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
