<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to edit vehicles.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Edit Vehicle";
$current_page = "vehicles";

// Initialize form variables
$vehicle_id = $vehicle_code = $license_plate = $type = $manufacturer = $model = $year = $fuel_type = $capacity_weight = $capacity_volume = $status = $user_id = "";
// Initialize error variables
$license_plate_err = $type_err = $manufacturer_err = $model_err = $year_err = $fuel_type_err = $capacity_weight_err = $capacity_volume_err = $status_err = $user_id_err = "";

// Initialize audit trail variables for display
$created_at = $updated_at = $created_by_username = $updated_by_username = '';

// Fetch existing vehicle data if ID is provided in GET request
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $vehicle_id = trim($_GET["id"]);

    $sql_fetch_vehicle = "SELECT vehicle_code, license_plate, type, manufacturer, model, year, fuel_type, capacity_weight, capacity_volume, status, user_id, created_at, updated_at, created_by, updated_by FROM vehicles WHERE vehicle_id = ?";
    if ($stmt_fetch = mysqli_prepare($conn, $sql_fetch_vehicle)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $param_id);
        $param_id = $vehicle_id;

        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);

            if (mysqli_num_rows($result_fetch) == 1) {
                // Fetch result row and populate variables
                $row = mysqli_fetch_assoc($result_fetch);
                $vehicle_code = $row["vehicle_code"];
                $license_plate = $row["license_plate"];
                $type = $row["type"];
                $manufacturer = $row["manufacturer"];
                $model = $row["model"];
                $year = $row["year"];
                $fuel_type = $row["fuel_type"];
                $capacity_weight = $row["capacity_weight"];
                $capacity_volume = $row["capacity_volume"];
                $status = $row["status"];
                $user_id = $row["user_id"];
                // Capture audit data for display
                $created_at = $row["created_at"];
                $updated_at = $row["updated_at"];
                $created_by_id = $row["created_by"];
                $updated_by_id = $row["updated_by"];

                // Fetch usernames for display
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
                // Vehicle not found, redirect with error
                $_SESSION['error_message'] = "Vehicle not found.";
                header("location: " . BASE_URL . "vehicles/index.php");
                exit();
            }
        } else {
            // Error executing fetch query
            $_SESSION['error_message'] = "Oops! Something went wrong fetching vehicle data. Please try again later.";
            error_log("Error executing vehicle fetch: " . mysqli_error($conn));
            header("location: " . BASE_URL . "vehicles/index.php");
            exit();
        }
        mysqli_stmt_close($stmt_fetch);
    } else {
        // Error preparing fetch query
        $_SESSION['error_message'] = "Error preparing vehicle fetch statement. Please try again later.";
        error_log("Error preparing vehicle fetch statement: " . mysqli_error($conn));
        header("location: " . BASE_URL . "vehicles/index.php");
        exit();
    }
} else if ($_SERVER["REQUEST_METHOD"] != "POST") { // Redirect if no ID provided in GET, and not a POST request
    $_SESSION['error_message'] = "Invalid request. No vehicle ID provided.";
    header("location: " . BASE_URL . "vehicles/index.php");
    exit();
}

// Get available users for linking (users with 'driver' role who are not already linked to another vehicle, or the current vehicle)
$available_users = [];
$sql_users = "SELECT u.user_id, u.username, u.email, CONCAT(d.first_name, ' ', d.last_name) as driver_name 
              FROM users u 
              LEFT JOIN drivers d ON u.user_id = d.user_id 
              WHERE u.role = 'driver' 
              AND (u.user_id NOT IN (SELECT user_id FROM vehicles WHERE user_id IS NOT NULL) OR u.user_id = ?)
              ORDER BY u.username";
if ($stmt_users = mysqli_prepare($conn, $sql_users)) {
    mysqli_stmt_bind_param($stmt_users, "i", $user_id);
    mysqli_stmt_execute($stmt_users);
    $result_users = mysqli_stmt_get_result($stmt_users);
    while ($row = mysqli_fetch_assoc($result_users)) {
        $available_users[] = $row;
    }
    mysqli_stmt_close($stmt_users);
}

// Process form submission (when data is posted back to this page)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $vehicle_id = $_POST["vehicle_id"]; // Get vehicle_id from hidden input

    // Validate License Plate and check for uniqueness (excluding current vehicle)
    if (empty(trim($_POST["license_plate"]))) {
        $license_plate_err = "Please enter the license plate.";
    } else {
        $license_plate = trim($_POST["license_plate"]);
        $sql_check_license = "SELECT vehicle_id FROM vehicles WHERE license_plate = ? AND vehicle_id != ?";
        if($stmt_check_license = mysqli_prepare($conn, $sql_check_license)){
            mysqli_stmt_bind_param($stmt_check_license, "si", $param_license_plate, $param_vehicle_id_check);
            $param_license_plate = $license_plate;
            $param_vehicle_id_check = $vehicle_id; // Exclude current vehicle's ID
            if(mysqli_stmt_execute($stmt_check_license)){
                mysqli_stmt_store_result($stmt_check_license);
                if(mysqli_stmt_num_rows($stmt_check_license) >= 1){
                    $license_plate_err = "This license plate is already registered to another vehicle.";
                }
            } else {
                error_log("Error checking duplicate license plate during edit: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_check_license);
        }
    }

    // Validate Vehicle Type
    if (empty(trim($_POST["type"]))) {
        $type_err = "Please enter the vehicle type.";
    } else {
        $type = trim($_POST["type"]);
    }

    // Validate Manufacturer
    if (empty(trim($_POST["manufacturer"]))) {
        $manufacturer_err = "Please enter the manufacturer.";
    } else {
        $manufacturer = trim($_POST["manufacturer"]);
    }

    // Validate Model
    if (empty(trim($_POST["model"]))) {
        $model_err = "Please enter the model.";
    } else {
        $model = trim($_POST["model"]);
    }

    // Validate Year
    if (empty(trim($_POST["year"])) || !is_numeric(trim($_POST["year"]))) {
        $year_err = "Please enter a valid year.";
    } else {
        $year = trim($_POST["year"]);
        if ($year < 1900 || $year > date('Y') + 1) {
            $year_err = "Please enter a valid year between 1900 and " . (date('Y') + 1);
        }
    }

    // Validate Fuel Type
    if (empty(trim($_POST["fuel_type"]))) {
        $fuel_type_err = "Please select the fuel type.";
    } else {
        $fuel_type = trim($_POST["fuel_type"]);
    }

    // Validate Capacity Weight
    if (empty(trim($_POST["capacity_weight"])) || !is_numeric(trim($_POST["capacity_weight"])) || $_POST["capacity_weight"] <= 0) {
        $capacity_weight_err = "Please enter a valid positive weight capacity.";
    } else {
        $capacity_weight = trim($_POST["capacity_weight"]);
    }

    // Validate Capacity Volume
    if (empty(trim($_POST["capacity_volume"])) || !is_numeric(trim($_POST["capacity_volume"])) || $_POST["capacity_volume"] <= 0) {
        $capacity_volume_err = "Please enter a valid positive volume capacity.";
    } else {
        $capacity_volume = trim($_POST["capacity_volume"]);
    }

    // Validate Status
    if (empty(trim($_POST["status"]))) {
        $status_err = "Please select the vehicle status.";
    } else {
        $status = trim($_POST["status"]);
    }

    // Validate User ID (optional)
    if (!empty(trim($_POST["user_id"]))) {
        $user_id = trim($_POST["user_id"]);
        // Check if user is already linked to another vehicle (excluding current vehicle)
        $sql_check_user = "SELECT vehicle_id FROM vehicles WHERE user_id = ? AND vehicle_id != ?";
        if($stmt_check_user = mysqli_prepare($conn, $sql_check_user)){
            mysqli_stmt_bind_param($stmt_check_user, "ii", $param_user_id, $param_vehicle_id_check);
            $param_user_id = $user_id;
            $param_vehicle_id_check = $vehicle_id;
            if(mysqli_stmt_execute($stmt_check_user)){
                mysqli_stmt_store_result($stmt_check_user);
                if(mysqli_stmt_num_rows($stmt_check_user) >= 1){
                    $user_id_err = "This user is already linked to another vehicle.";
                }
            }
            mysqli_stmt_close($stmt_check_user);
        }
    } else {
        $user_id = null;
    }

    // Check for any validation errors before updating
    if (empty($license_plate_err) && empty($type_err) && empty($manufacturer_err) && empty($model_err) && empty($year_err) && empty($fuel_type_err) && empty($capacity_weight_err) && empty($capacity_volume_err) && empty($status_err) && empty($user_id_err)) {
        
        $sql_update_vehicle = "UPDATE vehicles SET license_plate = ?, type = ?, manufacturer = ?, model = ?, year = ?, fuel_type = ?, capacity_weight = ?, capacity_volume = ?, status = ?, user_id = ?, updated_by = ?, updated_at = NOW() WHERE vehicle_id = ?";

        if ($stmt_update = mysqli_prepare($conn, $sql_update_vehicle)) {
            mysqli_stmt_bind_param($stmt_update, "ssssssssssii", $param_license_plate, $param_type, $param_manufacturer, $param_model, $param_year, $param_fuel_type, $param_capacity_weight, $param_capacity_volume, $param_status, $param_user_id, $param_updated_by, $param_vehicle_id);

            // Set parameters
            $param_license_plate = $license_plate;
            $param_type = $type;
            $param_manufacturer = $manufacturer;
            $param_model = $model;
            $param_year = $year;
            $param_fuel_type = $fuel_type;
            $param_capacity_weight = $capacity_weight;
            $param_capacity_volume = $capacity_volume;
            $param_status = $status;
            $param_user_id = $user_id;
            $param_updated_by = $_SESSION['user_id']; // Capture logged-in user's ID
            $param_vehicle_id = $vehicle_id;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt_update)) {
                $_SESSION['success_message'] = "Vehicle updated successfully!";
                header("location: " . BASE_URL . "vehicles/index.php"); // Redirect to vehicle list
                exit();
            } else {
                $_SESSION['error_message'] = "Error: Could not update vehicle. " . mysqli_error($conn);
                error_log("Error updating vehicle: " . mysqli_error($conn));
            }

            // Close statement
            mysqli_stmt_close($stmt_update);
        } else {
            $_SESSION['error_message'] = "Error preparing update statement: " . mysqli_error($conn);
            error_log("Error preparing vehicle update statement: " . mysqli_error($conn));
        }
    }
    // Note: mysqli_close($conn) is handled by includes/footer.php
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Edit Vehicle</h2>
        <a href="<?php echo BASE_URL; ?>vehicles/index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Vehicle List</a>

        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
            unset($_SESSION['success_message']);
        }
        ?>

        <div class="card p-4 shadow-sm">
            <!-- Audit Trail Information -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <h6 class="text-muted">Vehicle Information</h6>
                    <p class="mb-1"><strong>Vehicle Code:</strong> <?php echo htmlspecialchars($vehicle_code); ?></p>
                    <p class="mb-1"><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($created_at)); ?> by <?php echo htmlspecialchars($created_by_username ?? 'N/A'); ?></p>
                    <?php if ($updated_by_username): ?>
                    <p class="mb-0"><strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime($updated_at)); ?> by <?php echo htmlspecialchars($updated_by_username); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="vehicle_id" value="<?php echo $vehicle_id; ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="license_plate" class="form-label">License Plate <span class="text-danger">*</span></label>
                        <input type="text" name="license_plate" id="license_plate" class="form-control <?php echo (!empty($license_plate_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($license_plate); ?>">
                        <div class="invalid-feedback"><?php echo $license_plate_err; ?></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="type" class="form-label">Vehicle Type <span class="text-danger">*</span></label>
                        <input type="text" name="type" id="type" class="form-control <?php echo (!empty($type_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($type); ?>" placeholder="e.g., Refrigerated Truck, Van">
                        <div class="invalid-feedback"><?php echo $type_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="manufacturer" class="form-label">Manufacturer <span class="text-danger">*</span></label>
                        <input type="text" name="manufacturer" id="manufacturer" class="form-control <?php echo (!empty($manufacturer_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($manufacturer); ?>" placeholder="e.g., Toyota, Ford, Mercedes">
                        <div class="invalid-feedback"><?php echo $manufacturer_err; ?></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="model" class="form-label">Model <span class="text-danger">*</span></label>
                        <input type="text" name="model" id="model" class="form-control <?php echo (!empty($model_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($model); ?>" placeholder="e.g., Hino 300, Sprinter">
                        <div class="invalid-feedback"><?php echo $model_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="year" class="form-label">Year <span class="text-danger">*</span></label>
                        <input type="number" name="year" id="year" class="form-control <?php echo (!empty($year_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($year); ?>" min="1900" max="<?php echo date('Y') + 1; ?>">
                        <div class="invalid-feedback"><?php echo $year_err; ?></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="fuel_type" class="form-label">Fuel Type <span class="text-danger">*</span></label>
                        <select name="fuel_type" id="fuel_type" class="form-select <?php echo (!empty($fuel_type_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Fuel Type</option>
                            <option value="diesel" <?php echo ($fuel_type == 'diesel') ? 'selected' : ''; ?>>Diesel</option>
                            <option value="petrol" <?php echo ($fuel_type == 'petrol') ? 'selected' : ''; ?>>Petrol</option>
                            <option value="electric" <?php echo ($fuel_type == 'electric') ? 'selected' : ''; ?>>Electric</option>
                            <option value="hybrid" <?php echo ($fuel_type == 'hybrid') ? 'selected' : ''; ?>>Hybrid</option>
                            <option value="lpg" <?php echo ($fuel_type == 'lpg') ? 'selected' : ''; ?>>LPG</option>
                        </select>
                        <div class="invalid-feedback"><?php echo $fuel_type_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="capacity_weight" class="form-label">Capacity (Weight in kg) <span class="text-danger">*</span></label>
                        <input type="number" name="capacity_weight" id="capacity_weight" class="form-control <?php echo (!empty($capacity_weight_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($capacity_weight); ?>" step="0.01">
                        <div class="invalid-feedback"><?php echo $capacity_weight_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="capacity_volume" class="form-label">Capacity (Volume in m³) <span class="text-danger">*</span></label>
                        <input type="number" name="capacity_volume" id="capacity_volume" class="form-control <?php echo (!empty($capacity_volume_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($capacity_volume); ?>" step="0.01">
                        <div class="invalid-feedback"><?php echo $capacity_volume_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-select <?php echo (!empty($status_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Status</option>
                            <option value="available" <?php echo ($status == 'available') ? 'selected' : ''; ?>>Available</option>
                            <option value="in-use" <?php echo ($status == 'in-use') ? 'selected' : ''; ?>>In Use</option>
                            <option value="maintenance" <?php echo ($status == 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="retired" <?php echo ($status == 'retired') ? 'selected' : ''; ?>>Retired</option>
                        </select>
                        <div class="invalid-feedback"><?php echo $status_err; ?></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="user_id" class="form-label">Link to Driver (Optional)</label>
                        <select name="user_id" id="user_id" class="form-select <?php echo (!empty($user_id_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">No Driver Link</option>
                            <?php foreach ($available_users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php echo ($user_id == $user['user_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?> - <?php echo htmlspecialchars($user['driver_name'] ?? 'N/A'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $user_id_err; ?></div>
                        <small class="form-text text-muted">Link this vehicle to a driver account for better tracking and management.</small>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Vehicle</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>