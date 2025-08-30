<?php
require_once '../config/db.php';
require_once '../utils/id_generator.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Access control: Only Admin and Logistics Manager can add vehicles
if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to add vehicles.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Add Vehicle";
$current_page = "vehicles";

// Initialize form variables
$vehicle_code = $license_plate = $type = $manufacturer = $model = $year = $fuel_type = $capacity_weight = $capacity_volume = $status = $user_id = "";
// Initialize error variables
$vehicle_code_err = $license_plate_err = $type_err = $manufacturer_err = $model_err = $year_err = $fuel_type_err = $capacity_weight_err = $capacity_volume_err = $status_err = $user_id_err = "";

// Get available users for linking (users with 'driver' role who are not already linked to a vehicle)
$available_users = [];
$sql_users = "SELECT u.user_id, u.username, u.email, CONCAT(d.first_name, ' ', d.last_name) as driver_name 
              FROM users u 
              LEFT JOIN drivers d ON u.user_id = d.user_id 
              WHERE u.role = 'driver' 
              AND u.user_id NOT IN (SELECT user_id FROM vehicles WHERE user_id IS NOT NULL)
              ORDER BY u.username";
$result_users = mysqli_query($conn, $sql_users);
if ($result_users) {
    while ($row = mysqli_fetch_assoc($result_users)) {
        $available_users[] = $row;
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Generate vehicle code
    $vehicle_code = generateVehicleId();

    // Validate License Plate and check for uniqueness
    if (empty(trim($_POST["license_plate"]))) {
        $license_plate_err = "Please enter the license plate.";
    } else {
        $license_plate = trim($_POST["license_plate"]);
        $sql_check = "SELECT vehicle_id FROM vehicles WHERE license_plate = ?";
        if($stmt_check = mysqli_prepare($conn, $sql_check)){
            mysqli_stmt_bind_param($stmt_check, "s", $param_license_plate);
            $param_license_plate = $license_plate;
            if(mysqli_stmt_execute($stmt_check)){
                mysqli_stmt_store_result($stmt_check);
                if(mysqli_stmt_num_rows($stmt_check) >= 1){
                    $license_plate_err = "This license plate is already registered.";
                }
            } else {
                error_log("Error checking duplicate license plate: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_check);
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
        // Check if user is already linked to another vehicle
        $sql_check_user = "SELECT vehicle_id FROM vehicles WHERE user_id = ?";
        if($stmt_check_user = mysqli_prepare($conn, $sql_check_user)){
            mysqli_stmt_bind_param($stmt_check_user, "i", $param_user_id);
            $param_user_id = $user_id;
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

    // Check for any validation errors before inserting
    if (empty($license_plate_err) && empty($type_err) && empty($manufacturer_err) && empty($model_err) && empty($year_err) && empty($fuel_type_err) && empty($capacity_weight_err) && empty($capacity_volume_err) && empty($status_err) && empty($user_id_err)) {
        
        $sql = "INSERT INTO vehicles (vehicle_code, license_plate, type, manufacturer, model, year, fuel_type, capacity_weight, capacity_volume, status, user_id, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssssssssi", $param_vehicle_code, $param_license_plate, $param_type, $param_manufacturer, $param_model, $param_year, $param_fuel_type, $param_capacity_weight, $param_capacity_volume, $param_status, $param_user_id, $param_created_by);

            $param_vehicle_code = $vehicle_code;
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
            $param_created_by = $_SESSION['user_id']; // Capture logged-in user's ID

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Vehicle added successfully!";
                header("location: " . BASE_URL . "vehicles/index.php"); // Redirect to vehicle list
                exit();
            } else {
                $_SESSION['error_message'] = "Error: Could not add vehicle. " . mysqli_error($conn);
                error_log("Error adding vehicle: " . mysqli_error($conn)); // Log error for debugging
            }

            // Close statement
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Error preparing insert statement: " . mysqli_error($conn);
            error_log("Error preparing vehicle insert statement: " . mysqli_error($conn));
        }
    }
    // Note: mysqli_close($conn) is handled by includes/footer.php, no need to call here.
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Add New Vehicle</h2>
        <a href="<?php echo BASE_URL; ?>vehicles/index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Vehicle List</a>

        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        if (isset($_SESSION['success_message'])) { // Also check for success if previous redirect was missed for some reason
            echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
            unset($_SESSION['success_message']);
        }
        ?>

        <div class="card p-4 shadow-sm">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
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

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Vehicle Code:</strong> A unique 6-digit code will be automatically generated for this vehicle.
                </div>

                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Vehicle</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>