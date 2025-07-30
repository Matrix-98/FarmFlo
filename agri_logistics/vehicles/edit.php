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
$vehicle_id = $license_plate = $type = $capacity_weight = $capacity_volume = $status = "";
// Initialize error variables
$license_plate_err = $type_err = $capacity_weight_err = $capacity_volume_err = $status_err = "";

// Initialize audit trail variables for display
$created_at = $updated_at = $created_by_username = $updated_by_username = '';

// Fetch existing vehicle data if ID is provided in GET request
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $vehicle_id = trim($_GET["id"]);

    // FIX: Include created_at, updated_at, created_by, updated_by in the SELECT query
    $sql_fetch_vehicle = "SELECT license_plate, type, capacity_weight, capacity_volume, status, created_at, updated_at, created_by, updated_by FROM vehicles WHERE vehicle_id = ?";
    if ($stmt_fetch = mysqli_prepare($conn, $sql_fetch_vehicle)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $param_id);
        $param_id = $vehicle_id;

        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);

            if (mysqli_num_rows($result_fetch) == 1) {
                // Fetch result row and populate variables
                $row = mysqli_fetch_assoc($result_fetch);
                $license_plate = $row["license_plate"];
                $type = $row["type"];
                $capacity_weight = $row["capacity_weight"];
                $capacity_volume = $row["capacity_volume"];
                $status = $row["status"];
                // FIX: Capture audit data for display
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

    // Check for any validation errors before updating
    if (empty($license_plate_err) && empty($type_err) && empty($capacity_weight_err) && empty($capacity_volume_err) && empty($status_err)) {
        // FIX: Add 'updated_by' to the UPDATE statement
        // Assumes 'updated_at' is handled by DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP in DB
        $sql_update_vehicle = "UPDATE vehicles SET license_plate = ?, type = ?, capacity_weight = ?, capacity_volume = ?, status = ?, updated_by = ? WHERE vehicle_id = ?";

        if ($stmt_update = mysqli_prepare($conn, $sql_update_vehicle)) {
            // FIX: Add 'i' for updated_by and the $param_updated_by variable
            mysqli_stmt_bind_param($stmt_update, "ssddsii", $param_license_plate, $param_type, $param_capacity_weight, $param_capacity_volume, $param_status, $param_updated_by, $param_vehicle_id);

            // Set parameters
            $param_license_plate = $license_plate;
            $param_type = $type;
            $param_capacity_weight = $capacity_weight;
            $param_capacity_volume = $capacity_volume;
            $param_status = $status;
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
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="vehicle_id" value="<?php echo htmlspecialchars($vehicle_id); ?>">

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

                <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Update Vehicle</button>
            </form>
            <?php if (isset($created_at) || isset($updated_at)): ?>
            <div class="mt-3 border-top pt-3 text-muted small">
                <?php if (isset($created_at)): ?>
                    Created: <?php echo htmlspecialchars($created_at); ?> by <?php echo htmlspecialchars($created_by_username ?: 'N/A'); ?><br>
                <?php endif; ?>
                <?php if (isset($updated_at)): ?>
                    Last Updated: <?php echo htmlspecialchars($updated_at); ?> by <?php echo htmlspecialchars($updated_by_username ?: 'N/A'); ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>