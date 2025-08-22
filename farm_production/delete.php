<?php
require_once '../config/db.php';
require_once '../utils/id_generator.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'farm_manager') {
    $_SESSION['error_message'] = "You do not have permission to delete farm production data.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $production_id_to_delete = trim($_GET["id"]);

    // The database's foreign key constraints will handle
    // any related deletions if necessary.

    $sql = "DELETE FROM farm_production WHERE production_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $param_id);
        $param_id = $production_id_to_delete;

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Farm production record deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error: Could not delete production record. " . mysqli_error($conn);
            error_log("Error deleting production record: " . mysqli_error($conn));
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_message'] = "Error preparing delete statement.";
    }
} else {
    $_SESSION['error_message'] = "No production record ID provided for deletion.";
}

header("location: " . BASE_URL . "farm_production/index.php");
exit();