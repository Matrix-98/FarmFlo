<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Access control: Only Admin and Logistics Manager can delete orders
if (!in_array($_SESSION["role"], ['admin', 'logistics_manager'])) {
    $_SESSION['error_message'] = "You do not have permission to delete orders.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $order_id_to_delete = trim($_GET["id"]);

    // Start transaction for atomicity
    mysqli_begin_transaction($conn);

    try {
        // First, check if the order is linked to any active shipments.
        // A logistics manager would unassign/delete the shipment first.
        $sql_check_shipment = "SELECT shipment_id FROM shipments WHERE order_id = ?";
        if ($stmt_check_shipment = mysqli_prepare($conn, $sql_check_shipment)) {
            mysqli_stmt_bind_param($stmt_check_shipment, "i", $param_order_id);
            $param_order_id = $order_id_to_delete;
            mysqli_stmt_execute($stmt_check_shipment);
            mysqli_stmt_store_result($stmt_check_shipment);

            if (mysqli_stmt_num_rows($stmt_check_shipment) > 0) {
                throw new Exception("Cannot delete order. It is currently linked to an active shipment. Please delete the shipment first.");
            }
            mysqli_stmt_close($stmt_check_shipment);
        } else {
             throw new Exception("Error checking for associated shipments: " . mysqli_error($conn));
        }

        // Now, delete related records in order_products table
        $sql_delete_products = "DELETE FROM order_products WHERE order_id = ?";
        if ($stmt_products = mysqli_prepare($conn, $sql_delete_products)) {
            mysqli_stmt_bind_param($stmt_products, "i", $param_id);
            $param_id = $order_id_to_delete;
            if (!mysqli_stmt_execute($stmt_products)) {
                throw new Exception("Error deleting associated order products: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_products);
        } else {
            throw new Exception("Error preparing order products deletion statement.");
        }

        // Finally, delete the order record itself
        $sql_delete_order = "DELETE FROM orders WHERE order_id = ?";
        if ($stmt_order = mysqli_prepare($conn, $sql_delete_order)) {
            mysqli_stmt_bind_param($stmt_order, "i", $param_id);
            $param_id = $order_id_to_delete;
            if (!mysqli_stmt_execute($stmt_order)) {
                throw new Exception("Error deleting order: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_order);
        } else {
            throw new Exception("Error preparing order deletion statement.");
        }

        mysqli_commit($conn);
        $_SESSION['success_message'] = "Order #" . $order_id_to_delete . " and all associated data deleted successfully.";

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Deletion failed: " . $e->getMessage();
        error_log("Order deletion failed: " . $e->getMessage());
    }
} else {
    $_SESSION['error_message'] = "Invalid request. No order ID provided.";
}

header("location: " . BASE_URL . "orders/index.php");
exit();
// Note: mysqli_close($conn) is handled by includes/footer.php
?>