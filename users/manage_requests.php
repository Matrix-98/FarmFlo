<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Access control: Only Admin can manage requests
if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to manage customer requests.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Manage Customer Requests";
$current_page = "users";

$requests = [];
$sql = "SELECT request_id, username, customer_type, email, phone, request_date FROM registration_requests ORDER BY request_date ASC";
if ($result = mysqli_query($conn, $sql)) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $requests[] = $row;
        }
        mysqli_free_result($result);
    }
} else {
    error_log("Registration requests query failed: " . mysqli_error($conn));
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
                <div>
                    <h2>Customer Request Management</h2>
                    <p class="text-muted mb-0">Review and manage pending customer registration requests.</p>
                </div>
                <a href="<?php echo BASE_URL; ?>users/index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to User List
                </a>
            </div>

            <!-- Success/Error Messages -->
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

            <!-- Requests List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-user-clock me-2"></i>Pending Requests</h5>
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
                    <?php if (!empty($requests)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="requestsTable">
                                <thead>
                                <tr>
                                    <th>Request Details</th>
                                    <th>Customer Type</th>
                                    <th>Contact Information</th>
                                    <th>Request Date</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                    <i class="fas fa-user-clock text-warning" style="font-size: 0.8rem;"></i>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($request['username']); ?></span>
                                                    <br><small class="text-muted">Request #<?php echo htmlspecialchars($request['request_id']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                    <span class="badge bg-info text-white">
                                        <?php echo htmlspecialchars(ucwords($request['customer_type'])); ?>
                                    </span>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="d-flex align-items-center mb-1">
                                                    <i class="fas fa-envelope text-muted me-2" style="width: 16px;"></i>
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($request['email']); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-phone text-muted me-2" style="width: 16px;"></i>
                                                    <span class="text-muted"><?php echo htmlspecialchars($request['phone'] ?: 'N/A'); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                    <span class="badge bg-secondary">
                                        <?php echo date('M d, Y', strtotime($request['request_date'])); ?>
                                    </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-success" onclick="approveRequest(<?php echo $request['request_id']; ?>, '<?php echo htmlspecialchars($request['username']); ?>')" title="Approve Request">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" onclick="rejectRequest(<?php echo $request['request_id']; ?>, '<?php echo htmlspecialchars($request['username']); ?>')" title="Reject Request">
                                                    <i class="fas fa-times"></i>
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
                            <i class="fas fa-user-clock text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No Pending Requests</h5>
                            <p class="text-muted">All customer requests have been processed.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function approveRequest(requestId, username) {
            if (confirm('Are you sure you want to approve the request for "' + username + '"? This will create a new user account.')) {
                window.location.href = '<?php echo BASE_URL; ?>users/approve_request.php?id=' + requestId;
            }
        }

        function rejectRequest(requestId, username) {
            if (confirm('Are you sure you want to reject the request for "' + username + '"? This action cannot be undone.')) {
                window.location.href = '<?php echo BASE_URL; ?>users/reject_request.php?id=' + requestId;
            }
        }

        function exportToCSV() {
            const table = document.getElementById('requestsTable');
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
            link.setAttribute('download', 'customer_requests_export.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>

<?php include '../includes/footer.php'; ?>

#
