<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'farm_manager' && $_SESSION["role"] != 'warehouse_manager') {
    $_SESSION['error_message'] = "You do not have permission to delete inventory records.";
    header("location: ../dashboard.php");
    exit;
}

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $sql = "DELETE FROM inventory WHERE inventory_id = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $param_id);
        $param_id = trim($_GET["id"]);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Inventory record deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error: Could not delete inventory record. " . mysqli_error($conn);
            error_log("Error deleting inventory: " . mysqli_error($conn));
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_message'] = "Error preparing delete statement.";
    }
} else {
    $_SESSION['error_message'] = "Invalid request. No inventory ID provided.";
}

mysqli_close($conn);
header("location: index.php");
exit();
?>