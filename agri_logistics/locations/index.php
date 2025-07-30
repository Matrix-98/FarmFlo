<?php
require_once '../config/db.php';

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
// FIX: Join with users table to get created_by and updated_by usernames
$sql = "SELECT l.location_id, l.name, l.address, l.type, l.latitude, l.longitude, l.created_at, l.updated_at,
               uc.username AS created_by_username, uu.username AS updated_by_username
        FROM locations l
        LEFT JOIN users uc ON l.created_by = uc.user_id
        LEFT JOIN users uu ON l.updated_by = uu.user_id
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
// mysqli_close($conn) is in footer.php
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Location List</h2>
            <a href="<?php echo BASE_URL; ?>locations/create.php" class="btn btn-success"><i class="fas fa-plus"></i> Add New Location</a>
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

        <?php if (!empty($locations)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Address</th>
                            <th>Type</th>
                            <th>Coordinates</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Last Updated By</th>
                            <th>Last Updated At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $location): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($location['location_id']); ?></td>
                                <td><?php echo htmlspecialchars($location['name']); ?></td>
                                <td><?php echo htmlspecialchars($location['address']); ?></td>
                                <td><?php echo htmlspecialchars($location['type']); ?></td>
                                <td><?php echo htmlspecialchars($location['latitude'] . ', ' . $location['longitude']); ?></td>
                                <td><?php echo htmlspecialchars($location['created_by_username'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($location['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($location['updated_by_username'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($location['updated_at']); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>locations/edit.php?id=<?php echo $location['location_id']; ?>" class="btn btn-sm btn-primary me-2" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="<?php echo BASE_URL; ?>locations/delete.php?id=<?php echo $location['location_id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this location?');"><i class="fas fa-trash-alt"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No locations found. Click "Add New Location" to get started.</div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>