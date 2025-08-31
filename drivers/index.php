<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check user role for access control
if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to access Driver Management.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Driver Management";
$current_page = "drivers";

$drivers = [];
// Join with users table to potentially show username if linked
$sql = "SELECT d.driver_id, d.driver_code, d.first_name, d.last_name, d.license_number, d.phone_number, d.email, d.vehicle_type, d.experience_years, d.status, d.created_at, d.updated_at,
               uc.username AS created_by_username, uu.username AS updated_by_username,
               u.username AS linked_username
        FROM drivers d
        LEFT JOIN users uc ON d.created_by = uc.user_id
        LEFT JOIN users uu ON d.updated_by = uu.user_id
        LEFT JOIN users u ON d.user_id = u.user_id
        ORDER BY d.created_at DESC";
if ($result = mysqli_query($conn, $sql)) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $drivers[] = $row;
        }
        mysqli_free_result($result);
    }
} else {
    error_log("Driver list query failed: " . mysqli_error($conn));
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
                <h2>Driver Management</h2>
                <p class="text-muted mb-0">Manage your fleet drivers and their information.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>drivers/create.php" class="btn btn-success">
                <i class="fas fa-plus me-2"></i>Add New Driver
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

        <!-- Drivers List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Drivers List</h5>
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
                <?php if (!empty($drivers)): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="driversTable">
                        <thead>
                            <tr>
                                <th>Driver Code</th>
                                <th>Driver</th>
                                <th>License Number</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Vehicle Type</th>
                                <th>Experience</th>
                                <th>Status</th>
                                <th>Linked User</th>
                                <th>Created By</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drivers as $driver): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($driver['driver_code']); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                            <i class="fas fa-user-tie text-primary" style="font-size: 0.8rem;"></i>
                                        </div>
                                        <div>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?></span>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($driver['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?php echo htmlspecialchars($driver['license_number']); ?></span>
                                </td>
                                <td>
                                    <a href="tel:<?php echo htmlspecialchars($driver['phone_number']); ?>" class="text-decoration-none">
                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($driver['phone_number']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($driver['email']): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($driver['email']); ?>" class="text-decoration-none">
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($driver['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($driver['vehicle_type'] ?: 'N/A')); ?></span>
                                </td>
                                <td>
                                    <?php if ($driver['experience_years']): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($driver['experience_years']); ?> years</span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        switch ($driver['status']) {
                                            case 'active': echo 'success'; break;
                                            case 'inactive': echo 'danger'; break;
                                            case 'on_leave': echo 'info'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>"><?php echo ucwords(str_replace('_', ' ', $driver['status'])); ?></span>
                                </td>
                                <td>
                                    <?php if ($driver['linked_username']): ?>
                                        <span class="badge bg-success"><?php echo htmlspecialchars($driver['linked_username']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Not Linked</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($driver['created_by_username'] ?: 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($driver['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="<?php echo BASE_URL; ?>drivers/edit.php?id=<?php echo $driver['driver_id']; ?>" class="btn btn-outline-primary" title="Edit Driver">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" onclick="deleteDriver(<?php echo $driver['driver_id']; ?>, '<?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?>')" title="Delete Driver">
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
                    <i class="fas fa-user-tie text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No Drivers Found</h5>
                    <p class="text-muted">Start by adding your first driver.</p>
                    <a href="<?php echo BASE_URL; ?>drivers/create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add First Driver
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function deleteDriver(driverId, driverName) {
    if (confirm('Are you sure you want to delete driver "' + driverName + '"? This action cannot be undone.')) {
        window.location.href = '<?php echo BASE_URL; ?>drivers/delete.php?id=' + driverId;
    }
}

function exportToCSV() {
    const table = document.getElementById('driversTable');
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
    link.setAttribute('download', 'drivers_export.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../includes/footer.php'; ?>