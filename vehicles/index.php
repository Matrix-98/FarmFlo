<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to access Vehicle Management.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Vehicle Management";
$current_page = "vehicles";

$vehicles = [];
$sql = "SELECT v.vehicle_id, v.vehicle_code, v.license_plate, v.type, v.manufacturer, v.model, v.year, v.fuel_type, v.capacity_weight, v.capacity_volume, v.status, v.created_at, v.updated_at,
               uc.username AS created_by_username, uu.username AS updated_by_username,
               u.username AS linked_username
        FROM vehicles v
        LEFT JOIN users uc ON v.created_by = uc.user_id
        LEFT JOIN users uu ON v.updated_by = uu.user_id
        LEFT JOIN users u ON v.user_id = u.user_id
        ORDER BY v.created_at DESC";

if ($result = mysqli_query($conn, $sql)) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $vehicles[] = $row;
        }
        mysqli_free_result($result);
    }
} else {
    error_log("Vehicle list query failed: " . mysqli_error($conn));
    // Don't show error message immediately, let the page load and show empty state
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
            <h2>Vehicle Management</h2>
            <a href="<?php echo BASE_URL; ?>vehicles/create.php" class="btn btn-success"><i class="fas fa-plus"></i> Add New Vehicle</a>
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

        <!-- Vehicles List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Vehicles List</h5>
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
                <?php if (!empty($vehicles)): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="vehiclesTable">
                        <thead>
                            <tr>
                                <th>Vehicle Code</th>
                                <th>License Plate</th>
                                <th>Type</th>
                                <th>Manufacturer/Model</th>
                                <th>Year</th>
                                <th>Fuel Type</th>
                                <th>Status</th>
                                <th>Capacity</th>
                                <th>Linked User</th>
                                <th>Created By</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehicles as $vehicle): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                            <i class="fas fa-truck text-primary" style="font-size: 0.8rem;"></i>
                                        </div>
                                        <div>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($vehicle['vehicle_code']); ?></span>
                                            <br><small class="text-muted">ID: <?php echo htmlspecialchars($vehicle['vehicle_id']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($vehicle['license_plate']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($vehicle['type'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <small class="fw-semibold"><?php echo htmlspecialchars($vehicle['manufacturer'] ?? 'N/A'); ?></small>
                                        <small class="text-muted"><?php echo htmlspecialchars($vehicle['model'] ?? 'N/A'); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($vehicle['year'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        switch ($vehicle['fuel_type']) {
                                            case 'electric': echo 'success'; break;
                                            case 'hybrid': echo 'info'; break;
                                            case 'diesel': echo 'warning'; break;
                                            case 'petrol': echo 'danger'; break;
                                            case 'lpg': echo 'primary'; break;
                                            default: echo 'light text-dark';
                                        }
                                    ?>"><?php echo ucwords($vehicle['fuel_type'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        switch ($vehicle['status']) {
                                            case 'available': echo 'success'; break;
                                            case 'in-use': echo 'warning'; break;
                                            case 'maintenance': echo 'danger'; break;
                                            case 'retired': echo 'secondary'; break;
                                            default: echo 'light text-dark';
                                        }
                                    ?>"><?php echo ucwords(str_replace('-', ' ', $vehicle['status'] ?? 'unknown')); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <small class="text-muted">Weight: <?php echo number_format($vehicle['capacity_weight'] ?? 0, 0); ?> kg</small>
                                        <small class="text-muted">Volume: <?php echo number_format($vehicle['capacity_volume'] ?? 0, 1); ?> m³</small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($vehicle['linked_username']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-link me-1"></i><?php echo htmlspecialchars($vehicle['linked_username']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Not Linked</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($vehicle['created_by_username'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($vehicle['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="<?php echo BASE_URL; ?>vehicles/edit.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-outline-primary" title="Edit Vehicle">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" onclick="deleteVehicle(<?php echo $vehicle['vehicle_id']; ?>, '<?php echo htmlspecialchars($vehicle['license_plate']); ?>')" title="Delete Vehicle">
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
                    <i class="fas fa-truck text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No Vehicles Found</h5>
                    <p class="text-muted">Start by adding your first vehicle.</p>
                    <a href="<?php echo BASE_URL; ?>vehicles/create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add First Vehicle
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function deleteVehicle(vehicleId, licensePlate) {
    if (confirm('Are you sure you want to delete vehicle "' + licensePlate + '"? This action cannot be undone.')) {
        window.location.href = '<?php echo BASE_URL; ?>vehicles/delete.php?id=' + vehicleId;
    }
}

function exportToCSV() {
    const table = document.getElementById('vehiclesTable');
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
    link.setAttribute('download', 'vehicles_export.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../includes/footer.php'; ?>