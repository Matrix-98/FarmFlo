<?php
require_once 'config/db.php';
require_once 'utils/activity_notifications.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'farm_manager') {
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

// Get farm manager's information
$farm_manager_info = [];
$sql_manager = "SELECT user_id, username, email, phone, created_at 
                FROM users WHERE user_id = ? AND role = 'farm_manager'";

if ($stmt = mysqli_prepare($conn, $sql_manager)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $farm_manager_info = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Get farm production statistics
$sql_production_stats = "SELECT 
    COUNT(*) as total_productions,
    SUM(CASE WHEN status = 'planning' THEN 1 ELSE 0 END) as planning_productions,
    SUM(CASE WHEN status = 'sowing' THEN 1 ELSE 0 END) as sowing_productions,
    SUM(CASE WHEN status = 'growing' THEN 1 ELSE 0 END) as growing_productions,
    SUM(CASE WHEN status = 'harvesting' THEN 1 ELSE 0 END) as harvesting_productions,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_productions,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_productions,
    SUM(harvested_amount_kg) as total_harvested_kg
FROM farm_production 
WHERE farm_manager_id = ?";

$production_stats = [
    'total_productions' => 0,
    'planning_productions' => 0,
    'sowing_productions' => 0,
    'growing_productions' => 0,
    'harvesting_productions' => 0,
    'completed_productions' => 0,
    'failed_productions' => 0,
    'total_harvested_kg' => 0
];

if ($stmt = mysqli_prepare($conn, $sql_production_stats)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $production_stats = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Get active productions
$sql_active = "SELECT fp.production_id, fp.field_name, fp.status, fp.expected_harvest_date,
                      p.name as product_name, p.item_type as crop_type, fp.seed_amount_kg, fp.harvested_amount_kg
               FROM farm_production fp
               JOIN products p ON fp.product_id = p.product_id
               WHERE fp.farm_manager_id = ? 
               AND fp.status IN ('planning', 'sowing', 'growing', 'harvesting')
               ORDER BY fp.expected_harvest_date ASC
               LIMIT 5";

$active_productions = [];
if ($stmt = mysqli_prepare($conn, $sql_active)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $active_productions[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Get recent harvests
$sql_recent = "SELECT fp.production_id, fp.field_name, fp.actual_harvest_date, fp.harvested_amount_kg,
                      p.name as product_name, p.item_type as crop_type
               FROM farm_production fp
               JOIN products p ON fp.product_id = p.product_id
               WHERE fp.farm_manager_id = ? 
               AND fp.status = 'completed'
               ORDER BY fp.actual_harvest_date DESC
               LIMIT 5";

$recent_harvests = [];
if ($stmt = mysqli_prepare($conn, $sql_recent)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_harvests[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Get inventory summary for farm products
$sql_inventory = "SELECT 
    COUNT(*) as total_items,
    SUM(quantity_kg) as total_quantity,
    SUM(CASE WHEN stage = 'available' THEN quantity_kg ELSE 0 END) as available_quantity,
    SUM(CASE WHEN stage = 'reserved' THEN quantity_kg ELSE 0 END) as reserved_quantity
FROM inventory i
JOIN products p ON i.product_id = p.product_id
        WHERE p.item_type IS NOT NULL";

$inventory_summary = [
    'total_items' => 0,
    'total_quantity' => 0,
    'available_quantity' => 0,
    'reserved_quantity' => 0
];

if ($result = mysqli_query($conn, $sql_inventory)) {
    $inventory_summary = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
}
?>