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
