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

        <?php if (empty($assigned_locations)): ?>
            <!-- No Assigned Warehouses Alert -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>No Warehouses Assigned</h5>
                        <p class="mb-2">You don't have any warehouses assigned to you yet. Please contact an administrator to assign warehouses to your account.</p>
                        <p class="mb-0"><strong>Current Status:</strong> You cannot access inventory management until warehouses are assigned to your account.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-boxes fa-2x text-primary mb-2"></i>
                        <h4><?php echo number_format($inventory_stats['available_quantity'], 1); ?> kg</h4>
                        <p class="text-muted mb-0">Available Stock</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h4><?php echo count($expiring_inventory); ?></h4>
                        <p class="text-muted mb-0">Expiring Soon</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-dolly fa-2x text-info mb-2"></i>
                        <h4><?php echo number_format($inventory_stats['reserved_quantity'], 1); ?> kg</h4>
                        <p class="text-muted mb-0">Reserved Stock</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                        <h4><?php echo number_format($inventory_stats['lost_quantity'], 1); ?> kg</h4>
                        <p class="text-muted mb-0">Lost/Damaged</p>
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
                            <div class="col-md-6 mb-3">
                                <a href="<?php echo BASE_URL; ?>inventory/" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-boxes me-2"></i>Manage Inventory
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="<?php echo BASE_URL; ?>reports/" class="btn btn-warning btn-lg w-100">
                                    <i class="fas fa-chart-bar me-2"></i>View Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiring Inventory -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Expiring Inventory</h5>
                        <a href="<?php echo BASE_URL; ?>inventory/" class="btn btn-sm btn-outline-warning">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($expiring_inventory)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Location</th>
                                        <th>Quantity</th>
                                        <th>Expires</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($expiring_inventory as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['location_name']); ?></td>
                                            <td><?php echo number_format($item['quantity_kg'], 1); ?> kg</td>
                                            <td>
                                                <?php
                                                $days_until = (strtotime($item['expiry_date']) - time()) / (60 * 60 * 24);
                                                $badge_class = $days_until <= 2 ? 'danger' : ($days_until <= 5 ? 'warning' : 'info');
                                                ?>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <?php echo date('M d', strtotime($item['expiry_date'])); ?>
                                                    (<?php echo ceil($days_until); ?> days)
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center mb-0">No expiring inventory in the next 7 days.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Inventory Movements -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Recent Inventory Movements</h5>
                        <a href="<?php echo BASE_URL; ?>inventory/" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_movements)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Product</th>
                                    <th>Location</th>
                                    <th>Quantity</th>
                                    <th>Stage</th>
                                    <th>Created By</th>