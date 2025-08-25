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
$sql = "SELECT u.user_id, u.user_code, u.username, u.email, u.phone, u.role, u.customer_type, u.created_at, u.updated_at,
               uc.username AS created_by_username, uu.username AS updated_by_username
        FROM users u
        LEFT JOIN users uc ON u.created_by = uc.user_id
        LEFT JOIN users uu ON u.updated_by = uu.user_id
        ORDER BY u.created_at DESC";

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
                <h2>User Management</h2>
                <div class="d-flex gap-2">
                    <a href="<?php echo BASE_URL; ?>users/create.php" class="btn btn-success"><i class="fas fa-plus"></i> Add New User</a>
                    <a href="<?php echo BASE_URL; ?>users/manage_requests.php" class="btn btn-outline-primary"><i class="fas fa-user-clock"></i> Manage Requests</a>
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

            <!-- Users List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>System Users</h5>
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
                    <?php if (!empty($users)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="usersTable">
                                <thead>
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
                                        <td><?php echo htmlspecialchars($user['user_code']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                    <i class="fas fa-user text-primary" style="font-size: 0.8rem;"></i>
                                                </div>
                                                <span class="fw-semibold"><?php echo htmlspecialchars($user['username']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                    <span class="badge bg-<?php
                                    switch ($user['role']) {
                                        case 'admin': echo 'danger'; break;
                                        case 'farm_manager': echo 'success'; break;
                                        case 'logistics_manager': echo 'info'; break;
                                        case 'driver': echo 'warning'; break;
                                        case 'customer': echo 'secondary'; break;
                                        default: echo 'light text-dark';
                                    }
                                    ?>"><?php echo ucwords(str_replace('_', ' ', $user['role'])); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($user['customer_type']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($user['customer_type']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($user['created_by_username']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($user['updated_by_username']); ?></td>
                                        <td><?php echo $user['updated_at'] ? date('M d, Y', strtotime($user['updated_at'])) : 'N/A'; ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="<?php echo BASE_URL; ?>users/edit.php?id=<?php echo $user['user_id']; ?>" class="btn btn-outline-primary" title="Edit User">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Delete User">
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
                            <i class="fas fa-users text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No Users Found</h5>
                            <p class="text-muted">Start by adding your first user.</p>
                            <a href="<?php echo BASE_URL; ?>users/create.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Add First User
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function deleteUser(userId, username) {
            if (confirm('Are you sure you want to delete user "' + username + '"? This action cannot be undone.')) {
                window.location.href = '<?php echo BASE_URL; ?>users/delete.php?id=' + userId;
            }
        }

        function exportToCSV() {
            const table = document.getElementById('usersTable');
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
            link.setAttribute('download', 'users_export.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>

<?php include '../includes/footer.php'; ?>