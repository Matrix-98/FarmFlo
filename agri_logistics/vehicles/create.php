<?php
require_once '../config/db.php';

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
$license_plate = $type = $capacity_weight = $capacity_volume = $status = "";
// Initialize error variables
$license_plate_err = $type_err = $capacity_weight_err = $capacity_volume_err = $status_err = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

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

    // Check for any validation errors before inserting
    if (empty($license_plate_err) && empty($type_err) && empty($capacity_weight_err) && empty($capacity_volume_err) && empty($status_err)) {
        // FIX: Add 'created_by' to the INSERT statement
        $sql = "INSERT INTO vehicles (license_plate, type, capacity_weight, capacity_volume, status, created_by) VALUES (?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            // FIX: Add 'i' for created_by and the $param_created_by variable
            mysqli_stmt_bind_param($stmt, "ssddsi", $param_license_plate, $param_type, $param_capacity_weight, $param_capacity_volume, $param_status, $param_created_by);

            $param_license_plate = $license_plate;
            $param_type = $type;
            $param_capacity_weight = $capacity_weight;
            $param_capacity_volume = $capacity_volume;
            $param_status = $status;
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
                <div class="mb-3">
                    <label for="license_plate" class="form-label">License Plate <span class="text-danger">*</span></label>
                    <input type="text" name="license_plate" id="license_plate" class="form-control <?php echo (!empty($license_plate_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($license_plate); ?>">
                    <div class="invalid-feedback"><?php echo $license_plate_err; ?></div>
                </div>

                <div class="mb-3">
                    <label for="type" class="form-label">Vehicle Type <span class="text-danger">*</span></label>
                    <input type="text" name="type" id="type" class="form-control <?php echo (!empty($type_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($type); ?>" placeholder="e.g., Refrigerated Truck, Van">
                    <div class="invalid-feedback"><?php echo $type_err; ?></div>
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

                <div class="mb-3">
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

                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Vehicle</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>