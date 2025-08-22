<?php
require_once 'config/db.php';
require_once 'utils/activity_notifications.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'warehouse_manager') {
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

// Get warehouse manager's information
$manager_info = [];
$sql_manager = "SELECT user_id, username, email, phone, created_at 
                FROM users WHERE user_id = ? AND role = 'warehouse_manager'";

if ($stmt = mysqli_prepare($conn, $sql_manager)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $manager_info = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Get warehouse manager's assigned locations
$assigned_locations = [];
$sql_assigned = "SELECT l.location_id, l.name 
                 FROM locations l
                 JOIN user_assigned_locations ual ON l.location_id = ual.location_id
                 WHERE ual.user_id = ? AND l.type = 'warehouse'";

if ($stmt = mysqli_prepare($conn, $sql_assigned)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $assigned_locations[] = $row['location_id'];
    }
    mysqli_stmt_close($stmt);
}

// Initialize default stats
$inventory_stats = [
    'total_items' => 0,
    'total_quantity' => 0,
    'available_quantity' => 0,
    'reserved_quantity' => 0,
    'in_transit_quantity' => 0,
    'lost_quantity' => 0
];

$expiring_inventory = [];
$recent_movements = [];

// If warehouses are assigned, get the data
if (!empty($assigned_locations)) {
    $location_ids = implode(',', $assigned_locations);

    // Get inventory statistics for assigned warehouses only
    $sql_inventory_stats = "SELECT 
        COUNT(*) as total_items,
        SUM(quantity_kg) as total_quantity,
        SUM(CASE WHEN stage = 'available' THEN quantity_kg ELSE 0 END) as available_quantity,
        SUM(CASE WHEN stage = 'reserved' THEN quantity_kg ELSE 0 END) as reserved_quantity,
        SUM(CASE WHEN stage = 'in-transit' THEN quantity_kg ELSE 0 END) as in_transit_quantity,
        SUM(CASE WHEN stage IN ('lost', 'damaged') THEN quantity_kg ELSE 0 END) as lost_quantity
    FROM inventory 
    WHERE location_id IN ($location_ids)";

    if ($result = mysqli_query($conn, $sql_inventory_stats)) {
        $inventory_stats = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
    }

    // Get expiring inventory for assigned warehouses only
    $sql_expiring = "SELECT i.inventory_id, i.quantity_kg, i.expiry_date, i.stage,
                            p.name as product_name, l.name as location_name
                     FROM inventory i
                     JOIN products p ON i.product_id = p.product_id
                     JOIN locations l ON i.location_id = l.location_id
                     WHERE i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                       AND i.stage = 'available'
                       AND i.location_id IN ($location_ids)
                     ORDER BY i.expiry_date ASC
                     LIMIT 5";

    if ($result = mysqli_query($conn, $sql_expiring)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $expiring_inventory[] = $row;
        }
        mysqli_free_result($result);
    }

    // Get recent inventory movements for assigned warehouses only
    $sql_movements = "SELECT i.inventory_id, i.quantity_kg, i.stage, i.created_at,
                            p.name as product_name, l.name as location_name,
                            u.username as created_by
                     FROM inventory i
                     JOIN products p ON i.product_id = p.product_id
                     JOIN locations l ON i.location_id = l.location_id
                     JOIN users u ON i.created_by = u.user_id
                     WHERE i.location_id IN ($location_ids)
                     ORDER BY i.created_at DESC
                     LIMIT 5";

    if ($result = mysqli_query($conn, $sql_movements)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $recent_movements[] = $row;
        }
        mysqli_free_result($result);
    }
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
                                <p class="mb-0">Manage inventory and track product movements in your assigned warehouses.</p>
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
