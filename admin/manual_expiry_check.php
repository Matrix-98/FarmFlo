<?php
require_once '../config/db.php';
require_once '../utils/inventory_helpers.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check if user is admin
if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Manual Shelf Life Check";
$current_page = "admin";

// Process manual expiry check
$check_result = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['run_check'])) {
    $check_result = autoUpdateExpiredInventory();
}

// Get current inventory statistics
$inventory_stats = getRealTimeInventoryStats();
$inventory_alerts = getInventoryAlerts();
$expiring_inventory = getExpiringInventory(7);

include '../includes/head.php';
?>

<!-- Sidebar -->
<?php include '../includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="content">
    <!-- Navbar -->
    <?php include '../includes/navbar.php'; ?>

    <!-- Page Content -->
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-clock me-2"></i>Manual Shelf Life Check</h2>
            <div class="d-flex gap-2">
                <a href="<?php echo BASE_URL; ?>admin/" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Admin
                </a>
            </div>
        </div>

        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <!-- Manual Check Form -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Run Manual Shelf Life Check</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            This will automatically check for expired inventory and mark it as lost. 
                            The system will also log supply chain events for tracking purposes.
                        </p>
                        
                        <form method="POST" action="">
                            <button type="submit" name="run_check" class="btn btn-primary">
                                <i class="fas fa-play me-2"></i>Run Shelf Life Check
                            </button>
                        </form>
                        
                        <?php if ($check_result): ?>
                        <div class="mt-3">
                            <div class="alert alert-<?php echo $check_result['success'] ? 'success' : 'danger'; ?>">
                                <h6><i class="fas fa-<?php echo $check_result['success'] ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                                    <?php echo htmlspecialchars($check_result['message']); ?>
                                </h6>
                                <?php if ($check_result['updated_count'] > 0): ?>
                                <p class="mb-0">
                                    <strong>Updated Items:</strong> <?php echo $check_result['updated_count']; ?> inventory items marked as expired/lost.
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Inventory Status -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="dashboard-card">
                    <div class="card-icon bg-primary">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo number_format($inventory_stats['available']['total_kg'] ?? 0, 2); ?></h3>
                        <p>Available Inventory (kg)</p>
                        <small class="text-muted">
                            <?php echo $inventory_stats['available']['count'] ?? 0; ?> items
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="dashboard-card">
                    <div class="card-icon bg-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo count($expiring_inventory); ?></h3>
                        <p>Expiring Soon (7 days)</p>
                        <small class="text-muted">
                            Items expiring within a week
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="dashboard-card">
                    <div class="card-icon bg-danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo number_format($inventory_stats['lost']['total_kg'] ?? 0, 2); ?></h3>
                        <p>Total Loss (kg)</p>
                        <small class="text-muted">
                            <?php echo $inventory_stats['lost']['count'] ?? 0; ?> items
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="dashboard-card">
                    <div class="card-icon bg-info">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo count($inventory_alerts); ?></h3>
                        <p>Active Alerts</p>
                        <small class="text-muted">
                            Low stock & capacity warnings
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiring Inventory -->
        <?php if (!empty($expiring_inventory)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Inventory Expiring Within 7 Days</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Location</th>
                                        <th>Quantity</th>
                                        <th>Expiry Date</th>
                                        <th>Days Left</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expiring_inventory as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                    <i class="fas fa-clock text-warning" style="font-size: 0.8rem;"></i>
                                                </div>
                                                <span class="fw-semibold"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['location_name']); ?></td>
                                        <td>
                                            <span class="badge bg-warning">
                                                <?php echo number_format($item['quantity_kg'], 2); ?> kg
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($item['expiry_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $item['days_until_expiry'] <= 3 ? 'danger' : 'warning'; ?>">
                                                <?php echo $item['days_until_expiry']; ?> days
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $item['days_until_expiry'] <= 3 ? 'danger' : 'warning'; ?>">
                                                <?php echo $item['days_until_expiry'] <= 3 ? 'Critical' : 'Warning'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Inventory Alerts -->
        <?php if (!empty($inventory_alerts)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Active Inventory Alerts</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($inventory_alerts as $alert): ?>
                            <div class="col-md-6 mb-2">
                                <div class="alert alert-<?php echo $alert['type'] == 'low_stock' ? 'warning' : 'danger'; ?> mb-0">
                                    <i class="fas fa-<?php echo $alert['type'] == 'low_stock' ? 'exclamation' : 'times-circle'; ?> me-2"></i>
                                    <?php echo htmlspecialchars($alert['message']); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Warehouse Capacity -->
        <?php if (!empty($inventory_stats['warehouse_capacity'])): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-warehouse me-2"></i>Warehouse Capacity Utilization</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($inventory_stats['warehouse_capacity'] as $warehouse): ?>
                            <?php 
                            $usage_percent = $warehouse['capacity_kg'] > 0 ? 
                                ($warehouse['current_weight'] / $warehouse['capacity_kg']) * 100 : 0;
                            ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card border-<?php echo $usage_percent >= 90 ? 'danger' : ($usage_percent >= 80 ? 'warning' : 'success'); ?>">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($warehouse['name']); ?></h6>
                                        
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <small class="text-muted">Capacity Usage</small>
                                                <small class="fw-semibold"><?php echo number_format($usage_percent, 1); ?>%</small>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar <?php echo $usage_percent >= 90 ? 'bg-danger' : ($usage_percent >= 80 ? 'bg-warning' : 'bg-success'); ?>" 
                                                     style="width: <?php echo min(100, $usage_percent); ?>%"></div>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo number_format($warehouse['current_weight'], 2); ?> / <?php echo number_format($warehouse['capacity_kg'], 2); ?> kg
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-refresh the page every 30 seconds to show updated statistics
setInterval(() => {
    if (!document.hidden) {
        window.location.reload();
    }
}, 30000); // 30 seconds
</script>

<?php include '../includes/footer.php'; ?>
