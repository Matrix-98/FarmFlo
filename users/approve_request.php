<?php
require_once '../config/db.php'; // Correct path from users/ folder
require_once '../utils/id_generator.php'; // Include ID generator for user codes

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Access control: Only Admin can approve requests
if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to approve customer requests.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $request_id = trim($_GET["id"]);
    $logged_in_admin_id = $_SESSION['user_id'];

    mysqli_begin_transaction($conn);

    try {
        // 1. Fetch request details
        $sql_fetch_request = "SELECT username, password_hash, customer_type, email, phone FROM registration_requests WHERE request_id = ?";
        if ($stmt_fetch = mysqli_prepare($conn, $sql_fetch_request)) {
            mysqli_stmt_bind_param($stmt_fetch, "i", $request_id);
            mysqli_stmt_execute($stmt_fetch);
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);
            $request_data = mysqli_fetch_assoc($result_fetch);
            mysqli_stmt_close($stmt_fetch);

            if (!$request_data) {
                throw new Exception("Registration request not found or already processed.");
            }
        } else {
            throw new Exception("Error preparing request fetch statement: " . mysqli_error($conn));
        }
