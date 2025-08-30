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
                                <h2 class="mb-2">Welcome to your Dashboard, <?php echo htmlspecialchars($farm_manager_info['username']); ?>!</h2>
                                <p class="mb-0">Manage your farm production and monitor crop growth efficiently.</p>
                            </div>
                            <div class="text-end">
                                <p class="mb-1"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($farm_manager_info['email']); ?></p>
                                <p class="mb-0"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($farm_manager_info['phone']); ?></p>
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
                        <i class="fas fa-seedling fa-2x text-success mb-2"></i>
                        <h4><?php echo $production_stats['total_productions']; ?></h4>
                        <p class="text-muted mb-0">Total Productions</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-leaf fa-2x text-warning mb-2"></i>
                        <h4><?php echo $production_stats['growing_productions'] + $production_stats['harvesting_productions']; ?></h4>
                        <p class="text-muted mb-0">Active Crops</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-primary mb-2"></i>
                        <h4><?php echo $production_stats['completed_productions']; ?></h4>
                        <p class="text-muted mb-0">Completed Harvests</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-weight-hanging fa-2x text-info mb-2"></i>
                        <h4><?php echo number_format($production_stats['total_harvested_kg'], 1); ?> kg</h4>
                        <p class="text-muted mb-0">Total Harvested</p>
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
                            <div class="col-md-3 mb-3">
                                <a href="<?php echo BASE_URL; ?>farm_production/" class="btn btn-success btn-lg w-100">
                                    <i class="fas fa-leaf me-2"></i>Manage Production
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="<?php echo BASE_URL; ?>products/" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-seedling me-2"></i>Manage Products
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="<?php echo BASE_URL; ?>inventory/" class="btn btn-info btn-lg w-100">
                                    <i class="fas fa-boxes me-2"></i>View Inventory
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Productions and Recent Harvests -->
        <div class="row">
            <!-- Active Productions -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-leaf me-2"></i>Active Productions</h5>
                        <a href="<?php echo BASE_URL; ?>farm_production/" class="btn btn-sm btn-outline-success">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($active_productions)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Field</th>
                                            <th>Crop</th>
                                            <th>Status</th>
                                            <th>Progress</th>
                                            <th>ETA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($active_productions as $production): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($production['field_name']); ?></td>
                                            <td>
                                                <small><?php echo htmlspecialchars($production['product_name']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $production['status'] == 'growing' ? 'success' : 
                                                        ($production['status'] == 'harvesting' ? 'warning' : 'info'); 
                                                ?>">
                                                    <?php echo ucfirst($production['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $progress = 0;
                                                if ($production['harvested_amount_kg'] > 0 && $production['seed_amount_kg'] > 0) {
                                                    $progress = min(100, ($production['harvested_amount_kg'] / $production['seed_amount_kg']) * 100);
                                                }
                                                ?>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" style="width: <?php echo $progress; ?>%">
                                                        <?php echo number_format($progress, 1); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo $production['expected_harvest_date'] ? date('M d', strtotime($production['expected_harvest_date'])) : 'TBD'; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center mb-0">No active productions.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Harvests -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Harvests</h5>
                        <a href="<?php echo BASE_URL; ?>farm_production/" class="btn btn-sm btn-outline-success">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_harvests)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Field</th>
                                            <th>Crop</th>
                                            <th>Harvested</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_harvests as $harvest): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($harvest['field_name']); ?></td>
                                            <td>
                                                <small><?php echo htmlspecialchars($harvest['product_name']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <?php echo number_format($harvest['harvested_amount_kg'], 1); ?> kg
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $harvest['actual_harvest_date'] ? date('M d, Y', strtotime($harvest['actual_harvest_date'])) : 'N/A'; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center mb-0">No recent harvests.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Summary -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Farm Product Inventory Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <h4 class="text-primary"><?php echo $inventory_summary['total_items']; ?></h4>
                                <p class="text-muted">Total Items</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h4 class="text-success"><?php echo number_format($inventory_summary['total_quantity'], 1); ?> kg</h4>
                                <p class="text-muted">Total Quantity</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h4 class="text-info"><?php echo number_format($inventory_summary['available_quantity'], 1); ?> kg</h4>
                                <p class="text-muted">Available</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h4 class="text-warning"><?php echo number_format($inventory_summary['reserved_quantity'], 1); ?> kg</h4>
                                <p class="text-muted">Reserved</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
