<?php
require_once 'config/db.php';
require_once 'utils/activity_notifications.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'logistics_manager') {
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Dashboard";
$current_page = "dashboard";

// Update user's dashboard visit timestamp
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    updateUserDashboardVisit($_SESSION['user_id'], $_SESSION['role']);
}

$user_id = $_SESSION['user_id'];

// Get logistics manager's information
$manager_info = [];
$sql_manager = "SELECT user_id, username, email, phone, created_at 
                FROM users WHERE user_id = ? AND role = 'logistics_manager'";

if ($stmt = mysqli_prepare($conn, $sql_manager)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $manager_info = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Get shipment statistics
$sql_shipment_stats = "SELECT 
    COUNT(*) as total_shipments,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_shipments,
    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_shipments,
    SUM(CASE WHEN status = 'in_transit' THEN 1 ELSE 0 END) as in_transit_shipments,
    SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) as out_for_delivery_shipments,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_shipments,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_shipments,
    SUM(total_weight_kg) as total_weight_shipped
FROM shipments";

$shipment_stats = [
        'total_shipments' => 0,
        'pending_shipments' => 0,
        'assigned_shipments' => 0,
        'in_transit_shipments' => 0,
        'out_for_delivery_shipments' => 0,
        'delivered_shipments' => 0,
        'failed_shipments' => 0,
        'total_weight_shipped' => 0
];

if ($result = mysqli_query($conn, $sql_shipment_stats)) {
    $shipment_stats = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
}

// Get active shipments
$sql_active = "SELECT s.shipment_id, s.status, s.planned_departure, s.planned_arrival,
                      o.shipping_address, u.username as customer_name,
                      d.first_name as driver_name, v.license_plate,
                      s.total_weight_kg
               FROM shipments s
               JOIN orders o ON s.order_id = o.order_id
               JOIN users u ON o.customer_id = u.user_id
               LEFT JOIN drivers d ON s.driver_id = d.driver_id
               LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
               WHERE s.status IN ('pending', 'assigned', 'in_transit', 'out_for_delivery')
               ORDER BY s.planned_departure ASC
               LIMIT 5";

$active_shipments = [];
if ($result = mysqli_query($conn, $sql_active)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $active_shipments[] = $row;
    }
    mysqli_free_result($result);
}

// Get available vehicles
$sql_vehicles = "SELECT v.vehicle_id, v.license_plate, v.type, v.capacity_weight,
                        CASE WHEN s.vehicle_id IS NULL THEN 'Available' ELSE 'In Use' END as status
                 FROM vehicles v
                 LEFT JOIN shipments s ON v.vehicle_id = s.vehicle_id 
                    AND s.status IN ('assigned', 'in_transit', 'out_for_delivery')
                 GROUP BY v.vehicle_id
                 ORDER BY status ASC, v.type ASC
                 LIMIT 5";

$available_vehicles = [];
if ($result = mysqli_query($conn, $sql_vehicles)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $available_vehicles[] = $row;
    }
    mysqli_free_result($result);
}

// Get driver statistics
$sql_driver_stats = "SELECT 
    COUNT(*) as total_drivers,
    SUM(CASE WHEN s.driver_id IS NOT NULL AND s.status IN ('assigned', 'in_transit', 'out_for_delivery') THEN 1 ELSE 0 END) as active_drivers
FROM drivers d
LEFT JOIN shipments s ON d.driver_id = s.driver_id";

$driver_stats = ['total_drivers' => 0, 'active_drivers' => 0];
if ($result = mysqli_query($conn, $sql_driver_stats)) {
    $driver_stats = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
}

include 'includes/head.php';
?>

<?php include 'includes/sidebar.php'; ?>

<div class="content">
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <!-- Welcome Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-2">Welcome to your Dashboard, <?php echo htmlspecialchars($manager_info['username']); ?>!</h2>
                                <p class="mb-0">Manage shipments, track deliveries, and coordinate logistics operations.</p>
                            </div>
                            <div class="text-end">
                                <p class="mb-1"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($manager_info['email']); ?></p>
                                <p class="mb-0"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($manager_info['phone']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-truck fa-2x text-primary mb-2"></i>
                        <h4><?php echo $shipment_stats['total_shipments']; ?></h4>
                        <p class="text-muted mb-0">Total Shipments</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-route fa-2x text-warning mb-2"></i>
                        <h4><?php echo $shipment_stats['in_transit_shipments'] + $shipment_stats['out_for_delivery_shipments']; ?></h4>
                        <p class="text-muted mb-0">Active Deliveries</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-user-tie fa-2x text-info mb-2"></i>
                        <h4><?php echo $driver_stats['active_drivers']; ?>/<?php echo $driver_stats['total_drivers']; ?></h4>
                        <p class="text-muted mb-0">Active Drivers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-weight-hanging fa-2x text-success mb-2"></i>
                        <h4><?php echo number_format($shipment_stats['total_weight_shipped'], 1); ?> kg</h4>
                        <p class="text-muted mb-0">Total Weight Shipped</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="<?php echo BASE_URL; ?>shipments/" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-truck me-2"></i>Manage Shipments
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="<?php echo BASE_URL; ?>drivers/" class="btn btn-info btn-lg w-100">
                                    <i class="fas fa-users me-2"></i>Manage Drivers
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="<?php echo BASE_URL; ?>vehicles/" class="btn btn-success btn-lg w-100">
                                    <i class="fas fa-truck-moving me-2"></i>Manage Vehicles
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="<?php echo BASE_URL; ?>reports/" class="btn btn-warning btn-lg w-100">
                                    <i class="fas fa-chart-bar me-2"></i>View Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Shipments and Vehicle Status -->
        <div class="row">
            <!-- Active Shipments -->
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-shipping-fast me-2"></i>Active Shipments</h5>
                        <a href="<?php echo BASE_URL; ?>shipments/" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($active_shipments)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Driver</th>
                                        <th>Vehicle</th>
                                        <th>Status</th>
                                        <th>Weight</th>
                                        <th>ETA</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($active_shipments as $shipment): ?>
                                        <tr>
                                            <td><?php echo $shipment['shipment_id']; ?></td>
                                            <td><?php echo htmlspecialchars($shipment['customer_name']); ?></td>
                                            <td>
                                                <?php echo $shipment['driver_name'] ? htmlspecialchars($shipment['driver_name']) : '<span class="text-muted">Not Assigned</span>'; ?>
                                            </td>
                                            <td>
                                                <?php echo $shipment['license_plate'] ? htmlspecialchars($shipment['license_plate']) : '<span class="text-muted">Not Assigned</span>'; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php
                                                echo $shipment['status'] == 'in_transit' ? 'primary' :
                                                        ($shipment['status'] == 'out_for_delivery' ? 'warning' : 'info');
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $shipment['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($shipment['total_weight_kg'], 1); ?> kg</td>
                                            <td>
                                                <?php echo date('M d, H:i', strtotime($shipment['planned_arrival'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center mb-0">No active shipments.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Vehicle Status -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-truck-moving me-2"></i>Vehicle Status</h5>
                        <a href="<?php echo BASE_URL; ?>vehicles/" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($available_vehicles)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                    <tr>
                                        <th>Vehicle</th>
                                        <th>Type</th>
                                        <th>Capacity</th>
                                        <th>Status</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($available_vehicles as $vehicle): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($vehicle['license_plate']); ?></td>
                                            <td><?php echo htmlspecialchars($vehicle['type']); ?></td>
                                            <td><?php echo number_format($vehicle['capacity_weight'], 1); ?> kg</td>
                                            <td>
                                                <span class="badge bg-<?php echo $vehicle['status'] == 'Available' ? 'success' : 'warning'; ?>">
                                                    <?php echo $vehicle['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center mb-0">No vehicles found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
