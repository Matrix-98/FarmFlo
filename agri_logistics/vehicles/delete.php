<?php
// This line is CRUCIAL for BASE_URL and $conn to be available
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php"); // Use BASE_URL for redirect
    exit;
}

// Access control: Only Admin and Logistics Manager can delete vehicles
if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to delete vehicles.";
    header("location: " . BASE_URL . "dashboard.php"); // Use BASE_URL for redirect
    exit;
}

// Check if ID parameter is set in the GET request
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $vehicle_id_to_delete = trim($_GET["id"]);

    // --- IMPORTANT: Check if vehicle is currently assigned to any active shipments ---
    $sql_check_shipments = "SELECT shipment_id FROM shipments WHERE vehicle_id = ? AND status IN ('pending', 'assigned', 'picked_up', 'in_transit')";
    if($stmt_check = mysqli_prepare($conn, $sql_check_shipments)){
        mysqli_stmt_bind_param($stmt_check, "i", $param_id);
        $param_id = $vehicle_id_to_delete; // Use the ID of the vehicle to be deleted
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if(mysqli_stmt_num_rows($stmt_check) > 0){
            // If the vehicle is assigned to active shipments, prevent deletion
            $_SESSION['error_message'] = "Cannot delete vehicle. It is currently assigned to one or more active shipments. Please unassign it first.";
        } else {
            // If not assigned to active shipments, proceed with deletion
            $sql_delete_vehicle = "DELETE FROM vehicles WHERE vehicle_id = ?";
            if ($stmt_delete = mysqli_prepare($conn, $sql_delete_vehicle)) {
                mysqli_stmt_bind_param($stmt_delete, "i", $param_id);
                $param_id = $vehicle_id_to_delete;

                if (mysqli_stmt_execute($stmt_delete)) {
                    $_SESSION['success_message'] = "Vehicle deleted successfully.";
                } else {
                    $_SESSION['error_message'] = "Error: Could not delete vehicle. " . mysqli_error($conn);
                    error_log("Error deleting vehicle: " . mysqli_error($conn)); // Log for debugging
                }
                mysqli_stmt_close($stmt_delete);
            } else {
                $_SESSION['error_message'] = "Error preparing delete statement. " . mysqli_error($conn);
                error_log("Error preparing vehicle delete statement: " . mysqli_error($conn));
            }
        }
        mysqli_stmt_close($stmt_check); // Close the check statement
    } else {
        $_SESSION['error_message'] = "Error checking vehicle assignments. " . mysqli_error($conn);
        error_log("Error preparing vehicle assignment check: " . mysqli_error($conn));
    }
} else {
    // If no ID is provided in the URL
    $_SESSION['error_message'] = "No vehicle ID provided for deletion.";
}

// Redirect back to the vehicle list page
header("location: " . BASE_URL . "vehicles/index.php"); // Use BASE_URL for redirect
exit();
// Note: mysqli_close($conn) is handled by includes/footer.php
?>