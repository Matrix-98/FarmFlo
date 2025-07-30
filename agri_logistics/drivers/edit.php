<?php
// This line is CRUCIAL for BASE_URL and $conn to be available
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php"); // Use BASE_URL for redirect
    exit;
}

// Check user role for access control
if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to edit drivers.";
    header("location: " . BASE_URL . "dashboard.php"); // Use BASE_URL for redirect
    exit;
}

$page_title = "Edit Driver";
$current_page = "drivers"; // For active state in sidebar

// Initialize form variables
$driver_id = $first_name = $last_name = $license_number = $phone_number = $status = $user_id = "";
// Initialize error variables
$first_name_err = $last_name_err = $license_number_err = $phone_number_err = $status_err = "";

// Fetch existing driver data if ID is provided in GET request
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $driver_id = trim($_GET["id"]);

    // Prepare a select statement to fetch existing driver details
    $sql_fetch_driver = "SELECT first_name, last_name, license_number, phone_number, status, user_id FROM drivers WHERE driver_id = ?";
    if ($stmt_fetch = mysqli_prepare($conn, $sql_fetch_driver)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $param_id);
        $param_id = $driver_id;

        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);
            if (mysqli_num_rows($result_fetch) == 1) {
                // Fetch result row and populate variables
                $row = mysqli_fetch_assoc($result_fetch);
                $first_name = $row["first_name"];
                $last_name = $row["last_name"];
                $license_number = $row["license_number"];
                $phone_number = $row["phone_number"];
                $status = $row["status"];
                $user_id = $row["user_id"]; // Current linked user_id
            } else {
                // Driver not found, redirect with error
                $_SESSION['error_message'] = "Driver not found.";
                header("location: " . BASE_URL . "drivers/index.php");
                exit();
            }
        } else {
            // Error executing fetch query
            $_SESSION['error_message'] = "Oops! Something went wrong fetching driver data. Please try again later.";
            error_log("Error executing driver fetch: " . mysqli_error($conn));
            header("location: " . BASE_URL . "drivers/index.php");
            exit();
        }
        mysqli_stmt_close($stmt_fetch);
    } else {
        // Error preparing fetch query
        $_SESSION['error_message'] = "Error preparing driver fetch statement. Please try again later.";
        error_log("Error preparing driver fetch statement: " . mysqli_error($conn));
        header("location: " . BASE_URL . "drivers/index.php");
        exit();
    }
} else if ($_SERVER["REQUEST_METHOD"] != "POST") { // Redirect if no ID provided in GET, and not a POST request
    $_SESSION['error_message'] = "Invalid request. No driver ID provided.";
    header("location: " . BASE_URL . "drivers/index.php");
    exit();
}

// Fetch available users with 'driver' role who are not yet linked to a driver profile
// OR are linked to the current driver profile being edited.
$available_users = [];
$sql_users = "SELECT u.user_id, u.username 
              FROM users u 
              LEFT JOIN drivers d ON u.user_id = d.user_id 
              WHERE u.role = 'driver' AND (d.user_id IS NULL OR d.user_id = ?) 
              ORDER BY u.username ASC";
if ($stmt_users = mysqli_prepare($conn, $sql_users)) {
    mysqli_stmt_bind_param($stmt_users, "i", $param_current_user_id);
    $param_current_user_id = $user_id; // Use the fetched user_id for the current driver
    mysqli_stmt_execute($stmt_users);
    $result_users = mysqli_stmt_get_result($stmt_users);
    while ($row = mysqli_fetch_assoc($result_users)) {
        $available_users[] = $row;
    }
    mysqli_stmt_close($stmt_users);
} else {
    error_log("Error preparing available users query for drivers: " . mysqli_error($conn));
}


// Process form submission (when data is posted back to this page)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $driver_id = $_POST["driver_id"]; // Get driver_id from hidden input

    // Validate first name
    if (empty(trim($_POST["first_name"]))) {
        $first_name_err = "Please enter the first name.";
    } else {
        $first_name = trim($_POST["first_name"]);
    }

    // Validate last name
    if (empty(trim($_POST["last_name"]))) {
        $last_name_err = "Please enter the last name.";
    } else {
        $last_name = trim($_POST["last_name"]);
    }

    // Validate license number and check for uniqueness (excluding current driver)
    if (empty(trim($_POST["license_number"]))) {
        $license_number_err = "Please enter the license number.";
    } else {
        $license_number = trim($_POST["license_number"]);
        $sql_check_license = "SELECT driver_id FROM drivers WHERE license_number = ? AND driver_id != ?";
        if($stmt_check_license = mysqli_prepare($conn, $sql_check_license)){
            mysqli_stmt_bind_param($stmt_check_license, "si", $param_license_number, $param_driver_id_check);
            $param_license_number = $license_number;
            $param_driver_id_check = $driver_id; // Exclude current driver's ID
            if(mysqli_stmt_execute($stmt_check_license)){
                mysqli_stmt_store_result($stmt_check_license);
                if(mysqli_stmt_num_rows($stmt_check_license) >= 1){
                    $license_number_err = "This license number is already registered to another driver.";
                }
            } else {
                error_log("Error checking duplicate license number during edit: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_check_license);
        }
    }

    // Validate phone number
    if (empty(trim($_POST["phone_number"]))) {
        $phone_number_err = "Please enter the phone number.";
    } else {
        $phone_number = trim($_POST["phone_number"]);
    }

    // Validate status
    if (empty(trim($_POST["status"]))) {
        $status_err = "Please select the driver status.";
    } else {
        $status = trim($_POST["status"]);
    }

    // user_id is optional, set to NULL if empty
    $user_id = !empty($_POST["user_id"]) ? $_POST["user_id"] : NULL;

    // Check for any validation errors before updating
    if (empty($first_name_err) && empty($last_name_err) && empty($license_number_err) && empty($phone_number_err) && empty($status_err)) {
        // Prepare an update statement
        $sql_update_driver = "UPDATE drivers SET first_name = ?, last_name = ?, license_number = ?, phone_number = ?, status = ?, user_id = ? WHERE driver_id = ?";

        if ($stmt_update = mysqli_prepare($conn, $sql_update_driver)) {
            // Bind parameters (s: string, i: integer)
            mysqli_stmt_bind_param($stmt_update, "sssssii", $param_first_name, $param_last_name, $param_license_number, $param_phone_number, $param_status, $param_user_id, $param_driver_id);

            // Set parameters
            $param_first_name = $first_name;
            $param_last_name = $last_name;
            $param_license_number = $license_number;
            $param_phone_number = $phone_number;
            $param_status = $status;
            $param_user_id = $user_id;
            $param_driver_id = $driver_id;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt_update)) {
                $_SESSION['success_message'] = "Driver updated successfully!";
                header("location: " . BASE_URL . "drivers/index.php"); // Redirect to driver list
                exit();
            } else {
                $_SESSION['error_message'] = "Error: Could not update driver. " . mysqli_error($conn);
                error_log("Error updating driver: " . mysqli_error($conn));
            }

            // Close statement
            mysqli_stmt_close($stmt_update);
        } else {
            $_SESSION['error_message'] = "Error preparing update statement: " . mysqli_error($conn);
            error_log("Error preparing driver update statement: " . mysqli_error($conn));
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
        <h2 class="mb-4">Edit Driver</h2>
        <a href="<?php echo BASE_URL; ?>drivers/index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Driver List</a>

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
                <input type="hidden" name="driver_id" value="<?php echo htmlspecialchars($driver_id); ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" id="first_name" class="form-control <?php echo (!empty($first_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($first_name); ?>">
                        <div class="invalid-feedback"><?php echo $first_name_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" id="last_name" class="form-control <?php echo (!empty($last_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($last_name); ?>">
                        <div class="invalid-feedback"><?php echo $last_name_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="license_number" class="form-label">License Number <span class="text-danger">*</span></label>
                        <input type="text" name="license_number" id="license_number" class="form-control <?php echo (!empty($license_number_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($license_number); ?>">
                        <div class="invalid-feedback"><?php echo $license_number_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="phone_number" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="phone_number" id="phone_number" class="form-control <?php echo (!empty($phone_number_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($phone_number); ?>">
                        <div class="invalid-feedback"><?php echo $phone_number_err; ?></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select <?php echo (!empty($status_err)) ? 'is-invalid' : ''; ?>">
                        <option value="">Select Status</option>
                        <option value="active" <?php echo ($status == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($status == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="on_leave" <?php echo ($status == 'on_leave') ? 'selected' : ''; ?>>On Leave</option>
                    </select>
                    <div class="invalid-feedback"><?php echo $status_err; ?></div>
                </div>

                <div class="mb-3">
                    <label for="user_id" class="form-label">Link to User Account (Optional)</label>
                    <select name="user_id" id="user_id" class="form-select">
                        <option value="">Do Not Link</option>
                        <?php foreach ($available_users as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['user_id']); ?>" <?php echo ($user_id == $user['user_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Select a user account with 'driver' role that is not yet linked.</small>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Update Driver</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>