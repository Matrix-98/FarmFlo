<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'farm_manager') {
    $_SESSION['error_message'] = "You do not have permission to delete products.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $product_id_to_delete = trim($_GET["id"]);

    // The database's ON DELETE CASCADE foreign key constraints will handle
    // deleting related records in 'inventory', 'order_products', and 'shipment_products'.
    // No need for manual checks here, as the database takes care of it automatically.

    $sql = "DELETE FROM products WHERE product_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $param_id);
        $param_id = $product_id_to_delete;

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Product and all its associated inventory records, order items, and shipment items deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error: Could not delete product. " . mysqli_error($conn);
            error_log("Error deleting product: " . mysqli_error($conn));
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_message'] = "Error preparing delete statement.";
    }
} else {
    $_SESSION['error_message'] = "No product ID provided for deletion.";
}

header("location: " . BASE_URL . "products/index.php");
exit();