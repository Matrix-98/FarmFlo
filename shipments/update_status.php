<?php
require_once '../config/db.php';
require_once '../utils/inventory_helpers.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if (!in_array($_SESSION["role"], ['admin', 'logistics_manager', 'driver'])) {
    $_SESSION['error_message'] = "You do not have permission to update shipment status.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Update Shipment Status";
$current_page = "shipments";

$shipment_id = $status = $actual_departure = $actual_arrival = "";
$damage_notes = "";  // Initialize damage_notes separately to ensure it's always defined
$status_err = "";

$created_at = $updated_at = $created_by_username = $updated_by_username = '';

// Fetch shipment details for form
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $shipment_id = trim($_GET["id"]);

    $sql_fetch_status = "SELECT s.status, s.actual_departure, s.actual_arrival, s.created_at, s.updated_at, 
                               s.created_by, s.updated_by, s.order_id, s.damage_notes,
                               CONCAT(ol.name, ' → ', dl.name) as route,
                               CONCAT(u.username, ' (', u.phone, ')') as customer_info
                        FROM shipments s
                        JOIN locations ol ON s.origin_location_id = ol.location_id
                        JOIN locations dl ON s.destination_location_id = dl.location_id
                        JOIN orders o ON s.order_id = o.order_id
                        JOIN users u ON o.customer_id = u.user_id
                        WHERE s.shipment_id = ?";
    
    if ($stmt_fetch = mysqli_prepare($conn, $sql_fetch_status)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $shipment_id);
        
        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);
            if (mysqli_num_rows($result_fetch) == 1) {
                $row = mysqli_fetch_assoc($result_fetch);
                $status = $row["status"];
                $actual_departure = $row["actual_departure"] ? date('Y-m-d\TH:i', strtotime($row["actual_departure"])) : '';
                $actual_arrival = $row["actual_arrival"] ? date('Y-m-d\TH:i', strtotime($row["actual_arrival"])) : '';
                $damage_notes = isset($row["damage_notes"]) ? $row["damage_notes"] : '';
                $created_at = $row["created_at"];
                $updated_at = $row["updated_at"];
                $created_by_id = $row["created_by"];
                $updated_by_id = $row["updated_by"];
                $order_id = $row["order_id"];
                $route = $row["route"];
                $customer_info = $row["customer_info"];

                // Get usernames
                if ($created_by_id) {
                    $user_sql = "SELECT username FROM users WHERE user_id = ?";
                    if($user_stmt = mysqli_prepare($conn, $user_sql)) {
                        mysqli_stmt_bind_param($user_stmt, "i", $created_by_id);
                        mysqli_stmt_execute($user_stmt);
                        $user_result = mysqli_stmt_get_result($user_stmt);
                        if($user_row = mysqli_fetch_assoc($user_result)) $created_by_username = $user_row['username'];
                        mysqli_stmt_close($user_stmt);
                    }
                }
                if ($updated_by_id) {
                    $user_sql = "SELECT username FROM users WHERE user_id = ?";
                    if($user_stmt = mysqli_prepare($conn, $user_sql)) {
                        mysqli_stmt_bind_param($user_stmt, "i", $updated_by_id);
                        mysqli_stmt_execute($user_stmt);
                        $user_result = mysqli_stmt_get_result($user_stmt);
                        if($user_row = mysqli_fetch_assoc($user_result)) $updated_by_username = $user_row['username'];
                        mysqli_stmt_close($user_stmt);
                    }
                }

            } else {
                $_SESSION['error_message'] = "Shipment not found.";
                header("location: " . BASE_URL . "shipments/index.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "Oops! Something went wrong fetching status.";
            error_log("Error executing shipment status fetch: " . mysqli_error($conn));
            header("location: " . BASE_URL . "shipments/index.php");
            exit();
        }
        mysqli_stmt_close($stmt_fetch);
    } else {
        $_SESSION['error_message'] = "Error preparing shipment status fetch statement.";
        error_log("Error preparing shipment status fetch statement: " . mysqli_error($conn));
        header("location: " . BASE_URL . "shipments/index.php");
        exit();
    }
} else if ($_SERVER["REQUEST_METHOD"] != "POST") {
    $_SESSION['error_message'] = "Invalid request. No shipment ID provided.";
    header("location: " . BASE_URL . "shipments/index.php");
    exit;
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $shipment_id = $_POST["shipment_id"];
    $old_status = $_POST["old_status"];
    $new_status = trim($_POST["status"]);

    if (empty($new_status)) {
        $status_err = "Please select a status.";
    }

    $actual_departure = !empty(trim($_POST["actual_departure"])) ? trim($_POST["actual_departure"]) : NULL;
    $actual_arrival = !empty(trim($_POST["actual_arrival"])) ? trim($_POST["actual_arrival"]) : NULL;
    $damage_notes = !empty(trim($_POST["damage_notes"])) ? trim($_POST["damage_notes"]) : '';
    
    // Handle photo upload for failed shipments
    $failure_photo = null;
    if ($new_status === 'failed') {
        if (!isset($_FILES['failure_photo']) || $_FILES['failure_photo']['error'] !== UPLOAD_ERR_OK) {
            $status_err = "Photo upload is required when marking shipment as failed.";
        } else {
            $uploaded_file = $_FILES['failure_photo'];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($uploaded_file['type'], $allowed_types)) {
                $status_err = "Please upload a valid image file (JPG, PNG, GIF).";
            }
            // Validate file size (5MB max)
            elseif ($uploaded_file['size'] > 5 * 1024 * 1024) {
                $status_err = "File size must be less than 5MB.";
            }
            else {
                // Generate unique filename
                $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
                $filename = 'shipment_failure_' . $shipment_id . '_' . time() . '.' . $file_extension;
                $upload_path = '../uploads/photos/' . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($uploaded_file['tmp_name'], $upload_path)) {
                    $failure_photo = 'uploads/photos/' . $filename;
                } else {
                    $status_err = "Failed to upload photo. Please try again.";
                }
            }
        }
    }

    if (empty($status_err)) {
        $logged_in_user_id = $_SESSION['user_id'];

        try {
            // Use the centralized updateShipmentStatus function
            updateShipmentStatus($shipment_id, $new_status, $logged_in_user_id, $actual_departure, $actual_arrival, $damage_notes, $failure_photo);
            
            $_SESSION['success_message'] = "Shipment status updated successfully to: " . ucfirst(str_replace('_', ' ', $new_status));
            header("location: " . BASE_URL . "shipments/view.php?id=" . $shipment_id);
            exit();

        } catch (Exception $e) {
            $_SESSION['error_message'] = "Transaction failed: " . $e->getMessage();
            error_log("Shipment status update failed: " . $e->getMessage());
        }
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
            <div>
                <h2><i class="fas fa-truck me-2"></i>Update Shipment Status</h2>
                <p class="text-muted mb-0">Update shipment status and manage delivery flow</p>
            </div>
            <a href="<?php echo BASE_URL; ?>shipments/" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Shipments
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

        <!-- Shipment Info Card -->
        <?php if (isset($route) && isset($customer_info)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h6 class="text-muted mb-1">Route</h6>
                                <p class="mb-0 fw-bold"><?php echo htmlspecialchars($route); ?></p>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-muted mb-1">Customer</h6>
                                <p class="mb-0 fw-bold"><?php echo htmlspecialchars($customer_info); ?></p>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-muted mb-1">Current Status</h6>
                                <span class="badge bg-<?php echo $status == 'delivered' ? 'success' : ($status == 'failed' ? 'danger' : 'warning'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Status Update Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Update Status</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="shipment_id" value="<?php echo htmlspecialchars($shipment_id); ?>">
                            <input type="hidden" name="old_status" value="<?php echo htmlspecialchars($status); ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">New Status</label>
                                    <select class="form-select <?php echo (!empty($status_err)) ? 'is-invalid' : ''; ?>" 
                                            id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="pending" <?php echo ($status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="assigned" <?php echo ($status == 'assigned') ? 'selected' : ''; ?>>Assigned</option>
                                        <option value="in_transit" <?php echo ($status == 'in_transit') ? 'selected' : ''; ?>>In Transit</option>
                                        <option value="out_for_delivery" <?php echo ($status == 'out_for_delivery') ? 'selected' : ''; ?>>Out for Delivery</option>
                                        <option value="delivered" <?php echo ($status == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="failed" <?php echo ($status == 'failed') ? 'selected' : ''; ?>>Failed</option>
                                    </select>
                                    <?php if (!empty($status_err)): ?>
                                        <div class="invalid-feedback"><?php echo $status_err; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="actual_departure" class="form-label">Actual Departure</label>
                                    <input type="datetime-local" class="form-control" id="actual_departure" 
                                           name="actual_departure" value="<?php echo htmlspecialchars($actual_departure); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="actual_arrival" class="form-label">Actual Arrival</label>
                                    <input type="datetime-local" class="form-control" id="actual_arrival" 
                                           name="actual_arrival" value="<?php echo htmlspecialchars($actual_arrival); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="damage_notes" class="form-label">Damage Notes (if failed)</label>
                                    <textarea class="form-control" id="damage_notes" name="damage_notes" rows="3" 
                                              placeholder="Enter damage details if shipment failed..."><?php echo htmlspecialchars($damage_notes); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Photo Upload Section - Only show when status is failed -->
                            <div class="row" id="photoUploadSection" style="display: none;">
                                <div class="col-12 mb-3">
                                    <label for="failure_photo" class="form-label">Failure Photo <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="failure_photo" name="failure_photo" 
                                           accept="image/*" onchange="previewImage(this)">
                                    <div class="form-text">Upload a photo documenting the failure reason (JPG, PNG, GIF - Max 5MB)</div>
                                    <div id="imagePreview" class="mt-2" style="display: none;">
                                        <img id="previewImg" src="" alt="Preview" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;">
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Dynamic Inventory System:</strong> 
                                <ul class="mb-0 mt-2">
                                    <li><strong>Pending</strong> → <strong>Assigned</strong> → <strong>In Transit</strong> → <strong>Out for Delivery</strong> → <strong>Delivered</strong></li>
                                    <li>Or <strong>Failed</strong> at any stage</li>
                                    <li>Inventory stages automatically update: <code>available</code> → <code>reserved</code> → <code>in-transit</code> → <code>sold</code>/<code>lost</code></li>
                                    <li>Total sold and total loss counts are dynamically calculated based on status changes</li>
                                </ul>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Status
                                </button>
                                <a href="<?php echo BASE_URL; ?>shipments/view.php?id=<?php echo $shipment_id; ?>" 
                                   class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Shipment Details -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Shipment Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6 class="text-muted mb-1">Shipment ID</h6>
                            <p class="mb-0 fw-bold">#<?php echo htmlspecialchars($shipment_id); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="text-muted mb-1">Created</h6>
                            <p class="mb-0"><?php echo date('M d, Y g:i A', strtotime($created_at)); ?></p>
                            <?php if ($created_by_username): ?>
                                <small class="text-muted">by <?php echo htmlspecialchars($created_by_username); ?></small>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($updated_at): ?>
                        <div class="mb-3">
                            <h6 class="text-muted mb-1">Last Updated</h6>
                            <p class="mb-0"><?php echo date('M d, Y g:i A', strtotime($updated_at)); ?></p>
                            <?php if ($updated_by_username): ?>
                                <small class="text-muted">by <?php echo htmlspecialchars($updated_by_username); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-fill departure/arrival times based on status and show/hide photo upload
document.addEventListener('DOMContentLoaded', function() {
    // Check initial status and show photo upload if needed
    const initialStatus = document.getElementById('status').value;
    const photoSection = document.getElementById('photoUploadSection');
    const photoInput = document.getElementById('failure_photo');
    
    if (initialStatus === 'failed') {
        photoSection.style.display = 'block';
        photoInput.required = true;
    }
});

document.getElementById('status').addEventListener('change', function() {
    const status = this.value;
    const departureField = document.getElementById('actual_departure');
    const arrivalField = document.getElementById('actual_arrival');
    const photoSection = document.getElementById('photoUploadSection');
    const photoInput = document.getElementById('failure_photo');
    
    // Show/hide photo upload section
    if (status === 'failed') {
        photoSection.style.display = 'block';
        photoInput.required = true;
    } else {
        photoSection.style.display = 'none';
        photoInput.required = false;
        // Clear the file input when status changes
        photoInput.value = '';
        document.getElementById('imagePreview').style.display = 'none';
    }
    
    if (status === 'in_transit' && !departureField.value) {
        departureField.value = new Date().toISOString().slice(0, 16);
    } else if (status === 'delivered' && !arrivalField.value) {
        arrivalField.value = new Date().toISOString().slice(0, 16);
    }
});

// Image preview function
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB');
            input.value = '';
            preview.style.display = 'none';
            return;
        }
        
        // Validate file type
        if (!file.type.match('image.*')) {
            alert('Please select an image file');
            input.value = '';
            preview.style.display = 'none';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
}

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const status = document.getElementById('status').value;
    const photoInput = document.getElementById('failure_photo');
    
    if (status === 'failed' && (!photoInput.files || photoInput.files.length === 0)) {
        e.preventDefault();
        alert('Please upload a photo when marking shipment as failed');
        photoInput.focus();
        return false;
    }
});
</script>

<?php include '../includes/footer.php'; ?>