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