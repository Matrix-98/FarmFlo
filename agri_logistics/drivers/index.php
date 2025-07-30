<?php
// This line is CRUCIAL for BASE_URL and $conn to be available
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php"); // Use BASE_URL for redirect
    exit;
}

// Check user role for access control
if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to access Driver Management.";
    header("location: " . BASE_URL . "dashboard.php"); // Use BASE_URL for redirect
    exit;
}

$page_title = "Driver Management";
$current_page = "drivers"; // For active state in sidebar

$drivers = [];
// Join with users table to potentially show username if linked
$sql = "SELECT d.driver_id, d.first_name, d.last_name, d.license_number, d.phone_number, d.status, u.username 
        FROM drivers d LEFT JOIN users u ON d.user_id = u.user_id 
        ORDER BY d.last_name ASC, d.first_name ASC";
if ($result = mysqli_query($conn, $sql)) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $drivers[] = $row;
        }
        mysqli_free_result($result);
    }
} else {
    // Log and display error if query fails
    error_log("Driver list query failed: " . mysqli_error($conn));
    echo '<div class="alert alert-danger">ERROR: Could not retrieve driver list. Please try again later.</div>';
}
// Note: mysqli_close($conn) is handled by includes/footer.php
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Driver List</h2>
            <a href="<?php echo BASE_URL; ?>drivers/create.php" class="btn btn-success"><i class="fas fa-plus"></i> Add New Driver</a>
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

        <?php if (!empty($drivers)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>License Number</th>
                            <th>Phone Number</th>
                            <th>Status</th>
                            <th>Linked User</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drivers as $driver): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($driver['driver_id']); ?></td>
                                <td><?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($driver['license_number']); ?></td>
                                <td><?php echo htmlspecialchars($driver['phone_number']); ?></td>
                                <td><span class="badge bg-<?php
                                    switch ($driver['status']) {
                                        case 'active': echo 'success'; break;
                                        case 'inactive': echo 'danger'; break;
                                        case 'on_leave': echo 'info'; break;
                                        default: echo 'secondary';
                                    }
                                ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $driver['status']))); ?></span></td>
                                <td><?php echo htmlspecialchars($driver['username'] ? $driver['username'] : 'N/A'); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>drivers/edit.php?id=<?php echo $driver['driver_id']; ?>" class="btn btn-sm btn-primary me-2" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="<?php echo BASE_URL; ?>drivers/delete.php?id=<?php echo $driver['driver_id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this driver?');"><i class="fas fa-trash-alt"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No drivers found. Click "Add New Driver" to get started.</div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>