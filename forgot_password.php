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