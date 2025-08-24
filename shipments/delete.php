<?php
require_once '../config/db.php'; // CRUCIAL for BASE_URL and $conn

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to delete shipments.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $shipment_id_to_delete = trim($_GET["id"]);

    // Start transaction for atomicity
    mysqli_begin_transaction($conn);

    try {
        // First, delete related records in shipment_products table
        $sql_delete_products = "DELETE FROM shipment_products WHERE shipment_id = ?";
        if ($stmt_products = mysqli_prepare($conn, $sql_delete_products)) {
            mysqli_stmt_bind_param($stmt_products, "i", $param_id);
            $param_id = $shipment_id_to_delete;
            if (!mysqli_stmt_execute($stmt_products)) {
                throw new Exception("Error deleting associated products: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_products);
        } else {
            throw new Exception("Error preparing product deletion statement.");
        }

        // Second, delete related records in tracking_data table
        $sql_delete_tracking = "DELETE FROM tracking_data WHERE shipment_id = ?";
        if ($stmt_tracking = mysqli_prepare($conn, $sql_delete_tracking)) {
            mysqli_stmt_bind_param($stmt_tracking, "i", $param_id);
            $param_id = $shipment_id_to_delete;
            if (!mysqli_stmt_execute($stmt_tracking)) {
                throw new Exception("Error deleting associated tracking data: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_tracking);
        } else {
            throw new Exception("Error preparing tracking data deletion statement.");
        }
        
        // Third, delete related records in documents table
        $sql_delete_documents = "DELETE FROM documents WHERE shipment_id = ?";
        if ($stmt_documents = mysqli_prepare($conn, $sql_delete_documents)) {
            mysqli_stmt_bind_param($stmt_documents, "i", $param_id);
            $param_id = $shipment_id_to_delete;
            if (!mysqli_stmt_execute($stmt_documents)) {
                throw new Exception("Error deleting associated documents: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_documents);
        } else {
            throw new Exception("Error preparing document deletion statement.");
        }

        // Finally, delete the shipment record itself
        // No audit needed here as the record is gone, but the 'updated_by' user is the one who deleted it.
        $sql_delete_shipment = "DELETE FROM shipments WHERE shipment_id = ?";
        if ($stmt_shipment = mysqli_prepare($conn, $sql_delete_shipment)) {
            mysqli_stmt_bind_param($stmt_shipment, "i", $param_id);
            $param_id = $shipment_id_to_delete;
            if (!mysqli_stmt_execute($stmt_shipment)) {
                throw new Exception("Error deleting shipment: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_shipment);
        } else {
            throw new Exception("Error preparing shipment deletion statement.");
        }

        mysqli_commit($conn);
        
        // Get shipment code for the success message
        $shipment_code = 'N/A';
        $sql_shipment_code = "SELECT shipment_code FROM shipments WHERE shipment_id = ?";
        if ($stmt_shipment_code = mysqli_prepare($conn, $sql_shipment_code)) {
            mysqli_stmt_bind_param($stmt_shipment_code, "i", $shipment_id_to_delete);
            mysqli_stmt_execute($stmt_shipment_code);
            $result_shipment_code = mysqli_stmt_get_result($stmt_shipment_code);
            if ($row_shipment_code = mysqli_fetch_assoc($result_shipment_code)) {
                $shipment_code = $row_shipment_code['shipment_code'];
            }
            mysqli_stmt_close($stmt_shipment_code);
        }
        
        $_SESSION['success_message'] = "Shipment: " . $shipment_code . " and all associated data deleted successfully.";

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Deletion failed: " . $e->getMessage();
        error_log("Shipment deletion failed: " . $e->getMessage());
    }
} else {
    $_SESSION['error_message'] = "Invalid request. No shipment ID provided.";
}

header("location: " . BASE_URL . "shipments/index.php"); // Use BASE_URL for redirect
exit();
// Note: mysqli_close($conn) is handled by includes/footer.php
?>