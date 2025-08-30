<?php
require_once '../config/db.php';
require_once '../utils/inventory_helpers.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check if user is admin or has appropriate permissions
if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to access Shipment Status Management.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Shipment Status Management";
$current_page = "shipments";

$user_role = $_SESSION['role'];
$logged_in_user_id = $_SESSION['user_id'];

// Get all active shipments (admin sees all, drivers see only assigned)
$shipments = [];
$sql = "SELECT 
            s.shipment_id,
            s.order_id,
            s.status,
            s.created_at,
            s.estimated_delivery,
            s.actual_departure,
            s.actual_arrival,
            o.shipping_address,
            c.name as customer_name,
            c.phone as customer_phone,
            v.vehicle_number,
            v.vehicle_type,
            d.first_name as driver_first_name,
            d.last_name as driver_last_name,
            d.phone as driver_phone,
            COUNT(sp.product_id) as product_count,
            SUM(sp.quantity_kg) as total_quantity
        FROM shipments s
        LEFT JOIN orders o ON s.order_id = o.order_id
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
        LEFT JOIN drivers d ON s.driver_id = d.driver_id
        LEFT JOIN shipment_products sp ON s.shipment_id = sp.shipment_id
        WHERE s.status IN ('pending', 'in_transit', 'out_for_delivery')
        GROUP BY s.shipment_id, s.order_id, s.status, s.created_at, s.estimated_delivery, 
                 s.actual_departure, s.actual_arrival, o.shipping_address, c.name, c.phone, 
                 v.vehicle_number, v.vehicle_type, d.first_name, d.last_name, d.phone
        ORDER BY s.estimated_delivery ASC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $shipments[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Process status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $shipment_id = intval($_POST['shipment_id']);
    $new_status = $_POST['new_status'];
    $damage_notes = trim($_POST['damage_notes'] ?? '');
    $tracking_notes = trim($_POST['tracking_notes'] ?? '');
    
    // Validate status
    $valid_statuses = ['delivered', 'damaged', 'cancelled', 'in_transit', 'out_for_delivery'];
    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['error_message'] = "Invalid status selected.";
    } else {
        // Update shipment status
        $result = updateShipmentStatus($shipment_id, $new_status, $_SESSION['user_id'], NULL, NULL, $damage_notes);
        
        if ($result) {
            // Add tracking notes if provided
            if (!empty($tracking_notes)) {
                $sql_notes = "UPDATE shipments SET notes = CONCAT(COALESCE(notes, ''), '\nTracking Update: ', ?) WHERE shipment_id = ?";
                $notes_stmt = mysqli_prepare($conn, $sql_notes);
                mysqli_stmt_bind_param($notes_stmt, "si", $tracking_notes, $shipment_id);
                mysqli_stmt_execute($notes_stmt);
                mysqli_stmt_close($notes_stmt);
            }
            
            $_SESSION['success_message'] = "Shipment status updated successfully to: " . ucfirst(str_replace('_', ' ', $new_status));
        } else {
            $_SESSION['error_message'] = "Failed to update shipment status. Please try again.";
        }
        
        // Redirect to refresh the page
        header("location: " . BASE_URL . "shipments/shipment_status.php");
        exit;
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
            <h2><i class="fas fa-truck me-2"></i>Shipment Status Management</h2>
            <div class="d-flex gap-2">
                <button onclick="refreshShipments()" class="btn btn-outline-primary">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
                <a href="<?php echo BASE_URL; ?>shipments/" class="btn btn-outline-secondary">
                    <i class="fas fa-list me-1"></i>All Shipments
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

        <!-- Admin Info Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="card-title mb-1">
                                    <i class="fas fa-user-shield me-2"></i>Admin Dashboard
                                </h5>
                                <p class="card-text mb-0">
                                    Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! 
                                    You have <?php echo count($shipments); ?> active shipments to monitor.
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <div class="d-flex flex-column">
                                    <span class="h4 mb-0"><?php echo count($shipments); ?></span>
                                    <small>Active Shipments</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shipments List -->
        <?php if (!empty($shipments)): ?>
        <div class="row">
            <?php foreach ($shipments as $shipment): ?>
            <div class="col-lg-6 col-xl-4 mb-4">
                <div class="card h-100 border-<?php 
                    switch ($shipment['status']) {
                        case 'pending': echo 'warning'; break;
                        case 'in_transit': echo 'info'; break;
                        case 'out_for_delivery': echo 'primary'; break;
                        default: echo 'secondary';
                    }
                ?>">
                    <div class="card-header bg-<?php 
                        switch ($shipment['status']) {
                            case 'pending': echo 'warning'; break;
                            case 'in_transit': echo 'info'; break;
                            case 'out_for_delivery': echo 'primary'; break;
                            default: echo 'secondary';
                        }
                    ?> text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-shipping-fast me-2"></i>Shipment #<?php echo $shipment['shipment_id']; ?>
                            </h6>
                            <span class="badge bg-light text-dark">
                                <?php echo ucwords(str_replace('_', ' ', $shipment['status'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Customer Info -->
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">
                                <?php echo $shipment['customer_name'] ? 'Customer Details' : 'Shipment Details'; ?>
                            </h6>
                            <?php if ($shipment['customer_name']): ?>
                            <!-- Order-based shipment -->
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-user text-primary me-2"></i>
                                <span class="fw-semibold"><?php echo htmlspecialchars($shipment['customer_name']); ?></span>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-phone text-success me-2"></i>
                                <span><?php echo htmlspecialchars($shipment['customer_phone']); ?></span>
                            </div>
                            <div class="d-flex align-items-start">
                                <i class="fas fa-map-marker-alt text-danger me-2 mt-1"></i>
                                <small><?php echo htmlspecialchars($shipment['shipping_address']); ?></small>
                            </div>
                            <?php else: ?>
                            <!-- Request-based shipment -->
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-truck-loading text-info me-2"></i>
                                <span class="fw-semibold">Farm Production Shipment</span>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-warehouse text-warning me-2"></i>
                                <span>Farm to Warehouse</span>
                            </div>
                            <div class="d-flex align-items-start">
                                <i class="fas fa-info-circle text-secondary me-2 mt-1"></i>
                                <small>Created from shipment request</small>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Driver Info -->
                        <?php if ($shipment['driver_first_name']): ?>
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">Driver Details</h6>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-user-tie text-info me-2"></i>
                                <span class="fw-semibold"><?php echo htmlspecialchars($shipment['driver_first_name'] . ' ' . $shipment['driver_last_name']); ?></span>
                            </div>
                            <?php if ($shipment['driver_phone']): ?>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-phone text-success me-2"></i>
                                <span><?php echo htmlspecialchars($shipment['driver_phone']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="mb-3">
                            <div class="alert alert-warning py-2">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <small>No driver assigned</small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Shipment Details -->
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">Shipment Details</h6>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">Products:</small><br>
                                    <span class="fw-semibold"><?php echo $shipment['product_count']; ?> items</span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Quantity:</small><br>
                                    <span class="fw-semibold"><?php echo number_format($shipment['total_quantity'], 2); ?> kg</span>
                                </div>
                            </div>
                        </div>

                        <!-- Vehicle Info -->
                        <?php if ($shipment['vehicle_number']): ?>
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">Vehicle</h6>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-truck text-secondary me-2"></i>
                                <span><?php echo htmlspecialchars($shipment['vehicle_number']); ?></span>
                                <small class="text-muted ms-2">(<?php echo htmlspecialchars($shipment['vehicle_type']); ?>)</small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Delivery Info -->
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">Delivery Timeline</h6>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">Created:</small><br>
                                    <span><?php echo date('M d, Y', strtotime($shipment['created_at'])); ?></span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Estimated:</small><br>
                                    <span><?php echo date('M d, Y', strtotime($shipment['estimated_delivery'])); ?></span>
                                </div>
                            </div>
                            <?php if ($shipment['actual_departure']): ?>
                            <div class="row mt-2">
                                <div class="col-6">
                                    <small class="text-muted">Departure:</small><br>
                                    <span><?php echo date('M d, Y H:i', strtotime($shipment['actual_departure'])); ?></span>
                                </div>
                                <?php if ($shipment['actual_arrival']): ?>
                                <div class="col-6">
                                    <small class="text-muted">Arrival:</small><br>
                                    <span><?php echo date('M d, Y H:i', strtotime($shipment['actual_arrival'])); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Status Update Form -->
                        <div class="mt-3">
                            <button class="btn btn-primary w-100 mb-2" 
                                    onclick="showStatusUpdateModal(<?php echo $shipment['shipment_id']; ?>, '<?php echo htmlspecialchars($shipment['customer_name']); ?>', '<?php echo $shipment['status']; ?>')">
                                <i class="fas fa-edit me-2"></i>Update Status
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-truck text-muted" style="font-size: 4rem;"></i>
            <h4 class="text-muted mt-3">No Active Shipments</h4>
            <p class="text-muted">There are no active shipments at the moment.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusUpdateModal" tabindex="-1" aria-labelledby="statusUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusUpdateModalLabel">Update Shipment Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="shipment_id" id="modalShipmentId">
                    <input type="hidden" name="update_status" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_status" class="form-label">New Status</label>
                                <select class="form-select" id="new_status" name="new_status" required>
                                    <option value="">Select Status</option>
                                    <option value="in_transit">In Transit</option>
                                    <option value="out_for_delivery">Out for Delivery</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tracking_notes" class="form-label">Tracking Notes</label>
                                <textarea class="form-control" id="tracking_notes" name="tracking_notes" rows="3" 
                                          placeholder="Add tracking update notes..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="damageNotesDiv" style="display: none;">
                        <label for="damage_notes" class="form-label">Damage/Cancellation Notes</label>
                        <textarea class="form-control" id="damage_notes" name="damage_notes" rows="3" 
                                  placeholder="Please describe the damage or reason for cancellation..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> 
                        <ul class="mb-0 mt-2">
                            <li><strong>Delivered:</strong> Will mark inventory as sold and update order status</li>
                            <li><strong>Damaged/Cancelled:</strong> Will mark inventory as lost and update order status</li>
                            <li><strong>In Transit/Out for Delivery:</strong> Will update shipment tracking</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showStatusUpdateModal(shipmentId, customerName, currentStatus) {
    document.getElementById('modalShipmentId').value = shipmentId;
    document.getElementById('statusUpdateModalLabel').innerHTML = `Update Status - ${customerName}`;
    
    // Set current status as selected
    const statusSelect = document.getElementById('new_status');
    statusSelect.value = currentStatus;
    
    // Show/hide damage notes based on status selection
    statusSelect.addEventListener('change', function() {
        const damageNotesDiv = document.getElementById('damageNotesDiv');
        const damageNotes = document.getElementById('damage_notes');
        
        if (this.value === 'damaged' || this.value === 'cancelled') {
            damageNotesDiv.style.display = 'block';
            damageNotes.required = true;
        } else {
            damageNotesDiv.style.display = 'none';
            damageNotes.required = false;
        }
    });
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('statusUpdateModal'));
    modal.show();
}

function refreshShipments() {
    // Show loading state
    const refreshBtn = event.target;
    const originalText = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
    refreshBtn.disabled = true;
    
    // Reload the page after a short delay
    setTimeout(() => {
        window.location.reload();
    }, 500);
}

// Auto-refresh shipments every 2 minutes
setInterval(() => {
    if (!document.hidden) {
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                // Update only the shipments list
                const parser = new DOMParser();
                const newDoc = parser.parseFromString(html, 'text/html');
                
                const newShipments = newDoc.querySelector('.row');
                const currentShipments = document.querySelector('.row');
                if (newShipments && currentShipments) {
                    currentShipments.innerHTML = newShipments.innerHTML;
                }
            })
            .catch(error => {
                console.log('Auto-refresh failed:', error);
            });
    }
}, 120000); // 2 minutes
</script>

<?php include '../includes/footer.php'; ?>
