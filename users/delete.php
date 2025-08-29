<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to delete users.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $user_id_to_delete = trim($_GET["id"]);

    // Prevent deletion of the currently logged-in user
    if ($user_id_to_delete == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "You cannot delete your own user account.";
        header("location: " . BASE_URL . "users/index.php");
        exit;
    }

    // --- IMPORTANT FIX: Check for dependent records before deletion ---
    $can_delete = true;
    $error_message_local = '';

    // Check if the user has any orders
    $sql_check_orders = "SELECT order_id FROM orders WHERE customer_id = ?";
    if ($stmt_orders = mysqli_prepare($conn, $sql_check_orders)) {
        mysqli_stmt_bind_param($stmt_orders, "i", $user_id_to_delete);
        mysqli_stmt_execute($stmt_orders);
        mysqli_stmt_store_result($stmt_orders);
        if (mysqli_stmt_num_rows($stmt_orders) > 0) {
            $error_message_local = "Cannot delete this user because they have placed " . mysqli_stmt_num_rows($stmt_orders) . " order(s). Please handle their orders first.";
            $can_delete = false;
        }
        mysqli_stmt_close($stmt_orders);
    }

    // Note: The driver's user_id is set to NULL on delete, so that's not a block.
    // The documents table uploaded_by is set to NULL on delete, so that's not a block.

    if ($can_delete) {
        $sql = "DELETE FROM users WHERE user_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $param_id);
            $param_id = $user_id_to_delete;

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "User deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Error: Could not delete user. " . mysqli_error($conn);
                error_log("Error deleting user: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Error preparing delete statement.";
        }
    } else {
        // Set the error message for display on the next page
        $_SESSION['error_message'] = $error_message_local;
    }
} else {
    $_SESSION['error_message'] = "No user ID provided for deletion.";
}

header("location: " . BASE_URL . "users/index.php");
exit();

#