<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Only admin and logistics_manager can access requested shipments
if (!in_array($_SESSION["role"], ['admin', 'logistics_manager'])) {
    $_SESSION['error_message'] = "You do not have permission to access Requested Shipments.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Requested Shipments";
$current_page = "shipments";

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $action = $_POST['action'];
    
    if ($request_id > 0 && in_array($action, ['approve', 'reject', 'delete'])) {
        if ($action == 'approve') {
            // Redirect to shipment creation page with request data
            $_SESSION['shipment_request_data'] = $request_id;
            header("location: " . BASE_URL . "shipments/create.php?from_request=" . $request_id);
            exit;
        } elseif ($action == 'reject') {
            // Just update status to rejected
            $sql_update = "UPDATE shipment_requests SET status = 'rejected' WHERE request_id = ?";
            if ($stmt = mysqli_prepare($conn, $sql_update)) {
                mysqli_stmt_bind_param($stmt, "i", $request_id);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "Shipment request rejected successfully.";
                } else {
                    $_SESSION['error_message'] = "Failed to reject request.";
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($action == 'delete') {
            // Delete the shipment request
            $sql_delete = "DELETE FROM shipment_requests WHERE request_id = ?";
            if ($stmt = mysqli_prepare($conn, $sql_delete)) {
                mysqli_stmt_bind_param($stmt, "i", $request_id);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "Shipment request deleted successfully.";
                } else {
                    $_SESSION['error_message'] = "Failed to delete request.";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    header("location: " . BASE_URL . "shipments/requested.php");
    exit;
}

// Function to create shipment from request
function createShipmentFromRequest($request_id) {
    global $conn;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Get request details
        $sql_request = "SELECT sr.*, fp.production_code, fp.field_name, p.name as product_name,
                               u.username as farm_manager_name, u.phone as farm_manager_phone
                        FROM shipment_requests sr
                        JOIN farm_production fp ON sr.production_id = fp.production_id
                        JOIN products p ON sr.product_id = p.product_id
                        JOIN users u ON sr.farm_manager_id = u.user_id
                        WHERE sr.request_id = ? AND sr.status = 'pending'";
        
        if ($stmt = mysqli_prepare($conn, $sql_request)) {
            mysqli_stmt_bind_param($stmt, "i", $request_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $request = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            if (!$request) {
                throw new Exception("Request not found or not eligible for approval.");
            }
            
            // Get farm location (origin) - we'll use the first farm location or create a default
            $sql_farm_location = "SELECT location_id FROM locations WHERE type = 'farm' LIMIT 1";
            $farm_location_result = mysqli_query($conn, $sql_farm_location);
            $farm_location = mysqli_fetch_assoc($farm_location_result);
            
            if (!$farm_location) {
                throw new Exception("No farm location found. Please create a farm location first.");
            }
            
            // Get warehouse location (destination) - we'll use the first warehouse or create a default
            $sql_warehouse_location = "SELECT location_id FROM locations WHERE type = 'warehouse' LIMIT 1";
            $warehouse_location_result = mysqli_query($conn, $sql_warehouse_location);
            $warehouse_location = mysqli_fetch_assoc($warehouse_location_result);
            
            if (!$warehouse_location) {
                throw new Exception("No warehouse location found. Please create a warehouse location first.");
            }
            
            // Generate shipment code
            require_once '../utils/id_generator.php';
            $shipment_code = generateShipmentId();
            
            // Calculate planned dates (pickup date + 2 days for delivery)
            $pickup_date = $request['preferred_pickup_date'];
            $planned_departure = $pickup_date . ' 08:00:00';
            $planned_arrival = date('Y-m-d H:i:s', strtotime($pickup_date . ' +2 days'));
            
            // Create shipment
            $sql_shipment = "INSERT INTO shipments (shipment_code, origin_location_id, destination_location_id, 
                           total_weight_kg, total_volume_m3, planned_departure, planned_arrival, 
                           status, notes, created_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";
            
            if ($stmt = mysqli_prepare($conn, $sql_shipment)) {
                $total_volume = $request['quantity_kg'] * 0.001; // Rough estimate: 1 kg = 0.001 m³
                $notes = "Created from shipment request: " . $request['request_code'] . "\n" . $request['notes'];
                
                mysqli_stmt_bind_param($stmt, "siidssssi", $shipment_code, $farm_location['location_id'], 
                                     $warehouse_location['location_id'], $request['quantity_kg'], $total_volume,
                                     $planned_departure, $planned_arrival, $notes, $_SESSION['user_id']);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to create shipment.");
                }
                
                $shipment_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                
                // Update request status to approved
                $sql_update_request = "UPDATE shipment_requests SET status = 'approved' WHERE request_id = ?";
                if ($stmt = mysqli_prepare($conn, $sql_update_request)) {
                    mysqli_stmt_bind_param($stmt, "i", $request_id);
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Failed to update request status.");
                    }
                    mysqli_stmt_close($stmt);
                }
                
                // Commit transaction
                mysqli_commit($conn);
                
                return ['success' => true, 'shipment_code' => $shipment_code];
                
            } else {
                throw new Exception("Failed to prepare shipment creation.");
            }
            
        } else {
            throw new Exception("Failed to prepare request query.");
        }
        
    } catch (Exception $e) {
        // Rollback transaction
        mysqli_rollback($conn);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Fetch shipment requests
$sql = "SELECT sr.*, fp.production_code, fp.field_name, p.name as product_name, 
               u.username as farm_manager_name, u.phone as farm_manager_phone
        FROM shipment_requests sr
        JOIN farm_production fp ON sr.production_id = fp.production_id
        JOIN products p ON sr.product_id = p.product_id
        JOIN users u ON sr.farm_manager_id = u.user_id
        ORDER BY sr.created_at DESC";

$requests = [];
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = $row;
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
            <h2><i class="fas fa-truck-loading me-2"></i>Requested Shipments</h2>
            <a href="<?php echo BASE_URL; ?>shipments/" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Shipments
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

        <!-- Shipment Requests List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Shipment Requests</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($requests)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Request Code</th>
                                <th>Production</th>
                                <th>Product</th>
                                <th>Farm Manager</th>
                                <th>Quantity</th>
                                <th>Preferred Pickup</th>
                                <th>Status</th>
                                <th>Request Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?php echo htmlspecialchars($request['request_code']); ?></span>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['production_code']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($request['field_name']); ?></small>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($request['product_name']); ?></td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['farm_manager_name']); ?></strong>
                                        <?php if ($request['farm_manager_phone']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($request['farm_manager_phone']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold"><?php echo number_format($request['quantity_kg'], 2); ?> kg</span>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($request['preferred_pickup_date'])); ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    switch ($request['status']) {
                                        case 'pending': $status_class = 'bg-warning'; break;
                                        case 'approved': $status_class = 'bg-success'; break;
                                        case 'rejected': $status_class = 'bg-danger'; break;
                                        case 'converted_to_order': $status_class = 'bg-info'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $request['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-info" 
                                                onclick="viewRequestDetails(<?php echo $request['request_id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($request['status'] == 'pending'): ?>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to approve this request?');">
                                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-outline-success" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to reject this request?');">
                                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-outline-warning" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this request? This action cannot be undone.');">
                                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-truck-loading text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No Shipment Requests Found</h5>
                    <p class="text-muted">Farm managers will appear here when they submit shipment requests.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Request Details Modal -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestDetailsModalLabel">Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="requestDetailsContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewRequestDetails(requestId) {
    // This would load request details via AJAX
    // For now, just show a placeholder
    document.getElementById('requestDetailsContent').innerHTML = 
        '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    const modal = new bootstrap.Modal(document.getElementById('requestDetailsModal'));
    modal.show();
    
    // In a real implementation, you would fetch the details via AJAX
    // and populate the modal content
}
</script>

<?php include '../includes/footer.php'; ?>
