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

if (isset($_GET['token']) && !empty(trim($_GET['token']))) {
    $reset_token = trim($_GET['token']);
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (empty(trim($_POST['password']))) {
            $password_err = "Please enter a new password.";
        } elseif (strlen(trim($_POST['password'])) < 6) {
            $password_err = "Password must have at least 6 characters.";
        } else {
            $password = trim($_POST['password']);
        }

        if (empty(trim($_POST['confirm_password']))) {
            $confirm_password_err = "Please confirm the new password.";
        } else {
            $confirm_password = trim($_POST['confirm_password']);
            if (empty($password_err) && ($password != $confirm_password)) {
                $confirm_password_err = "Password did not match.";
            }
        }
        
        if (empty($password_err) && empty($confirm_password_err)) {
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

} else {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (empty(trim($_POST['username_or_email']))) {
            $username_or_email_err = "Please enter your username.";
        } else {
            $username_or_email = trim($_POST['username_or_email']);
            
            $sql_find_user = "SELECT user_id FROM users WHERE username = ? OR email = ?";
            if ($stmt_find = mysqli_prepare($conn, $sql_find_user)) {
                mysqli_stmt_bind_param($stmt_find, "ss", $username_or_email, $username_or_email);
                mysqli_stmt_execute($stmt_find);
                mysqli_stmt_store_result($stmt_find);
                if (mysqli_stmt_num_rows($stmt_find) == 1) {
                    $reset_token = urlencode($username_or_email);
                    $success_message = "A password reset link has been sent to your email (ignore the message this is only demo). <br> Click this link to reset your password: <a href='" . BASE_URL . "forgot_password.php?token=" . $reset_token . "'>Reset Password</a>";
                } else {
                    $error_message = "No user found with that username.";
                }
                mysqli_stmt_close($stmt_find);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Agri-Logistics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css">
    <style>
        html, body {
            height: 100%; /* Ensure html and body take full height */
        }
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f8f9fa; /* Light background */
        }
        .login-container {
             width: 100%;
             max-width: 400px;
             padding: 30px;
             border-radius: 8px;
             box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
             background-color: #ffffff;
        }
        .login-header {
             text-align: center;
             margin-bottom: 25px;
             color: #28a745; /* Green color for agriculture theme */
        }
        .form-control:focus {
             border-color: #28a745;
             box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }
        .btn-primary {
             background-color: #28a745;
             border-color: #28a745;
        }
        .btn-primary:hover {
             background-color: #218838;
             border-color: #1e7e34;
        }
        .alert-danger {
             background-color: #f8d7da;
             color: #721c24;
             border-color: #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="login-header"><?php echo empty($reset_token) ? 'Forgot Password' : 'Reset Password'; ?></h2>
                    
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (empty($reset_token)): ?>
            <p class="text-muted text-center">Enter your username to receive a password reset link on your email (demo).</p>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-3">
                    <label for="username_or_email" class="form-label">Username</label>
                    <input type="text" name="username_or_email" id="username_or_email" class="form-control <?php echo (!empty($username_or_email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username_or_email); ?>">
                    <div class="invalid-feedback"><?php echo $username_or_email_err; ?></div>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Send Reset Link</button>
                </div>
            </form>
        <?php else: ?>
            <p class="text-muted text-center">Enter your new password below.</p>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?token=' . urlencode($reset_token); ?>" method="post">
                 <input type="hidden" name="reset_token" value="<?php echo htmlspecialchars($reset_token); ?>">
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                    <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        <?php endif; ?>
        <div class="text-center mt-3">
             <a href="<?php echo BASE_URL; ?>index.php">Back to Login</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>