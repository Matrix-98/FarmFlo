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

$page_title = "View Farm Production";
$current_page = "farm_production";

// Get production ID from URL
$production_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($production_id <= 0) {
    $_SESSION['error_message'] = "Invalid production ID.";
    header("location: " . BASE_URL . "farm_production/");
    exit;
}

// Get farm production details
$production = null;
$sql = "SELECT fp.*, p.name as product_name, p.item_type as crop_type, p.description as product_description,
               u.username as farm_manager_name, u.email as farm_manager_email, u.phone as farm_manager_phone
        FROM farm_production fp
        JOIN products p ON fp.product_id = p.product_id
        JOIN users u ON fp.farm_manager_id = u.user_id
        WHERE fp.production_id = ?";

// Add farm manager filter if user is a farm manager
if ($_SESSION['role'] == 'farm_manager') {
    $sql .= " AND fp.farm_manager_id = ?";
}

if ($stmt = mysqli_prepare($conn, $sql)) {
    if ($_SESSION['role'] == 'farm_manager') {
        mysqli_stmt_bind_param($stmt, "ii", $production_id, $_SESSION['user_id']);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $production_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $production = mysqli_fetch_assoc($result);
    } else {
        $_SESSION['error_message'] = "Production record not found or you don't have permission to view it.";
        header("location: " . BASE_URL . "farm_production/");
        exit;
    }
    mysqli_stmt_close($stmt);
} else {
    $_SESSION['error_message'] = "Error retrieving production details.";
    header("location: " . BASE_URL . "farm_production/");
    exit;
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
            <h2><i class="fas fa-seedling me-2"></i>Farm Production Details</h2>
            <div>
                <a href="<?php echo BASE_URL; ?>farm_production/" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i>Back to List
                </a>
                <a href="edit.php?id=<?php echo $production_id; ?>" class="btn btn-warning me-2">
                    <i class="fas fa-edit me-1"></i>Edit
                </a>
                <a href="delete.php?id=<?php echo $production_id; ?>" class="btn btn-danger"
                   onclick="return confirm('Are you sure you want to delete this production record?');">
                    <i class="fas fa-trash me-1"></i>Delete
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

        <!-- Production Details Card -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Production Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Production Code:</strong></td>
                                        <td><?php echo htmlspecialchars($production['production_code']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Product:</strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($production['product_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($production['crop_type']); ?></small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Field Name:</strong></td>
                                        <td><?php echo htmlspecialchars($production['field_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Seed Amount:</strong></td>
                                        <td><?php echo number_format($production['seed_amount_kg'], 2); ?> kg</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Sowing Date:</strong></td>
                                        <td><?php echo date('F d, Y', strtotime($production['sowing_date'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Status:</strong></td>
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
                                            <span class="badge <?php echo $status_class; ?> fs-6">
                                                <?php echo ucwords(str_replace('_', ' ', $production['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Expected Harvest:</strong></td>
                                        <td>
                                            <?php if ($production['expected_harvest_date']): ?>
                                                <?php echo date('F d, Y', strtotime($production['expected_harvest_date'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Actual Harvest:</strong></td>
                                        <td>
                                            <?php if ($production['actual_harvest_date']): ?>
                                                <?php echo date('F d, Y', strtotime($production['actual_harvest_date'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not harvested yet</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Harvested Amount:</strong></td>
                                        <td>
                                            <?php if ($production['harvested_amount_kg']): ?>
                                                <strong><?php echo number_format($production['harvested_amount_kg'], 2); ?> kg</strong>
                                            <?php else: ?>
                                                <span class="text-muted">Not harvested yet</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Created:</strong></td>
                                        <td><?php echo date('F d, Y g:i A', strtotime($production['created_at'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <?php if ($production['notes']): ?>
                            <div class="mt-4">
                                <h6><i class="fas fa-sticky-note me-2"></i>Notes</h6>
                                <div class="alert alert-light">
                                    <?php echo nl2br(htmlspecialchars($production['notes'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Farm Manager Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-user me-2"></i>Farm Manager</h6>
                    </div>
                    <div class="card-body">
                        <h6><?php echo htmlspecialchars($production['farm_manager_name']); ?></h6>
                        <p class="text-muted mb-2">
                            <i class="fas fa-envelope me-1"></i>
                            <?php echo htmlspecialchars($production['farm_manager_email']); ?>
                        </p>
                        <?php if ($production['farm_manager_phone']): ?>
                            <p class="text-muted mb-0">
                                <i class="fas fa-phone me-1"></i>
                                <?php echo htmlspecialchars($production['farm_manager_phone']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Product Information -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-box me-2"></i>Product Details</h6>
                    </div>
                    <div class="card-body">
                        <h6><?php echo htmlspecialchars($production['product_name']); ?></h6>
                        <p class="text-muted mb-2">
                            <strong>Type:</strong> <?php echo htmlspecialchars($production['crop_type']); ?>
                        </p>
                        <?php if ($production['product_description']): ?>
                            <p class="text-muted mb-0">
                                <?php echo htmlspecialchars(substr($production['product_description'], 0, 100)); ?>
                                <?php if (strlen($production['product_description']) > 100): ?>...<?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Production Timeline</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <h6>Production Created</h6>
                                    <p class="text-muted"><?php echo date('F d, Y g:i A', strtotime($production['created_at'])); ?></p>
                                </div>
                            </div>

                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6>Sowing Date</h6>
                                    <p class="text-muted"><?php echo date('F d, Y', strtotime($production['sowing_date'])); ?></p>
                                </div>
                            </div>

                            <?php if ($production['expected_harvest_date']): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-warning"></div>
                                    <div class="timeline-content">
                                        <h6>Expected Harvest</h6>
                                        <p class="text-muted"><?php echo date('F d, Y', strtotime($production['expected_harvest_date'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($production['actual_harvest_date']): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-info"></div>
                                    <div class="timeline-content">
                                        <h6>Actual Harvest</h6>
                                        <p class="text-muted"><?php echo date('F d, Y', strtotime($production['actual_harvest_date'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($production['updated_at'] != $production['created_at']): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-secondary"></div>
                                    <div class="timeline-content">
                                        <h6>Last Updated</h6>
                                        <p class="text-muted"><?php echo date('F d, Y g:i A', strtotime($production['updated_at'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 30px;
    }

    .timeline-marker {
        position: absolute;
        left: -22px;
        top: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px #e9ecef;
    }

    .timeline-content {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-left: 10px;
    }

    .timeline-content h6 {
        margin: 0 0 5px 0;
        color: #495057;
    }

    .timeline-content p {
        margin: 0;
        font-size: 0.9rem;
    }
</style>

<?php include '../includes/footer.php'; ?>
