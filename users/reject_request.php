<?php
require_once '../config/db.php'; // Correct path from users/ folder

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Access control: Only Admin can reject requests
if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to reject customer requests.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $request_id = trim($_GET["id"]);

    mysqli_begin_transaction($conn);

    try {
        // 1. Delete request from registration_requests table
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
        $_SESSION['success_message'] = "Customer request rejected successfully.";

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Rejection failed: " . $e->getMessage();
        error_log("Customer request rejection failed: " . $e->getMessage());
    }
} else {
    $_SESSION['error_message'] = "No request ID provided for rejection.";
}

header("location: " . BASE_URL . "users/manage_requests.php");
exit();

#