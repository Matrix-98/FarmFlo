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
