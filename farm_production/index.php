<?php
require_once '../config/db.php';
require_once '../utils/id_generator.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check user role for access control
if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'farm_manager') {
    $_SESSION['error_message'] = "You do not have permission to access Farm Production Management.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Farm Production Management";
$current_page = "farm_production";

$user_role = $_SESSION['role'];
$logged_in_user_id = $_SESSION['user_id'];

// Get farm production data with related information
$productions = [];
$sql = "SELECT fp.production_id, fp.production_code, fp.seed_amount_kg, fp.sowing_date, fp.field_name, 
               fp.expected_harvest_date, fp.actual_harvest_date, fp.harvested_amount_kg, 
               fp.status, fp.notes, fp.created_at, fp.updated_at,
               p.name as product_name, p.item_type as crop_type,
               u.username as farm_manager_name
        FROM farm_production fp
        JOIN products p ON fp.product_id = p.product_id
        JOIN users u ON fp.farm_manager_id = u.user_id";

// Filter by farm manager if user is a farm manager
if ($user_role == 'farm_manager') {
    $sql .= " WHERE fp.farm_manager_id = ?";
}

$sql .= " ORDER BY fp.created_at DESC";

if ($user_role == 'farm_manager') {
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $logged_in_user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $productions[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
} else {
    if ($result = mysqli_query($conn, $sql)) {
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $productions[] = $row;
            }
        }
        mysqli_free_result($result);
    } else {
        error_log("Farm production list query failed: " . mysqli_error($conn));
        echo '<div class="alert alert-danger">ERROR: Could not retrieve farm production list. Please try again later.</div>';
    }
}

// Get production statistics
$total_productions = count($productions);
$active_productions = 0;
$total_harvested = 0;
$upcoming_harvests = 0;

foreach ($productions as $production) {
    if (in_array($production['status'], ['planted', 'growing', 'ready_for_harvest'])) {
        $active_productions++;
    }
    if ($production['harvested_amount_kg']) {
        $total_harvested += $production['harvested_amount_kg'];
    }
    if ($production['expected_harvest_date'] && $production['expected_harvest_date'] >= date('Y-m-d')) {
        $upcoming_harvests++;
    }
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
                <h2>Farm Production Management</h2>
                <a href="<?php echo BASE_URL; ?>farm_production/create.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add New Production
                </a>
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

            <!-- Production Statistics -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="dashboard-card primary">
                        <div class="d-flex align-items-center">
                            <div class="card-icon primary me-3">
                                <i class="fas fa-seedling"></i>
                            </div>
                            <div>
                                <div class="card-title">Total Productions</div>
                                <div class="card-value"><?php echo $total_productions; ?></div>
                                <div class="card-change">All time</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="dashboard-card success">
                        <div class="d-flex align-items-center">
                            <div class="card-icon success me-3">
                                <i class="fas fa-leaf"></i>
                            </div>
                            <div>
                                <div class="card-title">Active Productions</div>
                                <div class="card-value"><?php echo $active_productions; ?></div>
                                <div class="card-change">Currently growing</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="dashboard-card warning">
                        <div class="d-flex align-items-center">
                            <div class="card-icon warning me-3">
                                <i class="fas fa-wheat-awn"></i>
                            </div>
                            <div>
                                <div class="card-title">Total Harvested</div>
                                <div class="card-value"><?php echo number_format($total_harvested, 1); ?> kg</div>
                                <div class="card-change">All time</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="dashboard-card info">
                        <div class="d-flex align-items-center">
                            <div class="card-icon info me-3">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <div class="card-title">Upcoming Harvests</div>
                                <div class="card-value"><?php echo $upcoming_harvests; ?></div>
                                <div class="card-change">Next 30 days</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Productions List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Farm Productions</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($productions)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                <tr>
                                    <th>Production Code</th>
                                    <th>Product</th>
                                    <th>Field Name</th>
                                    <th>Seed Amount</th>
                                    <th>Sowing Date</th>
                                    <th>Expected Harvest</th>
                                    <th>Status</th>
                                    <th>Harvested Amount</th>
                                    <th>Farm Manager</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($productions as $production): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($production['production_code']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($production['product_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($production['crop_type']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($production['field_name']); ?></td>
                                        <td><?php echo number_format($production['seed_amount_kg'], 1); ?> kg</td>
                                        <td><?php echo date('M d, Y', strtotime($production['sowing_date'])); ?></td>
                                        <td>
                                            <?php if ($production['expected_harvest_date']): ?>
                                                <?php echo date('M d, Y', strtotime($production['expected_harvest_date'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch ($production['status']) {
                                                case 'planted': $status_class = 'bg-primary'; break;
                                                case 'growing': $status_class = 'bg-success'; break;
                                                case 'ready_for_harvest': $status_class = 'bg-warning'; break;
                                                case 'harvested': $status_class = 'bg-info'; break;
                                                case 'completed': $status_class = 'bg-secondary'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $production['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($production['harvested_amount_kg']): ?>
                                                <?php echo number_format($production['harvested_amount_kg'], 1); ?> kg
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($production['farm_manager_name']); ?></td>
                                        <td>
                                            <a href="view.php?id=<?php echo $production['production_id']; ?>" class="btn btn-sm btn-info me-1" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $production['production_id']; ?>" class="btn btn-sm btn-warning me-1" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete.php?id=<?php echo $production['production_id']; ?>" class="btn btn-sm btn-danger" title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this production record?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-seedling fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No farm productions found</h5>
                            <p class="text-muted">Start by adding your first farm production record.</p>
                            <a href="create.php" class="btn btn-success">
                                <i class="fas fa-plus me-2"></i>Add First Production
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php include '../includes/footer.php'; ?>