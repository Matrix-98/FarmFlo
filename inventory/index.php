<?php
require_once '../config/db.php';
require_once '../utils/inventory_helpers.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check user role for access control
if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'farm_manager' && $_SESSION["role"] != 'warehouse_manager') {
    $_SESSION['error_message'] = "You do not have permission to access Inventory Management.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Inventory Management";
$current_page = "inventory";

$user_role = $_SESSION['role'];
$logged_in_user_id = $_SESSION['user_id'];

// Get real-time inventory statistics
$inventory_stats = getRealTimeInventoryStats($logged_in_user_id, $user_role);

// Get inventory alerts
$inventory_alerts = getInventoryAlerts($logged_in_user_id, $user_role);

// Get warehouse manager's assigned locations if applicable
$assigned_locations = [];
if ($user_role == 'warehouse_manager') {
    $sql_assigned = "SELECT l.location_id, l.name 
                     FROM locations l
                     JOIN user_assigned_locations ual ON l.location_id = ual.location_id
                     WHERE ual.user_id = ? AND l.type = 'warehouse'";

    if ($stmt = mysqli_prepare($conn, $sql_assigned)) {
        mysqli_stmt_bind_param($stmt, "i", $logged_in_user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $assigned_locations[] = $row['location_id'];
        }
        mysqli_stmt_close($stmt);
    }
}

// Get inventory data with location and product information
$inventory = [];
$sql = "SELECT i.inventory_id, i.inventory_code, i.quantity_kg, i.stage, i.expiry_date, i.created_at, i.updated_at,
               p.name as product_name, p.product_code, p.item_type,
               l.name as location_name, l.location_code, l.type as location_type,
               u.username as created_by_username
        FROM inventory i
        JOIN products p ON i.product_id = p.product_id
        JOIN locations l ON i.location_id = l.location_id
        LEFT JOIN users u ON i.created_by = u.user_id";

// Add location filter for warehouse managers
if ($user_role == 'warehouse_manager' && !empty($assigned_locations)) {
    $location_ids = implode(',', $assigned_locations);
    $sql .= " WHERE i.location_id IN ($location_ids)";
}

$sql .= " ORDER BY i.created_at DESC";

if ($result = mysqli_query($conn, $sql)) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $inventory[] = $row;
        }
        mysqli_free_result($result);
    }
} else {
    error_log("Inventory list query failed: " . mysqli_error($conn));
    echo '<div class="alert alert-danger">ERROR: Could not retrieve inventory list. Please try again later.</div>';
}

// Get capacity summary for warehouse managers and admins
$capacity_summary = [];
if (in_array($user_role, ['admin', 'warehouse_manager'])) {
    try {
        $capacity_summary = getCapacitySummary($conn, $logged_in_user_id, $user_role);
    } catch (Exception $e) {
        error_log("Capacity summary failed: " . $e->getMessage());
    }
}

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
            <h2><i class="fas fa-boxes me-2"></i>Inventory Management</h2>
            <div class="d-flex gap-2">
                <button onclick="refreshInventoryData()" class="btn btn-outline-primary">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
                <a href="<?php echo BASE_URL; ?>inventory/create.php" class="btn btn-success">
                    <i class="fas fa-plus me-1"></i>Add New Inventory
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

        <!-- Inventory Alerts -->
        <?php if (!empty($inventory_alerts)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Inventory Alerts</h5>
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

        <!-- Real-time Inventory Statistics -->
        <div class="row mb-4">
            <div class="col-md-2 mb-3">
                <div class="dashboard-card">
                    <div class="card-icon bg-primary">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo number_format($inventory_stats['available']['total_kg'] ?? 0, 2); ?></h3>
                        <p>Available Inventory (kg)</p>
                        <small class="text-muted">
                            <?php echo $inventory_stats['available']['count'] ?? 0; ?> items across 
                            <?php echo $inventory_stats['available']['location_count'] ?? 0; ?> locations
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="dashboard-card">
                    <div class="card-icon bg-success">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo number_format($inventory_stats['in_transit']['total_kg'] ?? 0, 2); ?></h3>
                        <p>In Transit (kg)</p>
                        <small class="text-muted">
                            <?php echo $inventory_stats['in_transit']['count'] ?? 0; ?> shipments
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="dashboard-card">
                    <div class="card-icon bg-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo number_format($inventory_stats['reserved']['total_kg'] ?? 0, 2); ?></h3>
                        <p>Reserved (kg)</p>
                        <small class="text-muted">
                            <?php echo $inventory_stats['reserved']['count'] ?? 0; ?> pending orders
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="dashboard-card">
                    <div class="card-icon bg-info">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo number_format($inventory_stats['sold']['total_kg'] ?? 0, 2); ?></h3>
                        <p>Total Sold (kg)</p>
                        <small class="text-muted">
                            <?php echo $inventory_stats['sold']['count'] ?? 0; ?> completed orders
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="dashboard-card">
                    <div class="card-icon bg-danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo number_format($inventory_stats['lost']['total_kg'] ?? 0, 2); ?></h3>
                        <p>Total Loss (kg)</p>
                        <small class="text-muted">
                            <?php echo $inventory_stats['lost']['count'] ?? 0; ?> damaged/expired items
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warehouse Capacity Overview -->
        <?php if (!empty($capacity_summary) && in_array($user_role, ['admin', 'warehouse_manager'])): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-warehouse me-2"></i>Warehouse Capacity Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($capacity_summary as $warehouse): ?>
                            <?php if ($warehouse['type'] == 'warehouse'): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card border-<?php echo $warehouse['weight_usage_percent'] >= 90 ? 'danger' : ($warehouse['weight_usage_percent'] >= 80 ? 'warning' : 'success'); ?>">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($warehouse['name']); ?></h6>
                                        
                                        <?php if ($warehouse['capacity_kg'] > 0): ?>
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <small class="text-muted">Weight Usage</small>
                                                <small class="fw-semibold"><?php echo number_format($warehouse['weight_usage_percent'], 1); ?>%</small>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar <?php echo $warehouse['weight_usage_percent'] >= 90 ? 'bg-danger' : ($warehouse['weight_usage_percent'] >= 80 ? 'bg-warning' : 'bg-success'); ?>" 
                                                     style="width: <?php echo min(100, $warehouse['weight_usage_percent']); ?>%"></div>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo number_format($warehouse['current_weight'], 2); ?> / <?php echo number_format($warehouse['capacity_kg'], 2); ?> kg
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($warehouse['capacity_m3'] > 0): ?>
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <small class="text-muted">Volume Usage</small>
                                                <small class="fw-semibold"><?php echo number_format($warehouse['volume_usage_percent'], 1); ?>%</small>
                                            </div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar <?php echo $warehouse['volume_usage_percent'] >= 90 ? 'bg-danger' : ($warehouse['volume_usage_percent'] >= 80 ? 'bg-warning' : 'bg-info'); ?>" 
                                                     style="width: <?php echo min(100, $warehouse['volume_usage_percent']); ?>%"></div>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo number_format($warehouse['current_volume'], 3); ?> / <?php echo number_format($warehouse['capacity_m3'], 3); ?> m³
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Inventory List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Inventory List</h5>
                <div class="d-flex gap-2">
                    <button onclick="exportToCSV()" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download me-1"></i>Export CSV
                    </button>
                    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($inventory)): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="inventoryTable">
                        <thead>
                            <tr>
                                <th>Inventory Code</th>
                                <th>Product</th>
                                <th>Location</th>
                                <th>Quantity</th>
                                <th>Stage</th>
                                <th>Created By</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($item['inventory_code']); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                            <i class="fas fa-seedling text-primary" style="font-size: 0.8rem;"></i>
                                        </div>
                                        <div>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['product_code'] . ' - ' . $item['item_type']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                            <i class="fas fa-map-marker-alt text-success" style="font-size: 0.8rem;"></i>
                                        </div>
                                        <div>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($item['location_name']); ?></span>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['location_code'] . ' - ' . ucfirst($item['location_type'])); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo number_format($item['quantity_kg'], 2); ?> kg
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $item['stage'] == 'available' ? 'success' : ($item['stage'] == 'in-transit' ? 'warning' : ($item['stage'] == 'sold' ? 'info' : ($item['stage'] == 'lost' ? 'danger' : 'secondary'))); ?>">
                                        <?php echo ucfirst(str_replace('-', ' ', $item['stage'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($item['created_by_username']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="<?php echo BASE_URL; ?>inventory/edit.php?id=<?php echo $item['inventory_id']; ?>" class="btn btn-outline-primary" title="Edit Inventory">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" onclick="deleteInventory(<?php echo $item['inventory_id']; ?>, '<?php echo htmlspecialchars($item['product_name']); ?>')" title="Delete Inventory">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No Inventory Found</h5>
                    <p class="text-muted">Start by adding your first inventory record.</p>
                    <a href="<?php echo BASE_URL; ?>inventory/create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add First Inventory
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function deleteInventory(inventoryId, productName) {
    if (confirm('Are you sure you want to delete inventory for "' + productName + '"? This action cannot be undone.')) {
        window.location.href = '<?php echo BASE_URL; ?>inventory/delete.php?id=' + inventoryId;
    }
}

function exportToCSV() {
    const table = document.getElementById('inventoryTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const cols = row.querySelectorAll('td, th');
        let csvRow = [];
        
        for (let j = 0; j < cols.length - 1; j++) { // Exclude the Actions column
            let text = cols[j].innerText.replace(/"/g, '""');
            csvRow.push('"' + text + '"');
        }
        
        csv.push(csvRow.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'inventory_export.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function refreshInventoryData() {
    // Show loading state
    const refreshBtn = event.target;
    const originalText = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
    refreshBtn.disabled = true;
    
    // Reload the page after a short delay to show the loading state
    setTimeout(() => {
        window.location.reload();
    }, 500);
}

// Auto-refresh inventory data every 5 minutes
setInterval(() => {
    // Only refresh if user is active (not idle)
    if (!document.hidden) {
        // Refresh without reloading the page using AJAX
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                // Update only the statistics and alerts sections
                const parser = new DOMParser();
                const newDoc = parser.parseFromString(html, 'text/html');
                
                // Update statistics
                const newStats = newDoc.querySelector('.row.mb-4');
                const currentStats = document.querySelector('.row.mb-4');
                if (newStats && currentStats) {
                    currentStats.innerHTML = newStats.innerHTML;
                }
                
                // Update alerts
                const newAlerts = newDoc.querySelector('.card.border-warning');
                const currentAlerts = document.querySelector('.card.border-warning');
                if (newAlerts && currentAlerts) {
                    currentAlerts.innerHTML = newAlerts.innerHTML;
                } else if (newAlerts && !currentAlerts) {
                    // Add new alerts if they don't exist
                    const alertsContainer = document.querySelector('.container-fluid');
                    alertsContainer.insertBefore(newAlerts, alertsContainer.firstChild);
                }
            })
            .catch(error => {
                console.log('Auto-refresh failed:', error);
            });
    }
}, 300000); // 5 minutes
</script>

<?php include '../includes/footer.php'; ?>