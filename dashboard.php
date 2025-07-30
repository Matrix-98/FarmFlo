<?php
require_once 'config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

$page_title = "Dashboard";
$current_page = "dashboard";

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// --- Fetch data for the dashboard (dynamic based on role) ---
if ($user_role == 'driver') {
    $assigned_shipments = [];
    $driver_profile_id = null;
    
    // First, get the driver_id linked to the logged-in user_id
    $sql_driver_id = "SELECT driver_id FROM drivers WHERE user_id = ?";
    if ($stmt_driver_id = mysqli_prepare($conn, $sql_driver_id)) {
        mysqli_stmt_bind_param($stmt_driver_id, "i", $user_id);
        mysqli_stmt_execute($stmt_driver_id);
        mysqli_stmt_bind_result($stmt_driver_id, $driver_profile_id);
        mysqli_stmt_fetch($stmt_driver_id);
        mysqli_stmt_close($stmt_driver_id);
    }
    
    if ($driver_profile_id) {
        // Fetch shipments assigned to this driver that are active
        $sql_shipments = "SELECT s.shipment_id, s.planned_departure, s.status,
                                 dl.name AS destination_name, dl.address AS destination_address,
                                 v.license_plate
                          FROM shipments s
                          LEFT JOIN locations dl ON s.destination_location_id = dl.location_id
                          LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
                          WHERE s.driver_id = ? AND s.status IN ('assigned', 'picked_up', 'in_transit')
                          ORDER BY s.planned_departure ASC";
        
        if ($stmt_shipments = mysqli_prepare($conn, $sql_shipments)) {
            mysqli_stmt_bind_param($stmt_shipments, "i", $driver_profile_id);
            mysqli_stmt_execute($stmt_shipments);
            $result_shipments = mysqli_stmt_get_result($stmt_shipments);
            while ($row = mysqli_fetch_assoc($result_shipments)) {
                $assigned_shipments[] = $row;
            }
            mysqli_stmt_close($stmt_shipments);
        }
    }
} else {
    // For all other roles (admin, manager, customer), fetch general KPIs
    $total_products_count = 0;
    $active_shipments_count = 0;
    $delivered_shipments_count = 0;

    $sql_inventory_count = "SELECT SUM(quantity) AS total_items FROM inventory WHERE stage IN ('post-harvest', 'processing', 'storage')";
    if ($result = mysqli_query($conn, $sql_inventory_count)) {
        $row = mysqli_fetch_assoc($result);
        $total_products_count = $row['total_items'] ?: 0;
        mysqli_free_result($result);
    }

    $sql_active_shipments_count = "SELECT COUNT(shipment_id) AS total_active FROM shipments WHERE status IN ('pending', 'assigned', 'picked_up', 'in_transit', 'delayed')";
    if ($result = mysqli_query($conn, $sql_active_shipments_count)) {
        $row = mysqli_fetch_assoc($result);
        $active_shipments_count = $row['total_active'];
        mysqli_free_result($result);
    }

    $sql_delivered_shipments_count = "SELECT COUNT(shipment_id) AS total_delivered FROM shipments WHERE status = 'delivered'";
    if ($result = mysqli_query($conn, $sql_delivered_shipments_count)) {
        $row = mysqli_fetch_assoc($result);
        $delivered_shipments_count = $row['total_delivered'];
        mysqli_free_result($result);
    }
}
// mysqli_close($conn) is handled by includes/footer.php
?>

<?php include 'includes/head.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="content">
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h1 class="mb-4">Welcome to Your Dashboard!</h1>
        <p class="lead">You are logged in as a **<?php echo htmlspecialchars($_SESSION["role"]); ?>**.</p>
        <p>This dashboard provides a quick overview of key metrics based on your access level.</p>

        <?php if ($user_role == 'driver'): ?>
            <hr>
            <h2 class="mt-4 mb-3">My Assigned Shipments</h2>
            <?php if (!empty($assigned_shipments)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Shipment ID</th>
                                <th>Planned Departure</th>
                                <th>Destination</th>
                                <th>Vehicle</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_shipments as $shipment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($shipment['shipment_id']); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['planned_departure']); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['destination_name'] . ' (' . $shipment['destination_address'] . ')'); ?></td>
                                    <td><?php echo htmlspecialchars($shipment['license_plate'] ?: 'N/A'); ?></td>
                                    <td><span class="badge bg-<?php
                                        switch ($shipment['status']) {
                                            case 'pending': echo 'secondary'; break;
                                            case 'assigned': echo 'primary'; break;
                                            case 'picked_up': echo 'info'; break;
                                            case 'in_transit': echo 'warning'; break;
                                            case 'delivered': echo 'success'; break;
                                            case 'delayed': echo 'danger'; break;
                                            default: echo 'light text-dark';
                                        }
                                    ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $shipment['status']))); ?></span></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>shipments/view.php?id=<?php echo $shipment['shipment_id']; ?>" class="btn btn-sm btn-info me-2" title="View Details"><i class="fas fa-eye"></i></a>
                                        <a href="<?php echo BASE_URL; ?>driver_interface/update_tracking.php?shipment_id=<?php echo $shipment['shipment_id']; ?>" class="btn btn-sm btn-primary" title="Update Tracking"><i class="fas fa-map-marked-alt"></i> Update Tracking</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info mt-3">You have no active or assigned shipments.</div>
            <?php endif; ?>
        <?php else: ?>
            <div class="row mt-4">
                <div class="col-md-4 mb-4">
                    <div class="card text-center p-3 shadow-sm">
                        <i class="fas fa-boxes fa-3x text-info mb-3"></i>
                        <h5>Current Inventory</h5>
                        <p class="fs-4 fw-bold"><?php echo htmlspecialchars($total_products_count); ?> items</p>
                        <?php if (in_array($user_role, ['admin', 'farm_manager', 'warehouse_manager'])): ?>
                            <a href="<?php echo BASE_URL; ?>inventory/index.php" class="btn btn-sm btn-outline-info">View Details</a>
                        <?php else: ?>
                            <span class="text-muted small">Access Restricted</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card text-center p-3 shadow-sm">
                        <i class="fas fa-truck fa-3x text-success mb-3"></i>
                        <h5>Active Shipments</h5>
                        <p class="fs-4 fw-bold"><?php echo htmlspecialchars($active_shipments_count); ?> Shipments</p>
                        <?php if (in_array($user_role, ['admin', 'logistics_manager', 'driver', 'customer'])): ?>
                            <a href="<?php echo BASE_URL; ?>shipments/index.php" class="btn btn-sm btn-outline-success">View Shipments</a>
                        <?php else: ?>
                            <span class="text-muted small">Access Restricted</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card text-center p-3 shadow-sm">
                        <i class="fas fa-check-circle fa-3x text-primary mb-3"></i>
                        <h5>Delivered Shipments</h5>
                        <p class="fs-4 fw-bold"><?php echo htmlspecialchars($delivered_shipments_count); ?> Deliveries</p>
                        <?php if (in_array($user_role, ['admin', 'logistics_manager', 'customer'])): ?>
                            <a href="<?php echo BASE_URL; ?>shipments/index.php?status=delivered" class="btn btn-sm btn-outline-primary">View History</a>
                        <?php else: ?>
                            <span class="text-muted small">Access Restricted</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card text-center p-3 shadow-sm">
                        <i class="fas fa-chart-line fa-3x text-warning mb-3"></i>
                        <h5>View Full Reports</h5>
                        <p class="fs-4 fw-bold">KPI Analytics</p>
                        <?php if ($user_role == 'admin'): ?>
                            <a href="<?php echo BASE_URL; ?>reports/index.php" class="btn btn-sm btn-outline-warning">Generate Reports</a>
                        <?php else: ?>
                            <span class="text-muted small">Admin Only</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>