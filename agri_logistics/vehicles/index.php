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
// FIX: Join with users table to get created_by and updated_by usernames
$sql = "SELECT v.vehicle_id, v.license_plate, v.type, v.capacity_weight, v.capacity_volume, v.status, v.current_latitude, v.current_longitude,
               v.created_at, v.updated_at, uc.username AS created_by_username, uu.username AS updated_by_username
        FROM vehicles v
        LEFT JOIN users uc ON v.created_by = uc.user_id
        LEFT JOIN users uu ON v.updated_by = uu.user_id
        ORDER BY v.license_plate ASC";
if ($result = mysqli_query($conn, $sql)) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $vehicles[] = $row;
        }
        mysqli_free_result($result);
    }
} else {
    error_log("Vehicle list query failed: " . mysqli_error($conn));
    echo '<div class="alert alert-danger">ERROR: Could not retrieve vehicle list. Please try again later.</div>';
}
// mysqli_close($conn) is in footer.php
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Vehicle List</h2>
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

        <?php if (!empty($vehicles)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>License Plate</th>
                            <th>Type</th>
                            <th>Capacity (Weight)</th>
                            <th>Capacity (Volume)</th>
                            <th>Status</th>
                            <th>Current Coords</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Last Updated By</th>
                            <th>Last Updated At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($vehicle['vehicle_id']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['license_plate']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['type']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['capacity_weight'] . ' kg'); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['capacity_volume'] . ' m³'); ?></td>
                                <td><span class="badge bg-<?php
                                    switch ($vehicle['status']) {
                                        case 'available': echo 'success'; break;
                                        case 'in-use': echo 'info'; break;
                                        case 'maintenance': echo 'warning'; break;
                                        case 'retired': echo 'danger'; break;
                                        default: echo 'secondary';
                                    }
                                ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $vehicle['status']))); ?></span></td>
                                <td><?php echo htmlspecialchars($vehicle['current_latitude'] . ', ' . $vehicle['current_longitude']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['created_by_username'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['created_at'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['updated_by_username'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['updated_at'] ?: 'N/A'); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>vehicles/edit.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-sm btn-primary me-2" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="<?php echo BASE_URL; ?>vehicles/delete.php?id=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this vehicle?');"><i class="fas fa-trash-alt"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No vehicles found. Click "Add New Vehicle" to get started.</div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>