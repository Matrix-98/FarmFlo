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
