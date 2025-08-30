<?php
require_once '../config/db.php';
require_once '../utils/inventory_helpers.php'; // Include spoilage check

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to access Reports & Analytics.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Reports & Analytics";
$current_page = "reports";

// --- Trigger automatic spoilage check on reports page load ---
autoUpdateExpiredInventory();


// Initialize data for KPIs
$total_products = 0;
$total_locations = 0;
$total_shipments = 0;
$active_shipments = 0;
$delivered_shipments = 0;
$on_time_deliveries_rate = 'N/A';
$total_amount_sold = 0; // NEW KPI
$total_amount_loss = 0; // NEW KPI


// --- Fetch Data for KPIs ---

// Total Products (items in inventory)
$sql_inventory_items_count = "SELECT SUM(quantity_kg) AS total_items FROM inventory WHERE stage = 'available'";
if ($result = mysqli_query($conn, $sql_inventory_items_count)) {
    $row = mysqli_fetch_assoc($result);
    $total_products = $row['total_items'] ?: 0;
    mysqli_free_result($result);
}

// Total Locations
$sql_locations_count = "SELECT COUNT(location_id) AS total FROM locations";
if ($result = mysqli_query($conn, $sql_locations_count)) {
    $row = mysqli_fetch_assoc($result);
    $total_locations = $row['total'];
    mysqli_free_result($result);
}

// Total Shipments
$sql_shipments_count = "SELECT COUNT(shipment_id) AS total FROM shipments";
if ($result = mysqli_query($conn, $sql_shipments_count)) {
    $row = mysqli_fetch_assoc($result);
    $total_shipments = $row['total'];
    mysqli_free_result($result);
}

// Active Shipments (pending, assigned, in_transit, out_for_delivery)
$sql_active_shipments_count = "SELECT COUNT(shipment_id) AS total FROM shipments WHERE status IN ('pending', 'assigned', 'in_transit', 'out_for_delivery')";
if ($result = mysqli_query($conn, $sql_active_shipments_count)) {
    $row = mysqli_fetch_assoc($result);
    $active_shipments = $row['total'];
    mysqli_free_result($result);
}

// Delivered Shipments
$sql_delivered_shipments_count = "SELECT COUNT(shipment_id) AS total FROM shipments WHERE status = 'delivered'";
if ($result = mysqli_query($conn, $sql_delivered_shipments_count)) {
    $row = mysqli_fetch_assoc($result);
    $delivered_shipments = $row['total'];
    mysqli_free_result($result);
}

// On-time Deliveries Rate (Simplified: actual_arrival <= planned_arrival for delivered)
$sql_on_time = "SELECT COUNT(shipment_id) AS on_time FROM shipments WHERE status = 'delivered' AND actual_arrival IS NOT NULL AND actual_arrival <= planned_arrival";
$sql_total_delivered_for_rate = "SELECT COUNT(shipment_id) AS total_delivered FROM shipments WHERE status = 'delivered' AND actual_arrival IS NOT NULL";

$on_time_count = 0;
$total_delivered_for_rate_count = 0;

if ($result_on_time = mysqli_query($conn, $sql_on_time)) {
    $row_on_time = mysqli_fetch_assoc($result_on_time);
    $on_time_count = $row_on_time['on_time'];
    mysqli_free_result($result_on_time);
}

if ($result_total_delivered_for_rate = mysqli_query($conn, $sql_total_delivered_for_rate)) {
    $row_total_delivered_for_rate = mysqli_fetch_assoc($result_total_delivered_for_rate);
    $total_delivered_for_rate_count = $row_total_delivered_for_rate['total_delivered'];
    mysqli_free_result($result_total_delivered_for_rate);
}

if ($total_delivered_for_rate_count > 0) {
    $on_time_deliveries_rate = round(($on_time_count / $total_delivered_for_rate_count) * 100, 2) . '%';
} else {
    $on_time_deliveries_rate = 'N/A';
}

// Calculate Total Amount Sold (actual revenue received considering discounts)
$sql_total_sold = "SELECT 
                        SUM(op.quantity_kg * op.price_at_order * (1 - CASE WHEN u.customer_type = 'retailer' THEN 0.30 ELSE 0 END)) AS total_sold 
                    FROM order_products op
                    JOIN orders o ON op.order_id = o.order_id
                    JOIN users u ON o.customer_id = u.user_id
                    JOIN shipments s ON o.order_id = s.order_id
                    WHERE s.status = 'delivered'";
if ($result = mysqli_query($conn, $sql_total_sold)) {
    $row = mysqli_fetch_assoc($result);
    $total_amount_sold = $row['total_sold'] ?: 0;
    mysqli_free_result($result);
}

// Calculate Total Amount Loss (actual cost of lost inventory)
$total_amount_loss = 0;

// 1. Calculate loss based on failed orders (order value that was lost)
$sql_failed_loss = "SELECT 
                        SUM(op.quantity_kg * op.price_at_order * (1 - CASE WHEN u.customer_type = 'retailer' THEN 0.30 ELSE 0 END)) AS failed_loss 
                    FROM order_products op
                    JOIN orders o ON op.order_id = o.order_id
                    JOIN users u ON o.customer_id = u.user_id
                    JOIN shipments s ON o.order_id = s.order_id
                    WHERE s.status = 'failed'";
if ($result = mysqli_query($conn, $sql_failed_loss)) {
    $row = mysqli_fetch_assoc($result);
    $total_amount_loss += $row['failed_loss'] ?: 0;
    mysqli_free_result($result);
}

// 2. Calculate loss from expired and damaged inventory (excluding inventory from failed shipments)
$sql_inventory_loss = "SELECT 
                          SUM(i.quantity_kg * p.price_per_unit) AS inventory_loss 
                      FROM inventory i
                      JOIN products p ON i.product_id = p.product_id
                      WHERE i.stage IN ('lost', 'damaged')
                      AND NOT EXISTS (
                          -- Check if this inventory was moved to lost due to a failed shipment
                          SELECT 1
                          FROM order_products op
                          JOIN orders o ON op.order_id = o.order_id
                          JOIN shipments s ON o.order_id = s.order_id
                          WHERE s.status = 'failed'
                          AND op.product_id = i.product_id
                          AND op.quantity_kg = i.quantity_kg
                      )";
if ($result = mysqli_query($conn, $sql_inventory_loss)) {
    $row = mysqli_fetch_assoc($result);
    $total_amount_loss += $row['inventory_loss'] ?: 0;
    mysqli_free_result($result);
}


// --- Fetch Data for Shipment Status Chart ---
$shipment_status_counts = [];
$sql_status_counts = "SELECT status, COUNT(shipment_id) AS count FROM shipments GROUP BY status";
if ($result_status_counts = mysqli_query($conn, $sql_status_counts)) {
    while ($row = mysqli_fetch_assoc($result_status_counts)) {
        $shipment_status_counts[$row['status']] = $row['count'];
    }
    mysqli_free_result($result_status_counts);
}

// Prepare data for JavaScript
$chart_labels = [];
$chart_data = [];
$chart_colors_bg = [];
$chart_colors_border = [];

$status_map = [
    'pending' => ['label' => 'Pending', 'bg' => 'rgba(108, 117, 125, 0.7)', 'border' => 'rgba(108, 117, 125, 1)'],
    'assigned' => ['label' => 'Assigned', 'bg' => 'rgba(0, 123, 255, 0.7)', 'border' => 'rgba(0, 123, 255, 1)'],
    'in_transit' => ['label' => 'In Transit', 'bg' => 'rgba(255, 193, 7, 0.7)', 'border' => 'rgba(255, 193, 7, 1)'],
    'out_for_delivery' => ['label' => 'Out for Delivery', 'bg' => 'rgba(23, 162, 184, 0.7)', 'border' => 'rgba(23, 162, 184, 1)'],
    'delivered' => ['label' => 'Delivered', 'bg' => 'rgba(40, 167, 69, 0.7)', 'border' => 'rgba(40, 167, 69, 1)'],
    'failed' => ['label' => 'Failed', 'bg' => 'rgba(220, 53, 69, 0.7)', 'border' => 'rgba(220, 53, 69, 1)'],
    'cancelled' => ['label' => 'Cancelled', 'bg' => 'rgba(52, 58, 64, 0.7)', 'border' => 'rgba(52, 58, 64, 1)']
];

foreach ($status_map as $key => $details) {
    $chart_labels[] = $details['label'];
    $chart_data[] = $shipment_status_counts[$key] ?? 0;
    $chart_colors_bg[] = $details['bg'];
    $chart_colors_border[] = $details['border'];
}

// Prepare data for NEW Profit/Loss Chart
$profit_loss_labels = ['Total Sold', 'Total Loss'];
$profit_loss_data = [$total_amount_sold, $total_amount_loss];
$profit_loss_colors = ['rgba(40, 167, 69, 0.7)', 'rgba(220, 53, 69, 0.7)'];

// --------------------
// Static charts (3–7)
// --------------------

// 3) Inventory Stock Levels by Location
$inv_loc_labels = [];
$inv_loc_values = [];
$sql_chart3 = "SELECT label, value FROM chart_values WHERE chart_key = 'inventory_by_location' ORDER BY id ASC";
if ($result = mysqli_query($conn, $sql_chart3)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $inv_loc_labels[] = (string)$row['label'];
        $inv_loc_values[] = (float)$row['value'];
    }
    mysqli_free_result($result);
}

// 4) Top 5 Best-Selling Products (by value)
$top_products_labels = [];
$top_products_values = [];
$sql_chart4 = "SELECT label, value FROM chart_values WHERE chart_key = 'top_products' ORDER BY value DESC, id ASC LIMIT 5";
if ($result = mysqli_query($conn, $sql_chart4)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $top_products_labels[] = (string)$row['label'];
        $top_products_values[] = (float)$row['value'];
    }
    mysqli_free_result($result);
}

// 5) Monthly Shipment Volume
$monthly_labels = [];
$monthly_values = [];
$sql_chart5 = "SELECT label, value FROM chart_values WHERE chart_key = 'monthly_shipment_volume' ORDER BY id ASC";
if ($result = mysqli_query($conn, $sql_chart5)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $monthly_labels[] = (string)$row['label'];
        $monthly_values[] = (float)$row['value'];
    }
    mysqli_free_result($result);
}

// 6) Delivery Performance by Driver (two series)
$driver_map = [];
$sql_chart6 = "SELECT label, series, value FROM chart_values WHERE chart_key = 'driver_delivery_performance'";
if ($result = mysqli_query($conn, $sql_chart6)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $label = (string)$row['label'];
        $series = (string)($row['series'] ?? 'on_time');
        $value = (float)$row['value'];
        if (!isset($driver_map[$label])) {
            $driver_map[$label] = ['on_time' => 0.0, 'delayed' => 0.0];
        }
        if ($series === 'delayed') {
            $driver_map[$label]['delayed'] += $value;
        } else {
            $driver_map[$label]['on_time'] += $value;
        }
    }
    mysqli_free_result($result);
}
ksort($driver_map);
$driver_labels = array_keys($driver_map);
$driver_on_time = array_map(function($m){return (float)$m['on_time'];}, array_values($driver_map));
$driver_delayed = array_map(function($m){return (float)$m['delayed'];}, array_values($driver_map));

// 7) Inventory Value by Stage (Pie)
$stage_labels = [];
$stage_values = [];
$sql_chart7 = "SELECT label, value FROM chart_values WHERE chart_key = 'inventory_value_by_stage' ORDER BY id ASC";
if ($result = mysqli_query($conn, $sql_chart7)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $stage_labels[] = (string)$row['label'];
        $stage_values[] = (float)$row['value'];
    }
    mysqli_free_result($result);
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Reports & Analytics Dashboard</h2>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card text-center p-3 shadow-sm">
                    <i class="fas fa-boxes fa-3x text-info mb-3"></i>
                    <h5>Total Inventory Items</h5>
                    <p class="fs-4 fw-bold"><?php echo htmlspecialchars($total_products); ?> items</p>
                    <small class="text-muted">Currently in stock/storage</small>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center p-3 shadow-sm">
                    <i class="fas fa-map-marker-alt fa-3x text-primary mb-3"></i>
                    <h5>Total Locations</h5>
                    <p class="fs-4 fw-bold"><?php echo htmlspecialchars($total_locations); ?></p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center p-3 shadow-sm">
                    <i class="fas fa-truck-loading fa-3x text-success mb-3"></i>
                    <h5>Total Shipments</h5>
                    <p class="fs-4 fw-bold"><?php echo htmlspecialchars($total_shipments); ?></p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center p-3 shadow-sm">
                    <i class="fas fa-truck-moving fa-3x text-warning mb-3"></i>
                    <h5>Active Shipments</h5>
                    <p class="fs-4 fw-bold"><?php echo htmlspecialchars($active_shipments); ?></p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center p-3 shadow-sm">
                    <i class="fas fa-check-circle fa-3x text-primary mb-3"></i>
                    <h5>Delivered Shipments</h5>
                    <p class="fs-4 fw-bold"><?php echo htmlspecialchars($delivered_shipments); ?></p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center p-3 shadow-sm">
                    <i class="fas fa-calendar-check fa-3x text-success mb-3"></i>
                    <h5>On-Time Delivery Rate</h5>
                    <p class="fs-4 fw-bold"><?php echo htmlspecialchars($on_time_deliveries_rate); ?></p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center p-3 shadow-sm">
                    <i class="fas fa-dollar-sign fa-3x text-success mb-3"></i>
                    <h5>Total Sales</h5>
                    <p class="fs-4 fw-bold">৳<?php echo number_format($total_amount_sold, 2); ?></p>
                    <small class="text-muted">Revenue from sold inventory</small>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center p-3 shadow-sm">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h5>Total Loss</h5>
                    <p class="fs-4 fw-bold">৳<?php echo number_format($total_amount_loss, 2); ?></p>
                    <small class="text-muted">Loss from damaged/expired items</small>
                </div>
            </div>
        </div>
        
        <h4 class="mt-4 mb-3">Profit & Loss Overview</h4>
        <div class="card p-4 shadow-sm mb-4">
            <canvas id="profitLossChart" style="max-height: 400px;"></canvas>
            <small class="text-muted mt-2">Comparison of total sold revenue versus total loss from damaged/spoiled goods.</small>
        </div>

        <h4 class="mt-4 mb-3">Shipment Status Distribution</h4>
        <div class="card p-4 shadow-sm mb-4">
            <canvas id="shipmentStatusChart" style="max-height: 400px;"></canvas>
            <small class="text-muted mt-2">This chart shows the number of shipments in each status category.</small>
        </div>

        <h4 class="mt-4 mb-3">Inventory Stock Levels by Location</h4>
        <div class="card p-4 shadow-sm mb-4">
            <canvas id="chartInventoryByLocation" style="max-height: 400px;"></canvas>
            <small class="text-muted mt-2">Total inventory quantity across locations (kg).</small>
        </div>

        <h4 class="mt-4 mb-3">Top 5 Best-Selling Products</h4>
        <div class="card p-4 shadow-sm mb-4">
            <canvas id="chartTopProducts" style="max-height: 400px;"></canvas>
            <small class="text-muted mt-2">Highest revenue products (static input).</small>
        </div>

        <h4 class="mt-4 mb-3">Monthly Shipment Volume</h4>
        <div class="card p-4 shadow-sm mb-4">
            <canvas id="chartMonthlyShipmentVolume" style="max-height: 400px;"></canvas>
            <small class="text-muted mt-2">Number of shipments per month (static input order).</small>
        </div>

        <h4 class="mt-4 mb-3">Delivery Performance by Driver</h4>
        <div class="card p-4 shadow-sm mb-4">
            <canvas id="chartDriverPerformance" style="max-height: 400px;"></canvas>
            <small class="text-muted mt-2">On-time vs delayed deliveries by driver.</small>
        </div>

        <h4 class="mt-4 mb-3">Inventory Value by Stage</h4>
        <div class="card p-4 shadow-sm mb-4">
            <canvas id="chartInventoryValueByStage" style="max-height: 400px;"></canvas>
            <small class="text-muted mt-2">Percentage of total inventory value by stage.</small>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Shipment Status Chart ---
    const shipmentStatusCtx = document.getElementById('shipmentStatusChart').getContext('2d');
    const shipmentStatusLabels = <?php echo json_encode($chart_labels); ?>;
    const shipmentStatusData = <?php echo json_encode($chart_data); ?>;
    const shipmentStatusBackgrounds = <?php echo json_encode($chart_colors_bg); ?>;
    const shipmentStatusBorders = <?php echo json_encode($chart_colors_border); ?>;
    
    new Chart(shipmentStatusCtx, {
        type: 'bar',
        data: {
            labels: shipmentStatusLabels,
            datasets: [{
                label: 'Number of Shipments',
                data: shipmentStatusData,
                backgroundColor: shipmentStatusBackgrounds,
                borderColor: shipmentStatusBorders,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: function(value) { if (Number.isInteger(value)) { return value; } }, stepSize: 1 }
                }
            },
            plugins: {
                legend: { display: true, position: 'top' }
            }
        }
    });

    // --- NEW: Profit & Loss Chart ---
    const profitLossCtx = document.getElementById('profitLossChart').getContext('2d');
    const profitLossLabels = <?php echo json_encode($profit_loss_labels); ?>;
    const profitLossData = <?php echo json_encode($profit_loss_data); ?>;
    const profitLossColors = <?php echo json_encode($profit_loss_colors); ?>;

    new Chart(profitLossCtx, {
        type: 'bar', // A bar chart is great for comparing two values
        data: {
            labels: profitLossLabels,
            datasets: [{
                label: 'Amount (in Taka)',
                data: profitLossData,
                backgroundColor: profitLossColors,
                borderColor: profitLossColors.map(color => color.replace('0.7', '1')), // Make border solid
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '৳' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: { display: false }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += '৳' + context.parsed.y.toLocaleString();
                            return label;
                        }
                    }
                }
            }
        }
    });

    // ----- 3) Inventory by Location (Bar) -----
    const invLocEl = document.getElementById('chartInventoryByLocation');
    if (invLocEl) {
        const invLocCtx = invLocEl.getContext('2d');
        const invLocLabels = <?php echo json_encode($inv_loc_labels); ?>;
        const invLocValues = <?php echo json_encode($inv_loc_values); ?>;
        if (invLocLabels.length > 0) {
            new Chart(invLocCtx, {
                type: 'bar',
                data: { labels: invLocLabels, datasets: [{ label: 'Quantity (kg)', data: invLocValues, backgroundColor: 'rgba(23, 162, 184, 0.6)', borderColor: 'rgba(23, 162, 184, 1)', borderWidth: 1 }] },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
            });
        }
    }

    // ----- 4) Top 5 Products (Bar) -----
    const topProdEl = document.getElementById('chartTopProducts');
    if (topProdEl) {
        const topProdCtx = topProdEl.getContext('2d');
        const topProdLabels = <?php echo json_encode($top_products_labels); ?>;
        const topProdValues = <?php echo json_encode($top_products_values); ?>;
        if (topProdLabels.length > 0) {
            new Chart(topProdCtx, {
                type: 'bar',
                data: { labels: topProdLabels, datasets: [{ label: 'Revenue (৳)', data: topProdValues, backgroundColor: 'rgba(40, 167, 69, 0.6)', borderColor: 'rgba(40, 167, 69, 1)', borderWidth: 1 }] },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { tooltip: { callbacks: { label: (ctx) => '৳' + ctx.parsed.y.toLocaleString() } } } }
            });
        }
    }

    // ----- 5) Monthly Shipment Volume (Line) -----
    const monthlyEl = document.getElementById('chartMonthlyShipmentVolume');
    if (monthlyEl) {
        const monthlyCtx = monthlyEl.getContext('2d');
        const monthlyLabels = <?php echo json_encode($monthly_labels); ?>;
        const monthlyValues = <?php echo json_encode($monthly_values); ?>;
        if (monthlyLabels.length > 0) {
            new Chart(monthlyCtx, {
                type: 'line',
                data: { labels: monthlyLabels, datasets: [{ label: 'Shipments', data: monthlyValues, fill: false, tension: 0.2, borderColor: 'rgba(0, 123, 255, 1)', backgroundColor: 'rgba(0, 123, 255, 0.3)' }] },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
        }
    }

    // ----- 6) Driver Performance (Grouped Bar) -----
    const driverPerfEl = document.getElementById('chartDriverPerformance');
    if (driverPerfEl) {
        const driverPerfCtx = driverPerfEl.getContext('2d');
        const driverLabels = <?php echo json_encode($driver_labels); ?>;
        const driverOnTime = <?php echo json_encode($driver_on_time); ?>;
        const driverDelayed = <?php echo json_encode($driver_delayed); ?>;
        if (driverLabels.length > 0) {
            new Chart(driverPerfCtx, {
                type: 'bar',
                data: { labels: driverLabels, datasets: [ { label: 'On Time', data: driverOnTime, backgroundColor: 'rgba(40, 167, 69, 0.6)', borderColor: 'rgba(40, 167, 69, 1)', borderWidth: 1 }, { label: 'Delayed', data: driverDelayed, backgroundColor: 'rgba(220, 53, 69, 0.6)', borderColor: 'rgba(220, 53, 69, 1)', borderWidth: 1 } ] },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { position: 'top' } } }
            });
        }
    }

    // ----- 7) Inventory Value by Stage (Pie) -----
    const invStageEl = document.getElementById('chartInventoryValueByStage');
    if (invStageEl) {
        const invStageCtx = invStageEl.getContext('2d');
        const stageLabels = <?php echo json_encode($stage_labels); ?>;
        const stageValues = <?php echo json_encode($stage_values); ?>;
        if (stageLabels.length > 0) {
            const colors = [ 'rgba(0, 123, 255, 0.7)', 'rgba(23, 162, 184, 0.7)', 'rgba(40, 167, 69, 0.7)', 'rgba(255, 193, 7, 0.7)', 'rgba(220, 53, 69, 0.7)', 'rgba(108, 117, 125, 0.7)' ];
            new Chart(invStageCtx, {
                type: 'pie',
                data: { labels: stageLabels, datasets: [{ data: stageValues, backgroundColor: stageLabels.map((_, i) => colors[i % colors.length]), borderColor: stageLabels.map((_, i) => colors[i % colors.length].replace('0.7', '1')), borderWidth: 1 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' }, tooltip: { callbacks: { label: (ctx) => '৳' + ctx.parsed.toLocaleString() } } } }
            });
        }
    }
});
</script>