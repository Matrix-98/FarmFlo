<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to access User Management.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "User Management";
$current_page = "users";

$users = [];
// FIX: Select u.customer_type and join with users table (twice) to get created_by and updated_by usernames
$sql = "SELECT u.user_id, u.username, u.role, u.customer_type, u.email, u.phone, u.created_at, u.updated_at,
               uc.username AS created_by_username, uu.username AS updated_by_username
        FROM users u
        LEFT JOIN users uc ON u.created_by = uc.user_id
        LEFT JOIN users uu ON u.updated_by = uu.user_id
        ORDER BY u.username ASC";
if ($result = mysqli_query($conn, $sql)) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }
        mysqli_free_result($result);
    }
} else {
    error_log("User list query failed: " . mysqli_error($conn));
    echo '<div class="alert alert-danger">ERROR: Could not retrieve user list. Please try again later.</div>';
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>User List</h2>
            <a href="<?php echo BASE_URL; ?>users/create.php" class="btn btn-success"><i class="fas fa-plus"></i> Add New User</a>
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

        <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Customer Type</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Last Updated By</th>
                            <th>Last Updated At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><span class="badge bg-<?php
                                    switch ($user['role']) {
                                        case 'admin': echo 'danger'; break;
                                        case 'logistics_manager': echo 'primary'; break;
                                        case 'farm_manager': echo 'success'; break;
                                        case 'warehouse_manager': echo 'info'; break;
                                        case 'driver': echo 'warning'; break;
                                        case 'customer': echo 'secondary'; break;
                                        default: echo 'light text-dark';
                                    }
                                ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['role']))); ?></span></td>
                                <td><?php echo htmlspecialchars(ucwords($user['customer_type'] ?: 'N/A')); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['created_by_username'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($user['updated_by_username'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['updated_at']); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>users/edit.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-primary me-2" title="Edit"><i class="fas fa-edit"></i></a>
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): // Prevent admin from deleting themselves ?>
                                        <a href="<?php echo BASE_URL; ?>users/delete.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this user? This cannot be undone.');"><i class="fas fa-trash-alt"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No users found. Click "Add New User" to get started.</div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>