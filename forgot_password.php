<?php
require_once 'config/db.php';

$page_title = "Forgot Password";
$username_or_email = '';
$username_or_email_err = '';
$password = '';
$confirm_password = '';
$password_err = '';
$confirm_password_err = '';
$reset_token = '';
$error_message = '';
$success_message = '';

// Check if a reset token is provided in the URL
if (isset($_GET['token']) && !empty(trim($_GET['token']))) {
    $reset_token = trim($_GET['token']);

    // Process new password submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Validate new password against strong policy
        if (empty(trim($_POST['password']))) {
            $password_err = "Please enter a new password.";
        } elseif (strlen(trim($_POST['password'])) < 8) { // Minimum 8 characters
            $password_err = "Password must be at least 8 characters long.";
        } elseif (!preg_match('/[A-Z]/', $_POST["password"])) { // At least one uppercase
            $password_err = "Password must contain at least one uppercase letter.";
        } elseif (!preg_match('/[a-z]/', $_POST["password"])) { // At least one lowercase
            $password_err = "Password must contain at least one lowercase letter.";
        } elseif (!preg_match('/[0-9]/', $_POST["password"])) { // At least one digit
            $password_err = "Password must contain at least one digit.";
        } elseif (!preg_match('/[^A-Za-z0-9]/', $_POST["password"])) { // At least one special character
            $password_err = "Password must contain at least one special character (e.g., !@#$%^&*).";
        } else {
            $password = trim($_POST['password']);
        }

        // Validate confirm password
        if (empty(trim($_POST['confirm_password']))) {
            $confirm_password_err = "Please confirm the new password.";
        } else {
            $confirm_password = trim($_POST['confirm_password']);
            if (empty($password_err) && ($password != $confirm_password)) {
                $confirm_password_err = "Password did not match.";
            }
        }

        if (empty($password_err) && empty($confirm_password_err)) {
            // In a real application, you'd verify the token against a database table
            // where you store generated tokens and their expiry dates.
            // For this demo, we simplified by using the username as the token.
            $sql_check_token = "SELECT user_id FROM users WHERE username = ?";
            if ($stmt_check = mysqli_prepare($conn, $sql_check_token)) {
                mysqli_stmt_bind_param($stmt_check, "s", $reset_token);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) == 1) {
                    $user_id = 0;
                    mysqli_stmt_bind_result($stmt_check, $user_id);
                    mysqli_stmt_fetch($stmt_check);

                    $sql_update_password = "UPDATE users SET password_hash = ? WHERE user_id = ?";
                    if ($stmt_update = mysqli_prepare($conn, $sql_update_password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        mysqli_stmt_bind_param($stmt_update, "si", $hashed_password, $user_id);
                        if (mysqli_stmt_execute($stmt_update)) {
                            $success_message = "Your password has been successfully reset. You can now log in with your new password.";
                            // In a real application, invalidate the token here (e.g., delete from tokens table).
                        } else {
                            $error_message = "Error resetting password: " . mysqli_error($conn);
                        }
                        mysqli_stmt_close($stmt_update);
                    }
                } else {
                    $error_message = "Invalid or expired reset token.";
                }
                mysqli_stmt_close($stmt_check);
            }
        }
    }

} else { // No token provided in URL, show password reset request form
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (empty(trim($_POST['username_or_email']))) {
            $username_or_email_err = "Please enter your username.";
        } else {
            $username_or_email = trim($_POST['username_or_email']);

            // Check if user exists (by username or email)
            $sql_find_user = "SELECT user_id FROM users WHERE username = ? OR email = ?";
            if ($stmt_find = mysqli_prepare($conn, $sql_find_user)) {
                mysqli_stmt_bind_param($stmt_find, "ss", $username_or_email, $username_or_email);
                mysqli_stmt_execute($stmt_find);
                mysqli_stmt_store_result($stmt_find);
                if (mysqli_stmt_num_rows($stmt_find) == 1) {
                    // This is where you would normally generate a secure token and email it to the user.
                    // For this project, we'll simplify and display a dummy link with the username as a token.
                    // THIS IS NOT SECURE FOR A REAL APP, but is a functional demonstration.
                    $reset_token = urlencode($username_or_email);
                    $success_message = "A password reset link has been sent to your email (ignore the message this is only demo). <br> Click this link to reset your password: <a href='" . BASE_URL . "forgot_password.php?token=" . $reset_token . "'>Reset Password</a>";
                } else {
                    $username_or_email_err = "No account found with that username or email.";
                }
                mysqli_stmt_close($stmt_find);
            } else {
                $error_message = "Something went wrong. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - Agri-Logistics</title>
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
            content: '🔐';
            position: absolute;
            top: 20%;
            left: 10%;
            font-size: 3rem;
            animation: floatIcon 6s ease-in-out infinite;
            opacity: 0.3;
        }

        .login-wrapper::after {
            content: '📧';
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
            max-width: 420px;
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

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #2E7D32;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus + .input-icon {
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
            <h3>Reset Password</h3>
            <p class="text-muted">
                <?php if (!empty($reset_token)): ?>
                    Enter your new password
                <?php else: ?>
                    Enter your username or email to reset password
                <?php endif; ?>
            </p>
        </div>

        <?php if (!empty($success_message)) : ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)) : ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (!empty($reset_token)): ?>
            <!-- Reset Password Form -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?token=' . $reset_token); ?>" method="post">
                <div class="mb-3">
                    <input type="password" name="password" placeholder="New Password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
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
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                    <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-success btn-lg">Reset Password</button>
                </div>

                <div class="text-center">
                    <a href="index.php" class="text-success fw-bold">Back to Login</a>
                </div>
            </form>
        <?php else: ?>
            <!-- Request Reset Form -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-3 position-relative">
                    <span class="input-icon"><i class="fas fa-user"></i></span>
                    <input type="text" name="username_or_email" placeholder="Username or Email" class="form-control <?php echo (!empty($username_or_email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username_or_email; ?>">
                    <div class="invalid-feedback"><?php echo $username_or_email_err; ?></div>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-success btn-lg">Send Reset Link</button>
                </div>

                <div class="text-center">
                    <a href="index.php" class="text-success fw-bold">Back to Login</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
