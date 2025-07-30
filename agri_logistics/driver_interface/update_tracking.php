<?php
require_once '../config/db.php'; // Adjust path for nested folder

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

// Access control: Only Drivers, Admin, Logistics Manager can access
if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager' && $_SESSION["role"] != 'driver') {
    $_SESSION['error_message'] = "You do not have permission to access the driver interface.";
    header("location: ../dashboard.php");
    exit;
}

$page_title = "Update Tracking Data";
$current_page = "driver_interface"; // Or keep 'shipments' if you prefer grouping it there

$shipment_id = ''; // Initialize for form input
$latitude = '';
$longitude = '';
$temperature = '';
$humidity = '';
$status_update_text = '';

$latitude_err = '';
$longitude_err = '';
$temperature_err = ''; // Added validation variable
$humidity_err = '';    // Added validation variable

$shipments_list = [];

// Get shipments relevant to the logged-in user
$user_role = $_SESSION["role"];
$user_id = $_SESSION["user_id"];

if ($user_role == 'driver') {
    // For a driver, find their assigned driver_id
    $driver_profile_id = null;
    $sql_driver_id = "SELECT driver_id FROM drivers WHERE user_id = ?";
    if ($stmt_driver_id = mysqli_prepare($conn, $sql_driver_id)) {
        mysqli_stmt_bind_param($stmt_driver_id, "i", $user_id);
        if (mysqli_stmt_execute($stmt_driver_id)) {
            mysqli_stmt_bind_result($stmt_driver_id, $driver_profile_id);
            mysqli_stmt_fetch($stmt_driver_id);
        }
        mysqli_stmt_close($stmt_driver_id);
    }

    if ($driver_profile_id) {
        // List shipments assigned to THIS driver that are active
        $sql_shipments_list = "SELECT s.shipment_id, ol.name AS origin_name, dl.name AS destination_name, s.status, v.license_plate
                               FROM shipments s
                               JOIN locations ol ON s.origin_location_id = ol.location_id
                               JOIN locations dl ON s.destination_location_id = dl.location_id
                               LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
                               WHERE s.driver_id = ? AND s.status IN ('assigned', 'picked_up', 'in_transit')
                               ORDER BY s.planned_departure DESC";
        if ($stmt_shipments_list = mysqli_prepare($conn, $sql_shipments_list)) {
            mysqli_stmt_bind_param($stmt_shipments_list, "i", $driver_profile_id);
            mysqli_stmt_execute($stmt_shipments_list);
            $result_shipments_list = mysqli_stmt_get_result($stmt_shipments_list);
            while ($row = mysqli_fetch_assoc($result_shipments_list)) {
                $shipments_list[] = $row;
            }
            mysqli_stmt_close($stmt_shipments_list);
        }
    } else {
        // Only redirect if it's a driver role and no profile is linked
        $_SESSION['error_message'] = "Your driver profile is not linked to your user account. Please contact admin.";
        header("location: ../dashboard.php");
        exit;
    }
} else { // Admin or Logistics Manager - can select any active shipment
    $sql_shipments_list = "SELECT s.shipment_id, ol.name AS origin_name, dl.name AS destination_name, s.status, v.license_plate, d.first_name, d.last_name
                           FROM shipments s
                           JOIN locations ol ON s.origin_location_id = ol.location_id
                           JOIN locations dl ON s.destination_location_id = dl.location_id
                           LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
                           LEFT JOIN drivers d ON s.driver_id = d.driver_id
                           WHERE s.status IN ('assigned', 'picked_up', 'in_transit')
                           ORDER BY s.planned_departure DESC";
    if ($result_shipments_list = mysqli_query($conn, $sql_shipments_list)) {
        while ($row = mysqli_fetch_assoc($result_shipments_list)) {
            $shipments_list[] = $row;
        }
        mysqli_free_result($result_shipments_list);
    }
}


// Pre-select shipment if coming from shipment view page
if (isset($_GET['shipment_id']) && !empty(trim($_GET['shipment_id']))) {
    $shipment_id = trim($_GET['shipment_id']);
}


// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate shipment ID
    if (empty(trim($_POST["shipment_id"]))) {
        $_SESSION['error_message'] = "Please select a shipment.";
        // No redirect here, so form values persist and error displays.
        // For production, you might want to redirect.
    } else {
        $shipment_id = trim($_POST["shipment_id"]);
    }

    // Validate Lat/Lon
    if (empty(trim($_POST["latitude"])) || !is_numeric(trim($_POST["latitude"]))) {
        $latitude_err = "Please enter a valid latitude.";
    } else {
        $latitude = trim($_POST["latitude"]);
    }

    if (empty(trim($_POST["longitude"])) || !is_numeric(trim($_POST["longitude"]))) {
        $longitude_err = "Please enter a valid longitude.";
    } else {
        $longitude = trim($_POST["longitude"]);
    }

    // Validate Temperature (optional but good for perishable goods)
    if (!empty(trim($_POST["temperature"])) && !is_numeric(trim($_POST["temperature"]))) {
        $temperature_err = "Please enter a valid temperature.";
    } else {
        $temperature = !empty(trim($_POST["temperature"])) ? trim($_POST["temperature"]) : NULL;
    }

    // Validate Humidity (optional)
    if (!empty(trim($_POST["humidity"])) && (!is_numeric(trim($_POST["humidity"])) || trim($_POST["humidity"]) < 0 || trim($_POST["humidity"]) > 100)) {
        $humidity_err = "Please enter a valid humidity (0-100).";
    } else {
        $humidity = !empty(trim($_POST["humidity"])) ? trim($_POST["humidity"]) : NULL;
    }

    $status_update_text = trim($_POST["status_update_text"]);

    // If no input errors, insert into tracking_data
    if (empty($_SESSION['error_message']) && empty($latitude_err) && empty($longitude_err) && empty($temperature_err) && empty($humidity_err)) {
        // Start transaction for atomicity
        mysqli_begin_transaction($conn);

        try {
            // Insert into tracking_data table
            $sql_insert_tracking = "INSERT INTO tracking_data (shipment_id, timestamp, latitude, longitude, temperature, humidity, status_update) VALUES (?, NOW(), ?, ?, ?, ?, ?)";
            if ($stmt_tracking = mysqli_prepare($conn, $sql_insert_tracking)) {
                mysqli_stmt_bind_param($stmt_tracking, "iddids", $param_shipment_id, $param_latitude, $param_longitude, $param_temperature, $param_humidity, $param_status_update_text);

                $param_shipment_id = $shipment_id;
                $param_latitude = $latitude;
                $param_longitude = $longitude;
                $param_temperature = $temperature;
                $param_humidity = $humidity;
                $param_status_update_text = $status_update_text;

                if (!mysqli_stmt_execute($stmt_tracking)) {
                    throw new Exception("Error adding tracking data: " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt_tracking);
            } else {
                throw new Exception("Error preparing tracking insert statement: " . mysqli_error($conn));
            }

            // Update vehicle's current location in 'vehicles' table via shipment's vehicle_id
            $sql_update_vehicle_loc = "UPDATE vehicles v JOIN shipments s ON v.vehicle_id = s.vehicle_id
                                       SET v.current_latitude = ?, v.current_longitude = ?
                                       WHERE s.shipment_id = ? AND v.vehicle_id IS NOT NULL"; // Only update if vehicle is assigned
            if ($stmt_update_vehicle_loc = mysqli_prepare($conn, $sql_update_vehicle_loc)) {
                mysqli_stmt_bind_param($stmt_update_vehicle_loc, "ddi", $param_latitude, $param_longitude, $param_shipment_id);
                if (!mysqli_stmt_execute($stmt_update_vehicle_loc)) {
                    // Log but don't fail transaction if vehicle update fails (e.g., no vehicle assigned)
                    error_log("Warning: Could not update vehicle location for shipment ID " . $shipment_id . ": " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt_update_vehicle_loc);
            } else {
                 error_log("Warning: Could not prepare vehicle location update for shipment ID " . $shipment_id . ": " . mysqli_error($conn));
            }

            mysqli_commit($conn);
            $_SESSION['success_message'] = "Tracking data added successfully for Shipment ID: " . $shipment_id;
            // Clear form fields on successful submission
            $latitude = $longitude = $temperature = $humidity = $status_update_text = "";
            
            // For a better UX, redirect to the shipment view page
            header("location: ../shipments/view.php?id=" . $shipment_id);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error_message'] = "Transaction failed: " . $e->getMessage();
            error_log("Tracking data addition failed: " . $e->getMessage());
        } // The finally block is removed as connection is closed in footer.php
    }
}
// Connection is closed in includes/footer.php
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Driver Interface: Update Shipment Tracking</h2>

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

        <?php if (empty($shipments_list)): ?>
            <div class="alert alert-info">No active or assigned shipments found for tracking.</div>
        <?php else: ?>
            <div class="card p-4 shadow-sm mb-4">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="mb-3">
                        <label for="shipment_id" class="form-label">Select Shipment <span class="text-danger">*</span></label>
                        <select name="shipment_id" id="shipment_id" class="form-select">
                            <option value="">-- Select an active shipment --</option>
                            <?php foreach ($shipments_list as $shipment): ?>
                                <option value="<?php echo htmlspecialchars($shipment['shipment_id']); ?>" <?php echo ($shipment_id == $shipment['shipment_id']) ? 'selected' : ''; ?>>
                                    ID: <?php echo htmlspecialchars($shipment['shipment_id']); ?> | From: <?php echo htmlspecialchars($shipment['origin_name']); ?> | To: <?php echo htmlspecialchars($shipment['destination_name']); ?> | Status: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $shipment['status']))); ?>
                                    <?php if ($user_role != 'driver' && isset($shipment['license_plate'])): // Only show vehicle/driver for admin/logistics ?>
                                        | Vehicle: <?php echo htmlspecialchars($shipment['license_plate'] ?: 'N/A'); ?> | Driver: <?php echo htmlspecialchars(($shipment['first_name'] ? $shipment['first_name'] . ' ' . $shipment['last_name'] : 'N/A')); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="latitude" class="form-label">Current Latitude <span class="text-danger">*</span></label>
                            <input type="text" name="latitude" id="latitude" class="form-control <?php echo (!empty($latitude_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($latitude); ?>" placeholder="e.g., 34.0522">
                            <div class="invalid-feedback"><?php echo $latitude_err; ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="longitude" class="form-label">Current Longitude <span class="text-danger">*</span></label>
                            <input type="text" name="longitude" id="longitude" class="form-control <?php echo (!empty($longitude_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($longitude); ?>" placeholder="e.g., -118.2437">
                            <div class="invalid-feedback"><?php echo $longitude_err; ?></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="temperature" class="form-label">Temperature (°C)</label>
                            <input type="number" name="temperature" id="temperature" class="form-control <?php echo (!empty($temperature_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($temperature); ?>" step="0.1" placeholder="e.g., 4.5">
                            <div class="invalid-feedback"><?php echo $temperature_err; ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="humidity" class="form-label">Humidity (%)</label>
                            <input type="number" name="humidity" id="humidity" class="form-control <?php echo (!empty($humidity_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($humidity); ?>" step="0.1" min="0" max="100" placeholder="e.g., 85.2">
                            <div class="invalid-feedback"><?php echo $humidity_err; ?></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="status_update_text" class="form-label">Status Update (Message)</label>
                        <textarea name="status_update_text" id="status_update_text" class="form-control" rows="3"><?php echo htmlspecialchars($status_update_text); ?></textarea>
                        <small class="form-text text-muted">e.g., "Passed checkpoint", "Delayed due to traffic", "Arrived at destination"</small>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-location-arrow"></i> Report Location & Conditions</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>