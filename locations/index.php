<?php
require_once '../config/db.php';
require_once '../utils/id_generator.php';
require_once '../utils/inventory_helpers.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to access Location Management.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Location Management";
$current_page = "locations";

$locations = [];
// FIX: Include capacity_kg and capacity_m3 in the SELECT query
$sql = "SELECT l.location_id, l.location_code, l.name, l.address, l.type, l.latitude, l.longitude, l.capacity_kg, l.capacity_m3, l.created_at, l.updated_at,
               uc.username AS created_by_username, uu.username AS updated_by_username,
               GROUP_CONCAT(wm.username SEPARATOR ', ') AS assigned_managers
        FROM locations l
        LEFT JOIN users uc ON l.created_by = uc.user_id
        LEFT JOIN users uu ON l.updated_by = uu.user_id
        LEFT JOIN user_assigned_locations ual ON l.location_id = ual.location_id
        LEFT JOIN users wm ON ual.user_id = wm.user_id AND wm.role = 'warehouse_manager'
        GROUP BY l.location_id
        ORDER BY l.name ASC";
if ($result = mysqli_query($conn, $sql)) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $locations[] = $row;
        }
        mysqli_free_result($result);
    }
} else {
    error_log("Location list query failed: " . mysqli_error($conn));
    echo '<div class="alert alert-danger">ERROR: Could not retrieve location list. Please try again later.</div>';
}

// Calculate location statistics
$total_locations = count($locations);
$warehouse_count = count(array_filter($locations, function($loc) { return $loc['type'] == 'warehouse'; }));
$farm_count = count(array_filter($locations, function($loc) { return $loc['type'] == 'farm'; }));
$other_count = $total_locations - $warehouse_count - $farm_count;

// Get capacity summary for warehouse managers and admins (same as inventory page)
$capacity_summary = [];
if (in_array($_SESSION['role'], ['admin', 'warehouse_manager'])) {
    try {
        $capacity_summary = getCapacitySummary($conn, $_SESSION['user_id'], $_SESSION['role']);
    } catch (Exception $e) {
        error_log("Capacity summary failed: " . $e->getMessage());
    }
}

include '../includes/head.php';
include '../includes/sidebar.php';
?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="fas fa-map-marker-alt me-2"></i>Location Management</h2>
                <p class="text-muted mb-0">Manage your agricultural locations, warehouses, and farms.</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="refreshLocationData()" class="btn btn-outline-primary">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
                <a href="<?php echo BASE_URL; ?>locations/create.php" class="btn btn-success">
                    <i class="fas fa-plus me-1"></i>Add New Location
                </a>
            </div>
        </div>
    
        <!-- Success/Error Messages -->
        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
            echo '<i class="fas fa-check-circle me-2"></i>' . $_SESSION['success_message'];
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            echo '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
            echo '<i class="fas fa-exclamation-circle me-2"></i>' . $_SESSION['error_message'];
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            echo '</div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <!-- Location Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="dashboard-card">
                    <div class="card-icon bg-primary">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $total_locations; ?></h3>
                        <p>Total Locations</p>
                        <small class="text-muted">All location types</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="dashboard-card">
                    <div class="card-icon bg-success">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $warehouse_count; ?></h3>
                        <p>Warehouses</p>
                        <small class="text-muted">Storage facilities</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="dashboard-card">
                    <div class="card-icon bg-warning">
                        <i class="fas fa-seedling"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $farm_count; ?></h3>
                        <p>Farms</p>
                        <small class="text-muted">Production sites</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="dashboard-card">
                    <div class="card-icon bg-info">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $other_count; ?></h3>
                        <p>Other Locations</p>
                        <small class="text-muted">Distribution centers</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warehouse Capacity Overview -->
        <?php if (!empty($capacity_summary) && in_array($_SESSION['role'], ['admin', 'warehouse_manager'])): ?>
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
                                        
                                        <div class="d-flex gap-2 mt-3">
                                            <a href="<?php echo BASE_URL; ?>inventory/?location=<?php echo $warehouse['location_id']; ?>" class="btn btn-sm btn-outline-primary flex-fill">
                                                <i class="fas fa-eye me-1"></i>View Inventory
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>locations/edit.php?id=<?php echo $warehouse['location_id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
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

        <!-- Location List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Location List</h5>
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
                <?php if (!empty($locations)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="locationsTable">
                            <thead>
                                <tr>
                                    <th>Location Code</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Address</th>
                                    <th>Capacity</th>
                                    <th>Manager</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($locations as $location): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($location['location_code']); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($location['name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $location['type'] == 'warehouse' ? 'primary' : ($location['type'] == 'farm' ? 'success' : 'info'); ?>">
                                            <?php echo ucfirst($location['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($location['address']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($location['capacity_kg'] > 0 || $location['capacity_m3'] > 0): ?>
                                            <div class="small">
                                                <?php if ($location['capacity_kg'] > 0): ?>
                                                    <div><strong><?php echo number_format($location['capacity_kg'], 2); ?> kg</strong></div>
                                                <?php endif; ?>
                                                <?php if ($location['capacity_m3'] > 0): ?>
                                                    <div><strong><?php echo number_format($location['capacity_m3'], 3); ?> m³</strong></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($location['assigned_managers']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($location['assigned_managers']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?php echo BASE_URL; ?>locations/view.php?id=<?php echo $location['location_id']; ?>" 
                                               class="btn btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>locations/edit.php?id=<?php echo $location['location_id']; ?>" 
                                               class="btn btn-outline-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteLocation(<?php echo $location['location_id']; ?>, '<?php echo htmlspecialchars($location['name']); ?>')" 
                                                    title="Delete">
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
                    <div class="text-center py-4">
                        <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No locations found</h5>
                        <p class="text-muted">Get started by adding your first location.</p>
                        <a href="<?php echo BASE_URL; ?>locations/create.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add New Location
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function deleteLocation(locationId, locationName) {
    if (confirm(`Are you sure you want to delete "${locationName}"? This action cannot be undone.`)) {
        window.location.href = '<?php echo BASE_URL; ?>locations/delete.php?id=' + locationId;
    }
}

function refreshLocationData() {
    location.reload();
}

function exportToCSV() {
    // Get the table data
    const table = document.getElementById('locationsTable');
    const rows = table.querySelectorAll('tbody tr');
    
    let csv = 'Location Code,Name,Type,Address,Capacity,Manager\n';
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const rowData = [];
        
        cells.forEach((cell, index) => {
            if (index < 6) { // Exclude Actions column
                let text = cell.textContent.trim();
                // Remove extra whitespace and newlines
                text = text.replace(/\s+/g, ' ').replace(/\n/g, ' ');
                rowData.push(`"${text}"`);
            }
        });
        
        csv += rowData.join(',') + '\n';
    });
    
    // Create and download the CSV file
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'locations_export.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Initialize DataTable
$(document).ready(function() {
    $('#locationsTable').DataTable({
        responsive: true,
        order: [[1, 'asc']], // Sort by name column
        pageLength: 25,
        language: {
            search: "Search locations:",
            lengthMenu: "Show _MENU_ locations per page",
            info: "Showing _START_ to _END_ of _TOTAL_ locations",
            infoEmpty: "No locations to show",
            infoFiltered: "(filtered from _MAX_ total locations)"
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>