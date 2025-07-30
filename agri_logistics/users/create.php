<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to create users.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Add User";
$current_page = "users";

$username = $password = $confirm_password = $role = $email = $phone = $customer_type = "";
$username_err = $password_err = $confirm_password_err = $role_err = $email_err = $customer_type_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        $sql_check_username = "SELECT user_id FROM users WHERE username = ?";
        if ($stmt_check = mysqli_prepare($conn, $sql_check_username)) {
            mysqli_stmt_bind_param($stmt_check, "s", $param_username);
            $param_username = trim($_POST["username"]);
            if (mysqli_stmt_execute($stmt_check)) {
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                error_log("Error checking duplicate username: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_check); // Correctly closed here
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }

    // Validate role
    $allowed_roles = ['admin', 'farm_manager', 'warehouse_manager', 'logistics_manager', 'driver', 'customer'];
    if (empty(trim($_POST["role"])) || !in_array(trim($_POST["role"]), $allowed_roles)) {
        $role_err = "Please select a valid role.";
    } else {
        $role = trim($_POST["role"]);
    }

    // NEW LOGIC: Validate customer type ONLY if role is 'customer'
    if ($role == 'customer') {
        $allowed_customer_types = ['direct', 'retailer'];
        if (empty(trim($_POST["customer_type"])) || !in_array(trim($_POST["customer_type"]), $allowed_customer_types)) {
            $customer_type_err = "Please select a valid customer type for customer role.";
        } else {
            $customer_type = trim($_POST["customer_type"]);
        }
    } else {
        // If role is not customer, default customer_type to 'direct' and don't validate
        $customer_type = 'direct'; 
    }

    // Validate email (optional but good practice)
    if (!empty(trim($_POST["email"]))) {
        $sql_check_email = "SELECT user_id FROM users WHERE email = ?";
        if ($stmt_email_check = mysqli_prepare($conn, $sql_check_email)) {
            mysqli_stmt_bind_param($stmt_email_check, "s", $param_email);
            $param_email = trim($_POST["email"]);
            if (mysqli_stmt_execute($stmt_email_check)) {
                mysqli_stmt_store_result($stmt_email_check);
                if (mysqli_stmt_num_rows($stmt_email_check) == 1) {
                    $email_err = "This email is already registered.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                error_log("Error checking duplicate email: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_email_check); // Correctly closed here
        }
    } else {
        $email = NULL;
    }

    $phone = trim($_POST["phone"]);
    if (empty($phone)) $phone = NULL;


    // Check input errors before inserting into database
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($role_err) && empty($email_err) && empty($customer_type_err)) {
        $logged_in_user_id = $_SESSION['user_id'];
        $sql = "INSERT INTO users (username, password_hash, role, customer_type, email, phone, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssssssi", $param_username, $param_password, $param_role, $param_customer_type, $param_email, $param_phone, $param_created_by);

            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            $param_role = $role;
            $param_customer_type = $customer_type;
            $param_email = $email;
            $param_phone = $phone;
            $param_created_by = $logged_in_user_id;

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "User '" . htmlspecialchars($username) . "' created successfully!";
                header("location: " . BASE_URL . "users/index.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error: Could not create user. " . mysqli_error($conn);
                error_log("Error creating user: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Error preparing insert statement: " . mysqli_error($conn);
            error_log("Error preparing user insert statement: " . mysqli_error($conn));
        }
    }
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Add New User</h2>
        <a href="<?php echo BASE_URL; ?>users/index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to User List</a>

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
                <div class="mb-3">
                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>">
                    <div class="invalid-feedback"><?php echo $username_err; ?></div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($password); ?>">
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($confirm_password); ?>">
                        <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" id="role" class="form-select <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Role</option>
                            <option value="admin" <?php echo ($role == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            <option value="farm_manager" <?php echo ($role == 'farm_manager') ? 'selected' : ''; ?>>Farm Manager</option>
                            <option value="warehouse_manager" <?php echo ($role == 'warehouse_manager') ? 'selected' : ''; ?>>Warehouse Manager</option>
                            <option value="logistics_manager" <?php echo ($role == 'logistics_manager') ? 'selected' : ''; ?>>Logistics Manager</option>
                            <option value="driver" <?php echo ($role == 'driver') ? 'selected' : ''; ?>>Driver</option>
                            <option value="customer" <?php echo ($role == 'customer') ? 'selected' : ''; ?>>Customer</option>
                        </select>
                        <div class="invalid-feedback"><?php echo $role_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3" id="customer_type_group" style="display: <?php echo ($role == 'customer') ? 'block' : 'none'; ?>;">
                        <label for="customer_type" class="form-label">Customer Type <span class="text-danger">*</span></label>
                        <select name="customer_type" id="customer_type" class="form-select <?php echo (!empty($customer_type_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Type</option>
                            <option value="direct" <?php echo ($customer_type == 'direct') ? 'selected' : ''; ?>>Direct Customer</option>
                            <option value="retailer" <?php echo ($customer_type == 'retailer') ? 'selected' : ''; ?>>Retailer (30% Discount)</option>
                        </select>
                        <div class="invalid-feedback"><?php echo $customer_type_err; ?></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>">
                    <div class="invalid-feedback"><?php echo $email_err; ?></div>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="text" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>">
                </div>

                <button type="submit" class="btn btn-success"><i class="fas fa-user-plus"></i> Create User</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const customerTypeGroup = document.getElementById('customer_type_group');
    const customerTypeSelect = document.getElementById('customer_type');

    function toggleCustomerTypeVisibility() {
        if (roleSelect.value === 'customer') {
            customerTypeGroup.style.display = 'block';
            customerTypeSelect.setAttribute('required', 'required');
        } else {
            customerTypeGroup.style.display = 'none';
            customerTypeSelect.removeAttribute('required');
            // Reset value for non-customers to avoid submitting an incorrect value
            customerTypeSelect.value = 'direct'; 
        }
    }

    // Initial call on page load
    toggleCustomerTypeVisibility();

    // Event listener for role change
    roleSelect.addEventListener('change', toggleCustomerTypeVisibility);
});
</script>