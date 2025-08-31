<?php
require_once '../config/db.php';
require_once '../utils/inventory_helpers.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check if user has permission to access tracking entry
if (!in_array($_SESSION["role"], ['driver', 'admin', 'logistics_manager'])) {
    $_SESSION['error_message'] = "You do not have permission to access Tracking Interface.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Tracking Data Entry";
$current_page = $_SESSION["role"] == 'driver' ? "driver" : "shipments";

$user_id = $_SESSION['user_id'];

// Check if shipment_id is provided in URL
$pre_selected_shipment_id = null;
if (isset($_GET['shipment_id']) && !empty($_GET['shipment_id'])) {
    $pre_selected_shipment_id = intval($_GET['shipment_id']);
}

// Get shipments based on user role
$shipments = [];
if ($_SESSION["role"] == 'driver') {
    // For drivers, show only their assigned shipments
    $sql = "SELECT 
                s.shipment_id,
                s.order_id,
                s.status,
                s.created_at,
                s.planned_arrival as estimated_delivery,
                o.shipping_address,
                u.username as customer_name,
                u.phone as customer_phone,
                v.license_plate as vehicle_number,
                v.type as vehicle_type,
                COUNT(sp.product_id) as product_count,
                SUM(sp.quantity_kg) as total_quantity
            FROM shipments s
            LEFT JOIN orders o ON s.order_id = o.order_id
            LEFT JOIN users u ON o.customer_id = u.user_id
            LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
            LEFT JOIN shipment_products sp ON s.shipment_id = sp.shipment_id
            JOIN drivers d ON s.driver_id = d.driver_id
            WHERE d.user_id = ? AND s.status IN ('pending', 'assigned', 'in_transit', 'out_for_delivery')
            GROUP BY s.shipment_id, s.order_id, s.status, s.created_at, s.planned_arrival, 
                     o.shipping_address, u.username, u.phone, v.license_plate, v.type
            ORDER BY s.planned_arrival ASC";
} else {
    // For admin and logistics_manager, show all active shipments
    $sql = "SELECT 
                s.shipment_id,
                s.order_id,
                s.status,
                s.created_at,
                s.planned_arrival as estimated_delivery,
                o.shipping_address,
                u.username as customer_name,
                u.phone as customer_phone,
                v.license_plate as vehicle_number,
                v.type as vehicle_type,
                CONCAT(d.first_name, ' ', d.last_name) as driver_name,
                COUNT(sp.product_id) as product_count,
                SUM(sp.quantity_kg) as total_quantity
            FROM shipments s
            LEFT JOIN orders o ON s.order_id = o.order_id
            LEFT JOIN users u ON o.customer_id = u.user_id
            LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
            LEFT JOIN shipment_products sp ON s.shipment_id = sp.shipment_id
            LEFT JOIN drivers d ON s.driver_id = d.driver_id
            WHERE s.status IN ('pending', 'assigned', 'in_transit', 'out_for_delivery')
            GROUP BY s.shipment_id, s.order_id, s.status, s.created_at, s.planned_arrival, 
                     o.shipping_address, u.username, u.phone, v.license_plate, v.type, d.first_name, d.last_name
            ORDER BY s.planned_arrival ASC";
}

if ($stmt = mysqli_prepare($conn, $sql)) {
    if ($_SESSION["role"] == 'driver') {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $shipments[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Process tracking data entry
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_tracking'])) {
    $shipment_id = intval($_POST['shipment_id']);
    $latitude_raw = trim($_POST['latitude']);
    $longitude_raw = trim($_POST['longitude']);
    $temperature = floatval($_POST['temperature']);
    $humidity = floatval($_POST['humidity']);
    $delivery_status = $_POST['delivery_status'];
    $order_notes = trim($_POST['order_notes'] ?? '');
    $current_time = date('Y-m-d H:i:s');
    
    // Validate data
    $errors = [];
    if ($latitude_raw === '' || !is_numeric($latitude_raw)) {
        $errors[] = "Please enter a valid latitude.";
    }
    if ($longitude_raw === '' || !is_numeric($longitude_raw)) {
        $errors[] = "Please enter a valid longitude.";
    }
    // Coerce to float after numeric validation
    if (empty($errors)) {
        $latitude = (float)$latitude_raw;
        $longitude = (float)$longitude_raw;
        if ($latitude < -90 || $latitude > 90) $errors[] = "Invalid latitude value";
        if ($longitude < -180 || $longitude > 180) $errors[] = "Invalid longitude value";
    }
    if ($temperature < -50 || $temperature > 80) $errors[] = "Invalid temperature value";
    if ($humidity < 0 || $humidity > 100) $errors[] = "Invalid humidity value";
    
    if (empty($errors)) {
        // Insert tracking data
        $sql_tracking = "INSERT INTO tracking_data (shipment_id, latitude, longitude, temperature, humidity, 
                        delivery_status, order_notes, recorded_at, recorded_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt_tracking = mysqli_prepare($conn, $sql_tracking)) {
            mysqli_stmt_bind_param($stmt_tracking, "iddddsssi", 
                $shipment_id, $latitude, $longitude, $temperature, $humidity, 
                $delivery_status, $order_notes, $current_time, $user_id);
            
            if (mysqli_stmt_execute($stmt_tracking)) {
                $_SESSION['success_message'] = "Tracking data recorded successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to record tracking data: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt_tracking);
        }
        
        // Redirect to refresh the page
        header("location: " . BASE_URL . "driver/tracking_entry.php");
        exit;
    } else {
        $_SESSION['error_message'] = "Validation errors: " . implode(", ", $errors);
    }
}

// Get recent tracking data for display
$recent_tracking = [];
if ($_SESSION["role"] == 'driver') {
    $sql_recent = "SELECT td.*, s.shipment_id, u.username as customer_name 
                   FROM tracking_data td
                   JOIN shipments s ON td.shipment_id = s.shipment_id
                   LEFT JOIN orders o ON s.order_id = o.order_id
                   LEFT JOIN users u ON o.customer_id = u.user_id
                   WHERE td.recorded_by = ?
                   ORDER BY td.recorded_at DESC
                   LIMIT 10";
} else {
    $sql_recent = "SELECT td.*, s.shipment_id, u.username as customer_name, 
                          CONCAT(d.first_name, ' ', d.last_name) as driver_name
                   FROM tracking_data td
                   JOIN shipments s ON td.shipment_id = s.shipment_id
                   LEFT JOIN orders o ON s.order_id = o.order_id
                   LEFT JOIN users u ON o.customer_id = u.user_id
                   LEFT JOIN drivers d ON s.driver_id = d.driver_id
                   ORDER BY td.recorded_at DESC
                   LIMIT 10";
}

if ($stmt_recent = mysqli_prepare($conn, $sql_recent)) {
    if ($_SESSION["role"] == 'driver') {
        mysqli_stmt_bind_param($stmt_recent, "i", $user_id);
    }
    mysqli_stmt_execute($stmt_recent);
    $result_recent = mysqli_stmt_get_result($stmt_recent);
    
    while ($row = mysqli_fetch_assoc($result_recent)) {
        $recent_tracking[] = $row;
    }
    mysqli_stmt_close($stmt_recent);
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
            <h2><i class="fas fa-map-marker-alt me-2"></i>Tracking Data Entry</h2>
            <div class="d-flex gap-2">
                <button onclick="getCurrentLocation()" class="btn btn-outline-primary">
                    <i class="fas fa-location-arrow me-1"></i>Get Current Location
                </button>
                <a href="<?php echo BASE_URL; ?>shipments/index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Shipments
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

        <!-- Shipment Status Info -->
        <?php if (!empty($shipments)): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Ready to Track:</strong> You have <?php echo count($shipments); ?> active shipment<?php echo count($shipments) > 1 ? 's' : ''; ?> assigned to you.
                    <?php if ($pre_selected_shipment_id): ?>
                        <br><i class="fas fa-map-marker-alt me-2"></i>Shipment #<?php echo $pre_selected_shipment_id; ?> has been pre-selected for you.
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>No Active Shipments:</strong> You don't have any shipments assigned to you at the moment. 
                    <br>Please contact your logistics manager if you believe this is incorrect.
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tracking Data Entry Form -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Enter Tracking Data</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="trackingForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="shipment_id" class="form-label">Select Shipment</label>
                                    <select class="form-select" id="shipment_id" name="shipment_id" required>
                                        <option value="">Choose shipment...</option>
                                        <?php foreach ($shipments as $shipment): ?>
                                        <option value="<?php echo $shipment['shipment_id']; ?>" 
                                                <?php echo ($pre_selected_shipment_id == $shipment['shipment_id']) ? 'selected' : ''; ?>>
                                            Shipment #<?php echo $shipment['shipment_id']; ?> - 
                                            <?php echo htmlspecialchars($shipment['customer_name']); ?> 
                                            (<?php echo ucfirst(str_replace('_', ' ', $shipment['status'])); ?>)
                                            <?php if ($_SESSION["role"] != 'driver' && isset($shipment['driver_name'])): ?>
                                                - Driver: <?php echo htmlspecialchars($shipment['driver_name'] ?: 'Unassigned'); ?>
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="order_status" class="form-label">Order Status</label>
                                    <select class="form-select" id="delivery_status" name="delivery_status" required>
                                        <option value="">Select status...</option>
                                        <option value="in_transit">In Transit</option>
                                        <option value="out_for_delivery">Out for Delivery</option>
                                        <option value="delivered">Delivered</option>
                                        <option value="failed">Failed</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="latitude" class="form-label">Latitude</label>
                                    <input type="text" class="form-control" id="latitude" name="latitude" 
                                           placeholder="e.g., 23.81033167" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="longitude" class="form-label">Longitude</label>
                                    <input type="text" class="form-control" id="longitude" name="longitude" 
                                           placeholder="e.g., 90.41252136" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="temperature" class="form-label">Temperature (°C)</label>
                                    <input type="number" step="0.1" class="form-control" id="temperature" name="temperature" 
                                           placeholder="e.g., 25.5" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="humidity" class="form-label">Humidity (%)</label>
                                    <input type="number" step="0.1" class="form-control" id="humidity" name="humidity" 
                                           placeholder="e.g., 65.0" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="order_notes" class="form-label">Order Notes</label>
                                <textarea class="form-control" id="order_notes" name="order_notes" rows="3" 
                                          placeholder="Enter any notes about the delivery (e.g., traffic conditions, customer feedback, etc.)"></textarea>
                            </div>

                            <button type="submit" name="add_tracking" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Record Tracking Data
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Tracking Data -->
        <?php if (!empty($recent_tracking)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Tracking Entries</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Shipment</th>
                                        <th>Customer</th>
                                        <?php if ($_SESSION["role"] != 'driver'): ?>
                                        <th>Driver</th>
                                        <?php endif; ?>
                                        <th>Location</th>
                                        <th>Temperature</th>
                                        <th>Humidity</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_tracking as $track): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($track['recorded_at'])); ?></td>
                                        <td>
                                            <span class="badge bg-info">#<?php echo $track['shipment_id']; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($track['customer_name']); ?></td>
                                        <?php if ($_SESSION["role"] != 'driver'): ?>
                                        <td><?php echo htmlspecialchars($track['driver_name'] ?? 'N/A'); ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <small>
                                                <?php echo number_format($track['latitude'], 8); ?>, 
                                                <?php echo number_format($track['longitude'], 8); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $track['temperature'] > 30 ? 'danger' : ($track['temperature'] > 20 ? 'warning' : 'success'); ?>">
                                                <?php echo $track['temperature']; ?>°C
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $track['humidity']; ?>%</span>
                                        </td>
                                        <td>
                                                                                     <span class="badge bg-<?php 
                                             echo $track['delivery_status'] == 'delivered' ? 'success' : 
                                                 ($track['delivery_status'] == 'failed' ? 'danger' : 'primary'); 
                                         ?>">
                                             <?php echo ucfirst(str_replace('_', ' ', $track['delivery_status'])); ?>
                                         </span>
                                        </td>
                                        <td>
                                            <?php if ($track['order_notes']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($track['order_notes']); ?></small>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                document.getElementById('latitude').value = position.coords.latitude.toFixed(8);
                document.getElementById('longitude').value = position.coords.longitude.toFixed(8);
                
                // Show success message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i>Location captured successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.row'));
            },
            function(error) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>Error getting location: ${error.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.row'));
            }
        );
    } else {
        alert("Geolocation is not supported by this browser.");
    }
}

// Auto-refresh every 30 seconds
setInterval(() => {
    if (!document.hidden) {
        location.reload();
    }
}, 30000);
</script>

<?php include '../includes/footer.php'; ?>
