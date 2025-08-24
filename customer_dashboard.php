<?php
require_once 'config/db.php';
require_once 'utils/activity_notifications.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'customer') {
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

// Get customer's order statistics
$sql_orders = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
    SUM(total_amount) as total_spent
FROM orders 
WHERE customer_id = ?";

$total_orders = 0;
$pending_orders = 0;
$completed_orders = 0;
$total_spent = 0;

if ($stmt = mysqli_prepare($conn, $sql_orders)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $total_orders = $row['total_orders'];
            $pending_orders = $row['pending_orders'];
            $completed_orders = $row['completed_orders'];
            $total_spent = $row['total_spent'] ?: 0;
        }
    }
    mysqli_stmt_close($stmt);
}

// Get recent orders
$sql_recent = "SELECT order_id, order_date, total_amount, status 
               FROM orders 
               WHERE customer_id = ? 
               ORDER BY order_date DESC 
               LIMIT 5";

$recent_orders = [];
if ($stmt = mysqli_prepare($conn, $sql_recent)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $recent_orders[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// Get active shipments
$sql_shipments = "SELECT s.shipment_id, s.order_id, s.status, s.planned_arrival,
                         ol.name as origin, dl.name as destination
                  FROM shipments s
                  JOIN locations ol ON s.origin_location_id = ol.location_id
                  JOIN locations dl ON s.destination_location_id = dl.location_id
                  WHERE s.order_id IN (SELECT order_id FROM orders WHERE customer_id = ?)
                  AND s.status IN ('pending', 'assigned', 'in_transit', 'out_for_delivery')
                  ORDER BY s.created_at DESC
                  LIMIT 5";

$active_shipments = [];
if ($stmt = mysqli_prepare($conn, $sql_shipments)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $active_shipments[] = $row;
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
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h2 class="mb-2">Welcome to your Dashboard, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                        <p class="mb-0">Manage your orders and track your shipments easily.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-shopping-cart fa-2x text-primary mb-2"></i>
                        <h4><?php echo $total_orders; ?></h4>
                        <p class="text-muted mb-0">Total Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h4><?php echo $pending_orders; ?></h4>
                        <p class="text-muted mb-0">Pending Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h4><?php echo $completed_orders; ?></h4>
                        <p class="text-muted mb-0">Completed Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-dollar-sign fa-2x text-info mb-2"></i>
                        <h4>৳<?php echo number_format($total_spent, 2); ?></h4>
                        <p class="text-muted mb-0">Total Spent</p>
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
                                <a href="<?php echo BASE_URL; ?>orders/create.php" class="btn btn-success btn-lg w-100">
                                    <i class="fas fa-plus me-2"></i>Place New Order
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="<?php echo BASE_URL; ?>orders/" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-shopping-cart me-2"></i>View My Orders
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="<?php echo BASE_URL; ?>shipments/" class="btn btn-info btn-lg w-100">
                                    <i class="fas fa-truck me-2"></i>Track Shipments
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders and Active Shipments -->
        <div class="row">
            <!-- Recent Orders -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Recent Orders</h5>
                        <a href="<?php echo BASE_URL; ?>orders/" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_orders)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                            <td>৳<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php
                                                echo $order['status'] == 'completed' ? 'success' :
                                                        ($order['status'] == 'pending' ? 'warning' : 'secondary');
                                                ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center mb-0">No orders yet. <a href="<?php echo BASE_URL; ?>orders/create.php">Place your first order!</a></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Active Shipments -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Active Shipments</h5>
                        <a href="<?php echo BASE_URL; ?>shipments/" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($active_shipments)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                    <tr>
                                        <th>Shipment ID</th>
                                        <th>Route</th>
                                        <th>Status</th>
                                        <th>ETA</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($active_shipments as $shipment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($shipment['shipment_id']); ?></td>
                                            <td>
                                                <small><?php echo htmlspecialchars($shipment['origin']); ?> → <?php echo htmlspecialchars($shipment['destination']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php
                                                echo $shipment['status'] == 'in_transit' ? 'primary' :
                                                        ($shipment['status'] == 'out_for_delivery' ? 'info' : 'warning');
                                                ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $shipment['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $shipment['planned_arrival'] ? date('M d', strtotime($shipment['planned_arrival'])) : 'TBD'; ?>
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
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

