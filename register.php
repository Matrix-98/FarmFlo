<?php
require_once 'config/db.php'; // Includes BASE_URL and starts session

$page_title = "Customer Registration";

$username = $password = $confirm_password = $customer_type = $email = $phone = "";
$username_err = $password_err = $confirm_password_err = $customer_type_err = $email_err = $phone_err = "";
$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate Username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        // Check if username already exists in users or pending requests
        $sql_check_username = "SELECT user_id FROM users WHERE username = ? UNION SELECT request_id FROM registration_requests WHERE username = ?";
        if ($stmt_check = mysqli_prepare($conn, $sql_check_username)) {
            mysqli_stmt_bind_param($stmt_check, "ss", $param_username, $param_username);
            $param_username = trim($_POST["username"]);
            if (mysqli_stmt_execute($stmt_check)) {
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) > 0) {
                    $username_err = "This username is already taken or in review.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                error_log("Error checking duplicate username during registration: " . mysqli_error($conn));
                $error_message = "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt_check);
        }
    }

    // Validate Password Policy
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 8) {
        $password_err = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $_POST["password"])) {
        $password_err = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $_POST["password"])) {
        $password_err = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $_POST["password"])) {
        $password_err = "Password must contain at least one digit.";
    } elseif (!preg_match('/[^A-Za-z0-9]/', $_POST["password"])) {
        $password_err = "Password must contain at least one special character (e.g., !@#$%^&*).";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate Confirm Password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }

    // Validate Customer Type
    $allowed_customer_types = ['direct', 'retailer'];
    if (empty(trim($_POST["customer_type"])) || !in_array(trim($_POST["customer_type"]), $allowed_customer_types)) {
        $customer_type_err = "Please select a valid customer type.";
    } else {
        $customer_type = trim($_POST["customer_type"]);
    }

    // Validate Email and check for uniqueness
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email address.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        $sql_check_email = "SELECT user_id FROM users WHERE email = ? UNION SELECT request_id FROM registration_requests WHERE email = ?";
        if ($stmt_email_check = mysqli_prepare($conn, $sql_check_email)) {
            mysqli_stmt_bind_param($stmt_email_check, "ss", $param_email, $param_email);
            $param_email = trim($_POST["email"]);
            if (mysqli_stmt_execute($stmt_email_check)) {
                mysqli_stmt_store_result($stmt_email_check);
                if (mysqli_stmt_num_rows($stmt_email_check) > 0) {
                    $email_err = "This email is already registered or in review.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                error_log("Error checking duplicate email during registration: " . mysqli_error($conn));
                $error_message = "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt_email_check);
        }
    }

    // Validate Phone - now mandatory
    if (empty(trim($_POST["phone"]))) {
        $phone_err = "Please enter your phone number.";
    } else {
        $phone = trim($_POST["phone"]);
    }

    // If all validations pass, insert into registration_requests
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err) && 
        empty($customer_type_err) && empty($email_err) && empty($phone_err)) {
        
        $sql = "INSERT INTO registration_requests (username, password_hash, customer_type, email, phone) VALUES (?, ?, ?, ?, ?)";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssss", $param_username, $param_password, $param_customer_type, $param_email, $param_phone);
            
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            $param_customer_type = $customer_type;
            $param_email = $email;
            $param_phone = $phone;
            
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Registration request submitted successfully! Your account will be reviewed by an administrator. You will receive an email notification once approved.";
                // Clear form data after successful submission
                $username = $password = $confirm_password = $customer_type = $email = $phone = "";
            } else {
                $error_message = "Something went wrong. Please try again later.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register - Agri-Logistics</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
body {
    background: linear-gradient(135deg, #2E7D32 0%, #4CAF50 50%, #81C784 100%);
    font-family: 'Segoe UI', sans-serif;
    position: relative;
    overflow-x: hidden;
}

/* Animated background elements */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    animation: float 20s ease-in-out infinite;
    z-index: -1;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(180deg); }
}

.login-wrapper {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
}

/* Floating icons */
.login-wrapper::before {
    content: '🌾';
    position: absolute;
    top: 20%;
    left: 10%;
    font-size: 3rem;
    animation: floatIcon 6s ease-in-out infinite;
    opacity: 0.3;
}

.login-wrapper::after {
    content: '🚛';
    position: absolute;
    bottom: 20%;
    right: 10%;
    font-size: 2.5rem;
    animation: floatIcon 8s ease-in-out infinite reverse;
    opacity: 0.3;
}

@keyframes floatIcon {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-30px) rotate(10deg); }
}

.login-card {
    background: rgba(255, 255, 255, 0.95);
    padding: 40px 35px;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    width: 100%;
    max-width: 480px;
    animation: fadeInUp 0.8s ease;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
}

.login-header {
    text-align: center;
    margin-bottom: 30px;
    position: relative;
}

.login-header img {
    width: 80px;
    margin-bottom: 15px;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
    animation: pulse 2s ease-in-out infinite;
}

.login-header h3 {
    font-weight: bold;
    color: #2d6a4f;
    font-size: 2.2rem;
    margin-bottom: 8px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.login-header p {
    color: #6c757d;
    font-size: 1.1rem;
    margin: 0;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.form-control {
    padding-left: 45px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: rgba(255,255,255,0.9);
}

.form-control:focus {
    border-color: #2E7D32;
    box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.25);
    background: rgba(255,255,255,1);
}

.form-select {
    padding-left: 45px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: rgba(255,255,255,0.9);
}

.form-select:focus {
    border-color: #2E7D32;
    box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.25);
    background: rgba(255,255,255,1);
}

.input-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #2E7D32;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.form-control:focus + .input-icon,
.form-select:focus + .input-icon {
    color: #1B5E20;
    transform: translateY(-50%) scale(1.1);
}

.btn-success {
    background: linear-gradient(135deg, #2E7D32 0%, #4CAF50 100%);
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1.1rem;
    padding: 12px 24px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
}

.btn-success:hover {
    background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(46, 125, 50, 0.4);
}

.btn-success:active {
    transform: translateY(0);
    box-shadow: 0 2px 10px rgba(46, 125, 50, 0.3);
}

.alert {
    font-size: 0.9rem;
    border-radius: 12px;
}

.alert-success {
    background: rgba(40, 167, 69, 0.1);
    border: 1px solid rgba(40, 167, 69, 0.2);
    color: #155724;
}

.alert-danger {
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid rgba(220, 53, 69, 0.2);
    color: #721c24;
}

a {
    text-decoration: none;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.password-requirements {
    background: rgba(255,255,255,0.8);
    border-radius: 8px;
    padding: 10px;
    margin-top: 5px;
    font-size: 0.85rem;
    color: #6c757d;
}

.password-requirements ul {
    margin: 5px 0 0 0;
    padding-left: 20px;
}

.password-requirements li {
    margin-bottom: 2px;
}
</style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <img src="images/logo.png" alt="Logo">
            <h3>Join FarmFlo</h3>
            <p class="text-muted">Create your customer account</p>
        </div>

        <?php if (!empty($success_message)) : ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)) : ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-3 position-relative">
                <span class="input-icon"><i class="fas fa-user"></i></span>
                <input type="text" name="username" placeholder="Username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                <div class="invalid-feedback"><?php echo $username_err; ?></div>
            </div>

            <div class="mb-3 position-relative">
                <span class="input-icon"><i class="fas fa-envelope"></i></span>
                <input type="email" name="email" placeholder="Email Address" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                <div class="invalid-feedback"><?php echo $email_err; ?></div>
            </div>

            <div class="mb-3 position-relative">
                <span class="input-icon"><i class="fas fa-phone"></i></span>
                <input type="tel" name="phone" placeholder="Phone Number" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $phone; ?>">
                <div class="invalid-feedback"><?php echo $phone_err; ?></div>
            </div>

            <div class="mb-3 position-relative">
                <span class="input-icon"><i class="fas fa-users"></i></span>
                <select name="customer_type" class="form-select <?php echo (!empty($customer_type_err)) ? 'is-invalid' : ''; ?>">
                    <option value="">Select Customer Type</option>
                    <option value="direct" <?php echo ($customer_type == 'direct') ? 'selected' : ''; ?>>Direct Customer</option>
                    <option value="retailer" <?php echo ($customer_type == 'retailer') ? 'selected' : ''; ?>>Retailer</option>
                </select>
                <div class="invalid-feedback"><?php echo $customer_type_err; ?></div>
            </div>

            <div class="mb-3">
                <input type="password" name="password" placeholder="Password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                <div class="invalid-feedback"><?php echo $password_err; ?></div>
                <div class="password-requirements">
                    <strong>Password Requirements:</strong>
                    <ul>
                        <li>At least 8 characters long</li>
                        <li>One uppercase letter (A-Z)</li>
                        <li>One lowercase letter (a-z)</li>
                        <li>One digit (0-9)</li>
                        <li>One special character (!@#$%^&*)</li>
                    </ul>
                </div>
            </div>

            <div class="mb-3">
                <input type="password" name="confirm_password" placeholder="Confirm Password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
            </div>

            <div class="d-grid gap-2 mb-3">
                <button type="submit" class="btn btn-success btn-lg">Create Account</button>
            </div>

            <div class="text-center">
                <span class="text-muted">Already have an account?</span>
                <a href="index.php" class="text-success fw-bold ms-2">Sign In</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
