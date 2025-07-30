<?php
// This line is CRUCIAL for BASE_URL and $conn to be available
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php"); // Use BASE_URL for redirect
    exit;
}

// Check user role for access control
if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to delete drivers.";
    header("location: " . BASE_URL . "dashboard.php"); // Use BASE_URL for redirect
    exit;
}

// Check if ID parameter is set in the GET request
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $driver_id_to_delete = trim($_GET["id"]);

    // --- IMPORTANT: Check if driver is currently assigned to any active shipments ---
    $sql_check_shipments = "SELECT shipment_id FROM shipments WHERE driver_id = ? AND status IN ('pending', 'assigned', 'picked_up', 'in_transit')";
    if($stmt_check = mysqli_prepare($conn, $sql_check_shipments)){
        mysqli_stmt_bind_param($stmt_check, "i", $param_id);
        $param_id = $driver_id_to_delete; // Use the ID of the driver to be deleted
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if(mysqli_stmt_num_rows($stmt_check) > 0){
            // If the driver is assigned to active shipments, prevent deletion
            $_SESSION['error_message'] = "Cannot delete driver. They are currently assigned to one or more active shipments. Please unassign them first.";
        } else {
            // If not assigned to active shipments, proceed with deletion
            $sql_delete_driver = "DELETE FROM drivers WHERE driver_id = ?";
            if ($stmt_delete = mysqli_prepare($conn, $sql_delete_driver)) {
                mysqli_stmt_bind_param($stmt_delete, "i", $param_id);
                $param_id = $driver_id_to_delete;

                if (mysqli_stmt_execute($stmt_delete)) {
                    $_SESSION['success_message'] = "Driver deleted successfully.";
                } else {
                    $_SESSION['error_message'] = "Error: Could not delete driver. " . mysqli_error($conn);
                    error_log("Error deleting driver: " . mysqli_error($conn)); // Log for debugging
                }
                mysqli_stmt_close($stmt_delete);
            } else {
                $_SESSION['error_message'] = "Error preparing delete statement. " . mysqli_error($conn);
                error_log("Error preparing driver delete statement: " . mysqli_error($conn));
            }
        }
        mysqli_stmt_close($stmt_check); // Close the check statement
    } else {
        $_SESSION['error_message'] = "Error checking driver assignments. " . mysqli_error($conn);
        error_log("Error preparing driver assignment check: " . mysqli_error($conn));
    }
} else {
    // If no ID is provided in the URL
    $_SESSION['error_message'] = "No driver ID provided for deletion.";
}

// Redirect back to the driver list page
header("location: " . BASE_URL . "drivers/index.php"); // Use BASE_URL for redirect
exit();
// Note: mysqli_close($conn) is handled by includes/footer.php
?>