<?php
require_once '../config/db.php';
require_once '../utils/id_generator.php';

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

// Get warehouse capacity data
$warehouse_locations = array_filter($locations, function($loc) {
    return $loc['type'] == 'warehouse' && ($loc['capacity_kg'] > 0 || $loc['capacity_m3'] > 0);
});

foreach ($warehouse_locations as &$warehouse) {
    // Get current inventory for this warehouse (simplified weight-based)
    $sql_current = "SELECT 
        COALESCE(SUM(i.quantity_kg), 0) AS current_weight,
        COALESCE(SUM(i.quantity_kg * 0.001), 0) AS current_volume
        FROM inventory i
        WHERE i.location_id = ? AND i.stage = 'available'";

    $current_weight = 0;
    $current_volume = 0;

    if ($stmt_current = mysqli_prepare($conn, $sql_current)) {
        mysqli_stmt_bind_param($stmt_current, "i", $warehouse['location_id']);
        mysqli_stmt_execute($stmt_current);
        $result_current = mysqli_stmt_get_result($stmt_current);
        $row_current = mysqli_fetch_assoc($result_current);
        mysqli_stmt_close($stmt_current);

        if ($row_current) {
            $current_weight = $row_current['current_weight'];
            $current_volume = $row_current['current_volume'];
        }
    }

    $warehouse['current_weight'] = $current_weight;
    $warehouse['current_volume'] = $current_volume;
    $warehouse['weight_usage_percent'] = $warehouse['capacity_kg'] > 0 ? ($current_weight / $warehouse['capacity_kg']) * 100 : 0;
    $warehouse['volume_usage_percent'] = $warehouse['capacity_m3'] > 0 ? ($current_volume / $warehouse['capacity_m3']) * 100 : 0;
}

include '../includes/head.php';
include '../includes/sidebar.php';
?>

    <div class="content">
        <?php include '../includes/navbar.php'; ?>

        <div class="container-fluid mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Location Management</h2>
                    <p class="text-muted mb-0">Manage your agricultural locations, warehouses, and farms.</p>
                </div>
                <a href="<?php echo BASE_URL; ?>locations/create.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add New Location
                </a>
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

            <!-- Warehouse Capacity Overview -->
            <?php if (!empty($warehouse_locations)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-warehouse me-2"></i>Warehouse Capacity Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($warehouse_locations as $warehouse): ?>
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

                                            <a href="<?php echo BASE_URL; ?>inventory/?location=<?php echo $warehouse['location_id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                                <i class="fas fa-eye me-1"></i>View Inventory
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Location List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Location List</h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                            <i class="fas fa-print me-1"></i>Print
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="exportToCSV()">
                            <i class="fas fa-download me-1"></i>Export
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
