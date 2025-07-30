<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to edit users.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Edit User";
$current_page = "users";

$user_id_param = "";
$username = $role = $email = $phone = $customer_type = ""; // Added $customer_type
$password_err = $confirm_password_err = $username_err = $email_err = $role_err = $customer_type_err = "";

// Initialize audit trail variables for display
$created_at = $updated_at = $created_by_username = $updated_by_username = '';

// Fetch existing user data
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $user_id_param = trim($_GET["id"]);

    // FIX: Add 'customer_type' to the SELECT query
    $sql_fetch_user = "SELECT username, role, customer_type, email, phone, created_at, updated_at, created_by, updated_by FROM users WHERE user_id = ?";
    if ($stmt_fetch = mysqli_prepare($conn, $sql_fetch_user)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $param_id);
        $param_id = $user_id_param;

        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);
            if (mysqli_num_rows($result_fetch) == 1) {
                $row = mysqli_fetch_assoc($result_fetch);
                $username = $row["username"];
                $role = $row["role"];
                $customer_type = $row["customer_type"]; // NEW: Fetch customer type
                $email = $row["email"];
                $phone = $row["phone"];
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
                $_SESSION['error_message'] = "User not found.";
                header("location: " . BASE_URL . "users/index.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "Oops! Something went wrong fetching user data.";
            error_log("Error executing user fetch: " . mysqli_error($conn));
            header("location: " . BASE_URL . "users/index.php");
            exit();
        }
        mysqli_stmt_close($stmt_fetch);
    } else {
        $_SESSION['error_message'] = "Error preparing user fetch statement.";
        error_log("Error preparing user fetch statement: " . mysqli_error($conn));
        header("location: " . BASE_URL . "users/index.php");
        exit();
    }
} else if ($_SERVER["REQUEST_METHOD"] != "POST") { // Redirect if no ID provided in GET, and not a POST request
    $_SESSION['error_message'] = "Invalid request. No user ID provided.";
    header("location: " . BASE_URL . "users/index.php");
    exit();
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST["user_id"];

    // Validate username (allow current username to be unchanged)
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        $username_new = trim($_POST["username"]);
        $sql_check_username = "SELECT user_id FROM users WHERE username = ? AND user_id != ?";
        if ($stmt_check = mysqli_prepare($conn, $sql_check_username)) {
            mysqli_stmt_bind_param($stmt_check, "si", $param_username, $param_user_id);
            $param_username = $username_new;
            $param_user_id = $user_id;
            if (mysqli_stmt_execute($stmt_check)) {
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) == 1) {
                    $username_err = "This username is already taken by another user.";
                } else {
                    $username = $username_new;
                }
            } else {
                error_log("Error checking duplicate username during edit: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_check);
        }
    }

    // Validate password (optional update)
    $password_new = trim($_POST["password"]);
    $confirm_password_new = trim($_POST["confirm_password"]);
    $hashed_password = NULL;

    if (!empty($password_new)) {
        if (strlen($password_new) < 6) {
            $password_err = "Password must have at least 6 characters.";
        } else {
            if ($password_new != $confirm_password_new) {
                $confirm_password_err = "Password did not match.";
            } else {
                $hashed_password = password_hash($password_new, PASSWORD_DEFAULT);
            }
        }
    }

    // Validate role
    $allowed_roles = ['admin', 'farm_manager', 'warehouse_manager', 'logistics_manager', 'driver', 'customer'];
    if (empty(trim($_POST["role"])) || !in_array(trim($_POST["role"]), $allowed_roles)) {
        $role_err = "Please select a valid role.";
    } else {
        $role = trim($_POST["role"]);
    }

    // Special check: If admin is editing themselves, prevent changing role to non-admin
    if ($user_id == $_SESSION['user_id'] && $_SESSION['role'] == 'admin' && $role != 'admin') {
        $role_err = "You cannot change your own role from 'Admin' to a non-admin role.";
        $role = 'admin';
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
        $customer_type = 'direct'; // Default to 'direct' if role is not customer
    }
    
    // Validate email
    $email_new = trim($_POST["email"]);
    if (!empty($email_new) && !filter_var($email_new, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else if (!empty($email_new)) {
        $sql_check_email = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        if ($stmt_email_check = mysqli_prepare($conn, $sql_check_email)) {
            mysqli_stmt_bind_param($stmt_email_check, "si", $param_email, $param_user_id);
            $param_email = $email_new;
            $param_user_id = $user_id;
            if (mysqli_stmt_execute($stmt_email_check)) {
                mysqli_stmt_store_result($stmt_email_check);
                if (mysqli_stmt_num_rows($stmt_email_check) == 1) {
                    $email_err = "This email is already registered to another user.";
                } else {
                    $email = $email_new;
                }
            } else {
                error_log("Error checking duplicate email during edit: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_email_check);
        }
    } else {
        $email = NULL;
    }

    $phone = trim($_POST["phone"]);
    if (empty($phone)) $phone = NULL;


    // Check input errors before updating database
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($role_err) && empty($email_err) && empty($customer_type_err)) {
        $sql_update_parts = [];
        $bind_types = "";
        $bind_params = [];
        $logged_in_user_id = $_SESSION['user_id'];

        $sql_update_parts[] = "username = ?";
        $bind_types .= "s";
        $bind_params[] = $username;

        if ($hashed_password !== NULL) {
            $sql_update_parts[] = "password_hash = ?";
            $bind_types .= "s";
            $bind_params[] = $hashed_password;
        }

        $sql_update_parts[] = "role = ?";
        $bind_types .= "s";
        $bind_params[] = $role;
        
        $sql_update_parts[] = "customer_type = ?";
        $bind_types .= "s";
        $bind_params[] = $customer_type;

        $sql_update_parts[] = "email = ?";
        $bind_types .= "s";
        $bind_params[] = $email;

        $sql_update_parts[] = "phone = ?";
        $bind_types .= "s";
        $bind_params[] = $phone;

        $sql_update_parts[] = "updated_by = ?";
        $bind_types .= "i";
        $bind_params[] = $logged_in_user_id;

        $sql_update = "UPDATE users SET " . implode(", ", $sql_update_parts) . " WHERE user_id = ?";
        $bind_types .= "i";
        $bind_params[] = $user_id;

        if ($stmt = mysqli_prepare($conn, $sql_update)) {
            mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_params);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "User '" . htmlspecialchars($username) . "' updated successfully!";
                if ($user_id == $_SESSION['user_id']) {
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role;
                }
                header("location: " . BASE_URL . "users/index.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error: Could not update user. " . mysqli_error($conn);
                error_log("Error updating user: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Error preparing update statement: " . mysqli_error($conn);
            error_log("Error preparing user update statement: " . mysqli_error($conn));
        }
    }
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Edit User: <?php echo htmlspecialchars($username); ?></h2>
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
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id_param); ?>">

                <div class="mb-3">
                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>">
                    <div class="invalid-feedback"><?php echo $username_err; ?></div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">New Password (Leave blank to keep current)</label>
                        <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                        <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" id="role" class="form-select <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>"
                            <?php echo ($user_id_param == $_SESSION['user_id'] && $_SESSION['role'] == 'admin') ? 'disabled' : ''; ?> >
                            <option value="">Select Role</option>
                            <option value="admin" <?php echo ($role == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            <option value="farm_manager" <?php echo ($role == 'farm_manager') ? 'selected' : ''; ?>>Farm Manager</option>
                            <option value="warehouse_manager" <?php echo ($role == 'warehouse_manager') ? 'selected' : ''; ?>>Warehouse Manager</option>
                            <option value="logistics_manager" <?php echo ($role == 'logistics_manager') ? 'selected' : ''; ?>>Logistics Manager</option>
                            <option value="driver" <?php echo ($role == 'driver') ? 'selected' : ''; ?>>Driver</option>
                            <option value="customer" <?php echo ($role == 'customer') ? 'selected' : ''; ?>>Customer</option>
                        </select>
                        <?php if ($user_id_param == $_SESSION['user_id'] && $_SESSION['role'] == 'admin'): ?>
                            <input type="hidden" name="role" value="admin">
                            <small class="form-text text-muted">You cannot change your own admin role.</small>
                        <?php endif; ?>
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

                <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Update User</button>
            </form>
            <?php if (isset($created_at) || isset($updated_at)): ?>
            <div class="mt-3 border-top pt-3 text-muted small">
                Created: <?php echo htmlspecialchars($created_at); ?> by <?php echo htmlspecialchars($created_by_username ?: 'N/A'); ?><br>
                Last Updated: <?php echo htmlspecialchars($updated_at); ?> by <?php echo htmlspecialchars($updated_by_username ?: 'N/A'); ?>
            </div>
            <?php endif; ?>
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
            customerTypeSelect.setAttribute('required', 'required'); // Make required for customers
        } else {
            customerTypeGroup.style.display = 'none';
            customerTypeSelect.removeAttribute('required'); // Not required for non-customers
            customerTypeSelect.value = 'direct'; // Default to 'direct' or empty if not customer
        }
    }

    // Initial call on page load
    toggleCustomerTypeVisibility();

    // Event listener for role change
    roleSelect.addEventListener('change', toggleCustomerTypeVisibility);
});
</script>