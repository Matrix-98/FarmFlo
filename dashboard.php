<?php
require_once 'config/db.php';
require_once 'utils/inventory_helpers.php';
require_once 'utils/activity_notifications.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Redirect users to their respective welcome pages
if ($_SESSION["role"] == 'customer') {
    header("location: " . BASE_URL . "customer_dashboard.php");
    exit;
} elseif ($_SESSION["role"] == 'driver') {
    header("location: " . BASE_URL . "driver_dashboard.php");
    exit;
} elseif ($_SESSION["role"] == 'farm_manager') {
    header("location: " . BASE_URL . "farm_manager_dashboard.php");
    exit;
} elseif ($_SESSION["role"] == 'logistics_manager') {
    header("location: " . BASE_URL . "logistics_manager_dashboard.php");
    exit;
} elseif ($_SESSION["role"] == 'warehouse_manager') {
    header("location: " . BASE_URL . "warehouse_manager_dashboard.php");
    exit;
}

$page_title = "Dashboard";
$current_page = "dashboard";

// Update user's dashboard visit timestamp
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    updateUserDashboardVisit($_SESSION['user_id'], $_SESSION['role']);
}

// Check for expired inventory and update automatically
checkAndUpdateExpiredInventory();

// Get inventory statistics
$inventory_stats = getInventoryStats();

// Get sales and loss values
$total_sales_value = getTotalSalesValue();
$total_loss_value = getTotalLossValue();

// Get expiring inventory
$expiring_inventory = getExpiringInventory(7); // Next 7 days

// Count products
$sql_products = "SELECT COUNT(*) as count FROM products";
if ($result = mysqli_query($conn, $sql_products)) {
    $row = mysqli_fetch_assoc($result);
    $total_products = $row['count'];
    mysqli_free_result($result);
}

// Count locations
$sql_locations = "SELECT COUNT(*) as count FROM locations";
if ($result = mysqli_query($conn, $sql_locations)) {
    $row = mysqli_fetch_assoc($result);
    $total_locations = $row['count'];
    mysqli_free_result($result);
}

// Count shipments
$sql_shipments = "SELECT COUNT(*) as count FROM shipments";
if ($result = mysqli_query($conn, $sql_shipments)) {
    $row = mysqli_fetch_assoc($result);
    $total_shipments = $row['count'];
    mysqli_free_result($result);
}

// Count active shipments
$sql_active = "SELECT COUNT(*) as count FROM shipments WHERE status IN ('pending', 'assigned', 'in_transit', 'out_for_delivery')";
if ($result = mysqli_query($conn, $sql_active)) {
    $row = mysqli_fetch_assoc($result);
    $active_shipments = $row['count'];
    mysqli_free_result($result);
}

// Get comprehensive recent activities
$recent_activities = [];

// Recent inventory activities
$sql_inventory_activities = "SELECT 
    'inventory' as type,
    i.quantity_kg,
    p.name as product_name,
    l.name as location_name,
    i.created_at,
    i.stage,
    CONCAT(u.username, ' added inventory') as action_description
FROM inventory i 
JOIN products p ON i.product_id = p.product_id 
JOIN locations l ON i.location_id = l.location_id 
LEFT JOIN users u ON i.created_by = u.user_id
ORDER BY i.created_at DESC LIMIT 3";

if ($result = mysqli_query($conn, $sql_inventory_activities)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_activities[] = $row;
    }
    mysqli_free_result($result);
}

// Recent shipment activities
$sql_shipment_activities = "SELECT 
    'shipment' as type,
    s.shipment_id,
    CONCAT(ol.name, ' → ', dl.name) as route,
    s.status,
    s.created_at,
    CONCAT(u.username, ' created shipment') as action_description
FROM shipments s
JOIN locations ol ON s.origin_location_id = ol.location_id
JOIN locations dl ON s.destination_location_id = dl.location_id
LEFT JOIN users u ON s.created_by = u.user_id
ORDER BY s.created_at DESC LIMIT 3";

if ($result = mysqli_query($conn, $sql_shipment_activities)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_activities[] = $row;
    }
    mysqli_free_result($result);
}

// Recent order activities
$sql_order_activities = "SELECT 
    'order' as type,
    o.total_amount,
    c.username as customer_name,
    o.status,
    o.created_at,
    CONCAT(c.username, ' placed order') as action_description
FROM orders o
JOIN users c ON o.customer_id = c.user_id
ORDER BY o.created_at DESC LIMIT 3";

if ($result = mysqli_query($conn, $sql_order_activities)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_activities[] = $row;
    }
    mysqli_free_result($result);
}

// Sort all activities by date
usort($recent_activities, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Take only the 8 most recent activities
$recent_activities = array_slice($recent_activities, 0, 8);

include 'includes/head.php';
?>

<!-- Sidebar -->
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="content">
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Dashboard Content -->
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h2>
            <div class="text-muted">
                <i class="fas fa-clock me-1"></i>
                Last updated: <?php echo date('M d, Y g:i A'); ?>
            </div>
        </div>

        <!-- Enhanced Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card text-center h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <i class="fas fa-boxes fa-2x text-primary mb-3"></i>
                        <h6 class="card-title mb-2">Total Products</h6>
                        <p class="card-text fs-4 fw-bold mb-1"><?php echo number_format($total_products); ?></p>
                        <small class="text-muted mt-auto">Available in system</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card text-center h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <i class="fas fa-map-marker-alt fa-2x text-success mb-3"></i>
                        <h6 class="card-title mb-2">Total Locations</h6>
                        <p class="card-text fs-4 fw-bold mb-1"><?php echo number_format($total_locations); ?></p>
                        <small class="text-muted mt-auto">Warehouses & farms</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card text-center h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <i class="fas fa-truck fa-2x text-warning mb-3"></i>
                        <h6 class="card-title mb-2">Total Shipments</h6>
                        <p class="card-text fs-4 fw-bold mb-1"><?php echo number_format($total_shipments); ?></p>
                        <small class="text-muted mt-auto">All time shipments</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card text-center h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <i class="fas fa-route fa-2x text-info mb-3"></i>
                        <h6 class="card-title mb-2">Active Shipments</h6>
                        <p class="card-text fs-4 fw-bold mb-1"><?php echo number_format($active_shipments); ?></p>
                        <small class="text-muted mt-auto">Currently in transit</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card text-center h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <i class="fas fa-dollar-sign fa-2x text-success mb-3"></i>
                        <h6 class="card-title mb-2">Total Sales</h6>
                        <p class="card-text fs-4 fw-bold mb-1">৳<?php echo number_format($total_sales_value, 2); ?></p>
                        <small class="text-success mt-auto">
                            <i class="fas fa-arrow-up me-1"></i>
                            <?php echo number_format(($total_sales_value / max($total_sales_value + $total_loss_value, 1)) * 100, 1); ?>% of total
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card text-center h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i>
                        <h6 class="card-title mb-2">Total Loss</h6>
                        <p class="card-text fs-4 fw-bold mb-1">৳<?php echo number_format($total_loss_value, 2); ?></p>
                        <small class="text-danger mt-auto">
                            <i class="fas fa-arrow-down me-1"></i>
                            <?php echo number_format(($total_loss_value / max($total_sales_value + $total_loss_value, 1)) * 100, 1); ?>% of total
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Status Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card text-center h-100 shadow-sm border-primary">
                    <div class="card-body d-flex flex-column">
                        <i class="fas fa-box fa-2x text-primary mb-3"></i>
                        <h6 class="card-title mb-2">Available Inventory</h6>
                        <p class="card-text fs-4 fw-bold mb-1"><?php echo number_format($inventory_stats['available']['total_kg'] ?? 0, 1); ?> kg</p>
                        <small class="text-muted mt-auto"><?php echo $inventory_stats['available']['count'] ?? 0; ?> items in stock</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card text-center h-100 shadow-sm border-info">
                    <div class="card-body d-flex flex-column">
                        <i class="fas fa-handshake fa-2x text-info mb-3"></i>
                        <h6 class="card-title mb-2">Reserved Inventory</h6>
                        <p class="card-text fs-4 fw-bold mb-1"><?php echo number_format($inventory_stats['reserved']['total_kg'] ?? 0, 1); ?> kg</p>
                        <small class="text-muted mt-auto"><?php echo $inventory_stats['reserved']['count'] ?? 0; ?> pending orders</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card text-center h-100 shadow-sm border-warning">
                    <div class="card-body d-flex flex-column">
                        <i class="fas fa-clock fa-2x text-warning mb-3"></i>
                        <h6 class="card-title mb-2">Expiring Soon</h6>
                        <p class="card-text fs-4 fw-bold mb-1"><?php echo number_format($inventory_stats['expiring_soon']['total_kg'] ?? 0, 1); ?> kg</p>
                        <small class="text-muted mt-auto"><?php echo $inventory_stats['expiring_soon']['count'] ?? 0; ?> items (7 days)</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card text-center h-100 shadow-sm border-secondary">
                    <div class="card-body d-flex flex-column">
                        <i class="fas fa-truck fa-2x text-secondary mb-3"></i>
                        <h6 class="card-title mb-2">In Transit</h6>
                        <p class="card-text fs-4 fw-bold mb-1"><?php echo number_format($inventory_stats['in-transit']['total_kg'] ?? 0, 1); ?> kg</p>
                        <small class="text-muted mt-auto"><?php echo $inventory_stats['in-transit']['count'] ?? 0; ?> items shipping</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities and Quick Actions -->
        <div class="row">
            <div class="col-lg-8">
                                 <div class="card shadow-sm">
                     <div class="card-header bg-light text-dark">
                         <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activities</h5>
                     </div>
                    <div class="card-body p-0">
                        <?php if (!empty($recent_activities)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Activity</th>
                                            <th>Details</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_activities as $activity): ?>
                                            <tr>
                                                                                                 <td>
                                                     <?php if ($activity['type'] == 'inventory'): ?>
                                                         <span class="badge bg-secondary"><i class="fas fa-box me-1"></i>Inventory</span>
                                                     <?php elseif ($activity['type'] == 'shipment'): ?>
                                                         <span class="badge bg-secondary"><i class="fas fa-truck me-1"></i>Shipment</span>
                                                     <?php elseif ($activity['type'] == 'order'): ?>
                                                         <span class="badge bg-secondary"><i class="fas fa-shopping-cart me-1"></i>Order</span>
                                                     <?php endif; ?>
                                                 </td>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($activity['action_description']); ?></div>
                                                    <?php if ($activity['type'] == 'inventory'): ?>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($activity['product_name']); ?> - 
                                                            <?php echo htmlspecialchars($activity['location_name']); ?>
                                                        </small>
                                                    <?php elseif ($activity['type'] == 'shipment'): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($activity['route']); ?></small>
                                                    <?php elseif ($activity['type'] == 'order'): ?>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($activity['customer_name']); ?> - 
                                                            ৳<?php echo number_format($activity['total_amount'], 2); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                                                                 <td>
                                                     <?php if ($activity['type'] == 'inventory'): ?>
                                                         <span class="badge bg-light text-dark">
                                                             <?php echo ucfirst($activity['stage']); ?>
                                                         </span>
                                                     <?php elseif ($activity['type'] == 'shipment'): ?>
                                                         <span class="badge bg-light text-dark">
                                                             <?php echo ucfirst(str_replace('_', ' ', $activity['status'])); ?>
                                                         </span>
                                                     <?php elseif ($activity['type'] == 'order'): ?>
                                                         <span class="badge bg-light text-dark">
                                                             <?php echo ucfirst($activity['status']); ?>
                                                         </span>
                                                     <?php endif; ?>
                                                 </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, g:i A', strtotime($activity['created_at'])); ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No recent activities</h6>
                                <p class="text-muted">Activities will appear here as they happen</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                                 <div class="card shadow-sm mb-4">
                     <div class="card-header bg-light text-dark">
                         <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                     </div>
                    <div class="card-body">
                                                 <div class="d-grid gap-3">
                             <a href="<?php echo BASE_URL; ?>inventory/create.php" class="btn btn-outline-secondary btn-lg">
                                 <i class="fas fa-plus me-2"></i>Add Inventory
                             </a>
                             <a href="<?php echo BASE_URL; ?>shipments/create.php" class="btn btn-outline-secondary btn-lg">
                                 <i class="fas fa-truck me-2"></i>Create Shipment
                             </a>
                             <a href="<?php echo BASE_URL; ?>orders/create.php" class="btn btn-outline-secondary btn-lg">
                                 <i class="fas fa-shopping-cart me-2"></i>New Order
                             </a>
                             <a href="<?php echo BASE_URL; ?>reports/" class="btn btn-outline-secondary btn-lg">
                                 <i class="fas fa-chart-bar me-2"></i>View Reports
                             </a>
                         </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Expiring Inventory Alert -->
                <?php if (!empty($expiring_inventory)): ?>
                                 <div class="card shadow-sm border-warning">
                     <div class="card-header bg-light text-dark">
                         <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Expiring Soon</h5>
                     </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($expiring_inventory, 0, 3) as $item): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($item['location_name']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold"><?php echo number_format($item['quantity_kg'], 1); ?> kg</div>
                                        <small class="text-danger">Expires: <?php echo date('M d', strtotime($item['expiry_date'])); ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($expiring_inventory) > 3): ?>
                        <div class="text-center mt-3">
                            <a href="<?php echo BASE_URL; ?>inventory/" class="btn btn-outline-warning btn-sm">
                                View All (<?php echo count($expiring_inventory); ?> items)
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.table td {
    vertical-align: middle;
    border-top: 1px solid #f8f9fa;
}

.badge {
    font-size: 0.75rem;
    padding: 0.5rem 0.75rem;
}

.btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    font-weight: 600;
}

/* Dashboard Card Enhancements */
.card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: 1px solid rgba(0,0,0,.125);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}

.card-body {
    padding: 1.5rem;
}

.card-title {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.75rem;
}

.card-text {
    color: #212529;
    margin-bottom: 0.5rem;
}

.card small {
    font-size: 0.875rem;
    line-height: 1.4;
}

/* Icon styling */
.card i.fa-2x {
    margin-bottom: 1rem;
    opacity: 0.9;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    
    .card-title {
        font-size: 0.9rem;
    }
    
    .card-text {
        font-size: 1.1rem !important;
    }
    
    .card small {
        font-size: 0.8rem;
    }
}

/* Border colors for inventory cards */
.border-primary {
    border-color: #007bff !important;
}

.border-info {
    border-color: #17a2b8 !important;
}

.border-warning {
    border-color: #ffc107 !important;
}

.border-secondary {
    border-color: #6c757d !important;
}
</style>

<?php include 'includes/footer.php'; ?>