<?php
// This line is CRUCIAL for BASE_URL and $conn to be available
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php"); // Use BASE_URL for redirect
    exit;
}

// Check user role for access control
if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to add drivers.";
    header("location: " . BASE_URL . "dashboard.php"); // Use BASE_URL for redirect
    exit;
}

$page_title = "Add Driver";
$current_page = "drivers"; // For active state in sidebar

// Initialize form variables
$first_name = $last_name = $license_number = $phone_number = $status = $user_id = "";
// Initialize error variables
$first_name_err = $last_name_err = $license_number_err = $phone_number_err = $status_err = "";

// Fetch available users with 'driver' role who are not yet linked to a driver profile
$available_users = [];
$sql_users = "SELECT u.user_id, u.username 
              FROM users u 
              LEFT JOIN drivers d ON u.user_id = d.user_id 
              WHERE u.role = 'driver' AND d.user_id IS NULL 
              ORDER BY u.username ASC";
if ($result_users = mysqli_query($conn, $sql_users)) {
    while ($row = mysqli_fetch_assoc($result_users)) {
        $available_users[] = $row;
    }
    mysqli_free_result($result_users);
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

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

    // Validate license number and check for uniqueness
    if (empty(trim($_POST["license_number"]))) {
        $license_number_err = "Please enter the license number.";
    } else {
        $license_number = trim($_POST["license_number"]);
        $sql_check = "SELECT driver_id FROM drivers WHERE license_number = ?";
        if($stmt_check = mysqli_prepare($conn, $sql_check)){
            mysqli_stmt_bind_param($stmt_check, "s", $param_license_number);
            $param_license_number = $license_number;
            if(mysqli_stmt_execute($stmt_check)){
                mysqli_stmt_store_result($stmt_check);
                if(mysqli_stmt_num_rows($stmt_check) >= 1){
                    $license_number_err = "This license number is already registered.";
                }
            } else {
                // Log but don't stop execution, allow other validations to run
                error_log("Error checking duplicate license number: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_check);
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

    // Check for any validation errors before inserting
    if (empty($first_name_err) && empty($last_name_err) && empty($license_number_err) && empty($phone_number_err) && empty($status_err)) {
        // Prepare an insert statement
        $sql = "INSERT INTO drivers (first_name, last_name, license_number, phone_number, status, user_id) VALUES (?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind parameters (s: string, i: integer)
            mysqli_stmt_bind_param($stmt, "sssssi", $param_first_name, $param_last_name, $param_license_number, $param_phone_number, $param_status, $param_user_id);

            // Set parameters
            $param_first_name = $first_name;
            $param_last_name = $last_name;
            $param_license_number = $license_number;
            $param_phone_number = $phone_number;
            $param_status = $status;
            $param_user_id = $user_id;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Driver added successfully!";
                header("location: " . BASE_URL . "drivers/index.php"); // Redirect to driver list
                exit();
            } else {
                $_SESSION['error_message'] = "Error: Could not add driver. " . mysqli_error($conn);
                error_log("Error adding driver: " . mysqli_error($conn)); // Log error for debugging
            }

            // Close statement
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Error preparing insert statement: " . mysqli_error($conn);
            error_log("Error preparing driver insert statement: " . mysqli_error($conn));
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
        <h2 class="mb-4">Add New Driver</h2>
        <a href="<?php echo BASE_URL; ?>drivers/index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Driver List</a>

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

                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Driver</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>