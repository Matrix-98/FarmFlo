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
            <div>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <a href="<?php echo BASE_URL; ?>shipments/requested.php" class="btn btn-warning me-2">
                    <i class="fas fa-clipboard-list"></i> View Shipment Requests
                </a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>farm_production/create.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add New Production
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

        <!-- Debug Information (for troubleshooting) -->
        <?php if ($_SESSION['role'] == 'admin' && isset($_GET['debug'])): ?>
        <div class="alert alert-warning">
            <h6><i class="fas fa-bug me-2"></i>Debug Information:</h6>
            <p><strong>Your Role:</strong> <?php echo $_SESSION['role']; ?></p>
            <p><strong>Total Productions:</strong> <?php echo count($productions); ?></p>
            <div class="row">
                <?php foreach ($productions as $prod): ?>
                <div class="col-md-6 mb-2">
                    <strong><?php echo htmlspecialchars($prod['production_code']); ?></strong><br>
                    Status: <span class="badge bg-secondary"><?php echo $prod['status']; ?></span><br>
                    Harvested: <?php echo $prod['harvested_amount_kg'] ? number_format($prod['harvested_amount_kg'], 1) . ' kg' : 'None'; ?><br>
                    Can Request: <?php echo (($prod['status'] == 'harvested' || $prod['status'] == 'ready_for_harvest' || $prod['status'] == 'completed') && $prod['harvested_amount_kg'] > 0) ? 'YES' : 'NO'; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

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
                                            <?php if (($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'farm_manager') && 
                                                      ($production['status'] == 'harvested' || $production['status'] == 'ready_for_harvest' || $production['status'] == 'completed') && 
                                                      $production['harvested_amount_kg'] > 0): ?>
                                            <button type="button" class="btn btn-sm btn-success ms-1" title="Request for Shipment"
                                                    onclick="openShipmentRequestModal(<?php echo $production['production_id']; ?>, '<?php echo htmlspecialchars($production['product_name']); ?>', '<?php echo htmlspecialchars($production['production_code']); ?>', <?php echo $production['harvested_amount_kg']; ?>)">
                                                <i class="fas fa-truck"></i>
                                            </button>
                                            <?php elseif (($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'farm_manager') && 
                                                         ($production['status'] == 'harvested' || $production['status'] == 'ready_for_harvest' || $production['status'] == 'completed')): ?>
                                            <button type="button" class="btn btn-sm btn-secondary ms-1" title="Request for Shipment (No harvested amount)"
                                                    onclick="alert('This production has no harvested amount yet. Please update the harvested amount first.');">
                                                <i class="fas fa-truck"></i>
                                            </button>
                                            <?php endif; ?>
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

<!-- Shipment Request Modal -->
<div class="modal fade" id="shipmentRequestModal" tabindex="-1" aria-labelledby="shipmentRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shipmentRequestModalLabel">
                    <i class="fas fa-truck me-2"></i>Request for Shipment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="shipmentRequestForm" method="POST" action="request_shipment.php">
                <div class="modal-body">
                    <input type="hidden" name="production_id" id="modal_production_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_product_name" class="form-label">Product Name</label>
                                <input type="text" class="form-control" id="modal_product_name" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_production_code" class="form-label">Production Code</label>
                                <input type="text" class="form-control" id="modal_production_code" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_quantity_kg" class="form-label">Quantity Available (kg)</label>
                                <input type="number" class="form-control" id="modal_quantity_kg" name="quantity_kg" 
                                       step="0.01" required>
                                <div class="form-text" id="modal_max_quantity_text"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_preferred_pickup_date" class="form-label">Preferred Pickup Date</label>
                                <input type="date" class="form-control" id="modal_preferred_pickup_date" name="preferred_pickup_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="modal_notes" name="notes" rows="3" 
                                  placeholder="Any special instructions or notes for pickup..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> This request will be reviewed by logistics management. 
                        Once approved, it will be converted to a shipment order.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane me-1"></i>Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openShipmentRequestModal(productionId, productName, productionCode, harvestedAmount) {
    // Set form values
    document.getElementById('modal_production_id').value = productionId;
    document.getElementById('modal_product_name').value = productName;
    document.getElementById('modal_production_code').value = productionCode;
    document.getElementById('modal_quantity_kg').value = harvestedAmount;
    document.getElementById('modal_quantity_kg').max = harvestedAmount;
    document.getElementById('modal_max_quantity_text').textContent = 'Maximum available: ' + parseFloat(harvestedAmount).toFixed(2) + ' kg';
    
    // Set default pickup date to tomorrow
    document.getElementById('modal_preferred_pickup_date').value = '<?php echo date('Y-m-d', strtotime('+1 day')); ?>';
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('shipmentRequestModal'));
    modal.show();
}

// Form validation
document.getElementById('shipmentRequestForm').addEventListener('submit', function(e) {
    const quantity = parseFloat(document.getElementById('modal_quantity_kg').value);
    const maxQuantity = parseFloat(document.getElementById('modal_quantity_kg').max);
    
    if (quantity <= 0) {
        e.preventDefault();
        alert('Quantity must be greater than 0.');
        return false;
    }
    
    if (quantity > maxQuantity) {
        e.preventDefault();
        alert('Quantity cannot exceed the available harvested amount.');
        return false;
    }
    
    const pickupDate = document.getElementById('modal_preferred_pickup_date').value;
    const today = new Date().toISOString().split('T')[0];
    
    if (pickupDate < today) {
        e.preventDefault();
        alert('Pickup date cannot be in the past.');
        return false;
    }
});
</script>

<?php include '../includes/footer.php'; ?>