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