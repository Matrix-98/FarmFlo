<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check if user is admin
if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Admin Tools";
$current_page = "admin";

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
            <h2><i class="fas fa-cogs me-2"></i>Admin Tools</h2>
            <div class="d-flex gap-2">
                <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
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

        <!-- Admin Tools Grid -->
        <div class="row">
            <!-- Manual Expiry Check -->
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-clock text-warning"></i>
                            </div>
                            <h5 class="card-title mb-0">Manual Expiry Check</h5>
                        </div>
                        <p class="card-text text-muted">
                            Manually trigger inventory expiry checks and view related statistics. 
                            Check for expired items and update their status.
                        </p>
                        <a href="<?php echo BASE_URL; ?>admin/manual_expiry_check.php" class="btn btn-warning">
                            <i class="fas fa-play me-2"></i>Run Expiry Check
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tracking Data View -->
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-map-marker-alt text-info"></i>
                            </div>
                            <h5 class="card-title mb-0">Tracking Data View</h5>
                        </div>
                        <p class="card-text text-muted">
                            View and filter all tracking data from drivers. 
                            Monitor shipment progress and delivery status.
                        </p>
                        <a href="<?php echo BASE_URL; ?>admin/tracking_view.php" class="btn btn-info">
                            <i class="fas fa-eye me-2"></i>View Tracking Data
                        </a>
                    </div>
                </div>
            </div>

            <!-- System Statistics -->
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-chart-line text-success"></i>
                            </div>
                            <h5 class="card-title mb-0">System Statistics</h5>
                        </div>
                        <p class="card-text text-muted">
                            View comprehensive system statistics and performance metrics. 
                            Monitor overall system health.
                        </p>
                        <a href="<?php echo BASE_URL; ?>reports/" class="btn btn-success">
                            <i class="fas fa-chart-bar me-2"></i>View Reports
                        </a>
                    </div>
                </div>
            </div>

            <!-- User Management -->
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-users text-primary"></i>
                            </div>
                            <h5 class="card-title mb-0">User Management</h5>
                        </div>
                        <p class="card-text text-muted">
                            Manage all system users, roles, and permissions. 
                            Create, edit, and monitor user accounts.
                        </p>
                        <a href="<?php echo BASE_URL; ?>users/" class="btn btn-primary">
                            <i class="fas fa-user-cog me-2"></i>Manage Users
                        </a>
                    </div>
                </div>
            </div>

            <!-- Inventory Management -->
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-secondary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-boxes text-secondary"></i>
                            </div>
                            <h5 class="card-title mb-0">Inventory Management</h5>
                        </div>
                        <p class="card-text text-muted">
                            Manage inventory across all locations. 
                            Monitor stock levels and inventory movements.
                        </p>
                        <a href="<?php echo BASE_URL; ?>inventory/" class="btn btn-secondary">
                            <i class="fas fa-boxes me-2"></i>Manage Inventory
                        </a>
                    </div>
                </div>
            </div>

            <!-- Shipment Management -->
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-truck text-danger"></i>
                            </div>
                            <h5 class="card-title mb-0">Shipment Management</h5>
                        </div>
                        <p class="card-text text-muted">
                            Monitor and manage all shipments. 
                            Track delivery status and assign drivers.
                        </p>
                        <a href="<?php echo BASE_URL; ?>shipments/" class="btn btn-danger">
                            <i class="fas fa-truck me-2"></i>Manage Shipments
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Quick System Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php
                            // Get quick stats
                            $stats = [];
                            
                            // Total users
                            $sql = "SELECT COUNT(*) as count FROM users";
                            $result = mysqli_query($conn, $sql);
                            if ($result) {
                                $row = mysqli_fetch_assoc($result);
                                $stats['users'] = $row['count'];
                            }
                            
                            // Total shipments
                            $sql = "SELECT COUNT(*) as count FROM shipments";
                            $result = mysqli_query($conn, $sql);
                            if ($result) {
                                $row = mysqli_fetch_assoc($result);
                                $stats['shipments'] = $row['count'];
                            }
                            
                            // Total orders
                            $sql = "SELECT COUNT(*) as count FROM orders";
                            $result = mysqli_query($conn, $sql);
                            if ($result) {
                                $row = mysqli_fetch_assoc($result);
                                $stats['orders'] = $row['count'];
                            }
                            
                            // Total products
                            $sql = "SELECT COUNT(*) as count FROM products";
                            $result = mysqli_query($conn, $sql);
                            if ($result) {
                                $row = mysqli_fetch_assoc($result);
                                $stats['products'] = $row['count'];
                            }
                            ?>
                            
                            <div class="col-md-3 mb-3">
                                <div class="text-center">
                                    <h3 class="text-primary"><?php echo $stats['users'] ?? 0; ?></h3>
                                    <p class="text-muted mb-0">Total Users</p>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="text-center">
                                    <h3 class="text-success"><?php echo $stats['shipments'] ?? 0; ?></h3>
                                    <p class="text-muted mb-0">Total Shipments</p>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="text-center">
                                    <h3 class="text-info"><?php echo $stats['orders'] ?? 0; ?></h3>
                                    <p class="text-muted mb-0">Total Orders</p>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="text-center">
                                    <h3 class="text-warning"><?php echo $stats['products'] ?? 0; ?></h3>
                                    <p class="text-muted mb-0">Total Products</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
