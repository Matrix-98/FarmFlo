<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

// Access control: Only Admin can access comprehensive reports (for now)
if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to access Reports & Analytics.";
    header("location: ../dashboard.php");
    exit;
}

$page_title = "Reports & Analytics";
$current_page = "reports";

// Initialize data for KPIs
$total_products = 0;
$total_locations = 0;
$total_shipments = 0;
$active_shipments = 0;
$delivered_shipments = 0;
$on_time_deliveries_rate = 'N/A';

// --- Fetch Data for KPIs ---

// Total Products (items in inventory)
$sql_inventory_items_count = "SELECT SUM(quantity) AS total_items FROM inventory WHERE stage IN ('post-harvest', 'processing', 'storage')";
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

// Active Shipments (pending, assigned, picked_up, in_transit, delayed)
$sql_active_shipments_count = "SELECT COUNT(shipment_id) AS total FROM shipments WHERE status IN ('pending', 'assigned', 'picked_up', 'in_transit', 'delayed')";
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
    'pending' => ['label' => 'Pending', 'bg' => 'rgba(108, 117, 125, 0.7)', 'border' => 'rgba(108, 117, 125, 1)'], // secondary
    'assigned' => ['label' => 'Assigned', 'bg' => 'rgba(0, 123, 255, 0.7)', 'border' => 'rgba(0, 123, 255, 1)'],   // primary
    'picked_up' => ['label' => 'Picked Up', 'bg' => 'rgba(23, 162, 184, 0.7)', 'border' => 'rgba(23, 162, 184, 1)'], // info
    'in_transit' => ['label' => 'In Transit', 'bg' => 'rgba(255, 193, 7, 0.7)', 'border' => 'rgba(255, 193, 7, 1)'], // warning
    'delivered' => ['label' => 'Delivered', 'bg' => 'rgba(40, 167, 69, 0.7)', 'border' => 'rgba(40, 167, 69, 1)'],   // success
    'delayed' => ['label' => 'Delayed', 'bg' => 'rgba(220, 53, 69, 0.7)', 'border' => 'rgba(220, 53, 69, 1)'],     // danger
    'cancelled' => ['label' => 'Cancelled', 'bg' => 'rgba(52, 58, 64, 0.7)', 'border' => 'rgba(52, 58, 64, 1)']    // dark
];

foreach ($status_map as $key => $details) {
    $chart_labels[] = $details['label'];
    $chart_data[] = $shipment_status_counts[$key] ?? 0; // Get count or 0 if status not found
    $chart_colors_bg[] = $details['bg'];
    $chart_colors_border[] = $details['border'];
}

// Connection is closed in footer.php
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
                    <p class="fs-4 fw-bold"><?php echo htmlspecialchars($total_products); ?></p>
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
        </div>

        <h4 class="mt-4 mb-3">Shipment Status Distribution</h4>
        <div class="card p-4 shadow-sm mb-4">
            <canvas id="shipmentStatusChart" style="max-height: 400px;"></canvas>
            <small class="text-muted mt-2">This chart shows the number of shipments in each status category.</small>
        </div>

        <h4 class="mt-4 mb-3">Detailed Delivery Performance Report</h4>
        <div class="card p-4 shadow-sm mb-4">
            <p class="text-muted">A table or list showing individual shipment delivery performance (planned vs. actual, delays) can be added here.</p>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('shipmentStatusChart').getContext('2d');

    // Data fetched from PHP and converted to JavaScript
    const chartLabels = <?php echo json_encode($chart_labels); ?>;
    const chartData = <?php echo json_encode($chart_data); ?>;
    const chartBackgroundColors = <?php echo json_encode($chart_colors_bg); ?>;
    const chartBorderColors = <?php echo json_encode($chart_colors_border); ?>;

    const shipmentStatusChart = new Chart(ctx, {
        type: 'bar', // You can change this to 'doughnut' or 'pie' for a different visualization
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Number of Shipments',
                data: chartData,
                backgroundColor: chartBackgroundColors,
                borderColor: chartBorderColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // Allow charts to be flexible
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { // Ensure integer ticks for count
                            if (Number.isInteger(value)) {
                                return value;
                            }
                        },
                        stepSize: 1 // Force steps of 1 for integer counts
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                title: {
                    display: false, // Title is handled by H4 above
                }
            }
        }
    });
});
</script>