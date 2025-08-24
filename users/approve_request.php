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

        // 2. Insert into users table
        $sql_insert_user = "INSERT INTO users (user_code, username, password_hash, role, customer_type, email, phone, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt_insert_user = mysqli_prepare($conn, $sql_insert_user)) {
            $role_customer = 'customer'; // New users from requests are always 'customer' role
            $user_code = generateUserId(); // Generate unique user code
            mysqli_stmt_bind_param($stmt_insert_user, "sssssssi",
                $user_code,
                $request_data['username'],
                $request_data['password_hash'],
                $role_customer,
                $request_data['customer_type'],
                $request_data['email'],
                $request_data['phone'],
                $logged_in_admin_id
            );

            if (!mysqli_stmt_execute($stmt_insert_user)) {
                throw new Exception("Error creating user account: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_insert_user);
        } else {
            throw new Exception("Error preparing user insert statement: " . mysqli_error($conn));
        }

        // 3. Delete request from registration_requests table
        $sql_delete_request = "DELETE FROM registration_requests WHERE request_id = ?";
        if ($stmt_delete = mysqli_prepare($conn, $sql_delete_request)) {
            mysqli_stmt_bind_param($stmt_delete, "i", $request_id);
            if (!mysqli_stmt_execute($stmt_delete)) {
                throw new Exception("Error deleting registration request: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_delete);
        } else {
            throw new Exception("Error preparing request delete statement: " . mysqli_error($conn));
        }

        mysqli_commit($conn);
        $_SESSION['success_message'] = "Customer request for '" . htmlspecialchars($request_data['username']) . "' approved successfully! User account created.";

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Approval failed: " . $e->getMessage();
        error_log("Customer request approval failed: " . $e->getMessage());
    }
} else {
    $_SESSION['error_message'] = "No request ID provided for approval.";
}

header("location: " . BASE_URL . "users/manage_requests.php");
exit();