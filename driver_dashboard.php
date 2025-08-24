<?php
require_once 'config/db.php';
require_once 'utils/activity_notifications.php';
require_once 'utils/code_helpers.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'driver') {
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

// Get driver's information
$sql_driver = "SELECT d.*, u.username, u.phone 
               FROM drivers d 
               JOIN users u ON d.user_id = u.user_id 
               WHERE d.user_id = ?";

$driver_info = null;
if ($stmt = mysqli_prepare($conn, $sql_driver)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $driver_info = mysqli_fetch_assoc($result);
    }
    mysqli_stmt_close($stmt);
}

// Get driver's shipment statistics
$sql_shipments = "SELECT 
    COUNT(*) as total_shipments,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_shipments,
    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_shipments,
    SUM(CASE WHEN status = 'in_transit' THEN 1 ELSE 0 END) as in_transit_shipments,
    SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) as out_for_delivery_shipments,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_shipments,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_shipments
FROM shipments s
WHERE s.driver_id = (SELECT driver_id FROM drivers WHERE user_id = ?)";

$shipment_stats = [
    'total_shipments' => 0,
    'pending_shipments' => 0,
    'assigned_shipments' => 0,
    'in_transit_shipments' => 0,
    'out_for_delivery_shipments' => 0,
    'delivered_shipments' => 0,
    'failed_shipments' => 0
];

if ($stmt = mysqli_prepare($conn, $sql_shipments)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $shipment_stats = mysqli_fetch_assoc($result);
    }
    mysqli_stmt_close($stmt);
}

// Get active shipments
$sql_active = "SELECT s.shipment_id, s.shipment_code, s.order_id, s.status, s.planned_arrival,
                      ol.name as origin, dl.name as destination,
                      s.total_weight_kg, s.total_volume_m3
               FROM shipments s
               JOIN locations ol ON s.origin_location_id = ol.location_id
               JOIN locations dl ON s.destination_location_id = dl.location_id
               WHERE s.driver_id = (SELECT driver_id FROM drivers WHERE user_id = ?)
               AND s.status IN ('assigned', 'in_transit', 'out_for_delivery')
               ORDER BY s.planned_departure ASC
               LIMIT 5";

$active_shipments = [];
if ($stmt = mysqli_prepare($conn, $sql_active)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $active_shipments[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// Get recent deliveries
$sql_recent = "SELECT s.shipment_id, s.shipment_code, s.order_id, s.status, s.actual_arrival,
                      ol.name as origin, dl.name as destination
               FROM shipments s
               JOIN locations ol ON s.origin_location_id = ol.location_id
               JOIN locations dl ON s.destination_location_id = dl.location_id
               WHERE s.driver_id = (SELECT driver_id FROM drivers WHERE user_id = ?)
               AND s.status IN ('delivered', 'failed')
               ORDER BY s.actual_arrival DESC, s.updated_at DESC
               LIMIT 5";

$recent_deliveries = [];
if ($stmt = mysqli_prepare($conn, $sql_recent)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $recent_deliveries[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// Get recent activity (tracking updates, status changes)
$sql_activity = "SELECT 
    'tracking' as activity_type,
    t.tracking_id,
    t.shipment_id,
    t.location_name,
    t.status,
    t.notes,
    t.created_at,
    s.shipment_code
FROM shipment_tracking t
JOIN shipments s ON t.shipment_id = s.shipment_id
WHERE s.driver_id = (SELECT driver_id FROM drivers WHERE user_id = ?)
ORDER BY t.created_at DESC
LIMIT 10";

$recent_activity = [];
if ($stmt = mysqli_prepare($conn, $sql_activity)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $recent_activity[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}
?>

<?php include 'includes/head.php'; ?>
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
                                <h2 class="mb-2">Welcome to your Dashboard, <?php echo htmlspecialchars($driver_info['first_name'] . ' ' . $driver_info['last_name']); ?>!</h2>
                                <p class="mb-0">Manage your deliveries and track your shipments easily.</p>
                            </div>
                            <div class="text-end">
                                <p class="mb-1"><i class="fas fa-id-card me-2"></i>License: <?php echo htmlspecialchars($driver_info['license_number']); ?></p>
                                <p class="mb-0"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($driver_info['phone_number']); ?></p>
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
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h4><?php echo $shipment_stats['delivered_shipments']; ?></h4>
                        <p class="text-muted mb-0">Completed Deliveries</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-info mb-2"></i>
                        <h4><?php echo $shipment_stats['pending_shipments'] + $shipment_stats['assigned_shipments']; ?></h4>
                        <p class="text-muted mb-0">Pending Shipments</p>
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
                            <div class="col-md-4 mb-3">
                                <a href="<?php echo BASE_URL; ?>shipments/" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-truck me-2"></i>View My Shipments
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="<?php echo BASE_URL; ?>driver/tracking_entry.php" class="btn btn-success btn-lg w-100">
                                    <i class="fas fa-map-marker-alt me-2"></i>Update Tracking
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="<?php echo BASE_URL; ?>driver/shipment_status.php" class="btn btn-info btn-lg w-100">
                                    <i class="fas fa-clipboard-check me-2"></i>Update Status
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
