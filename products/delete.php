<?php
require_once '../config/db.php'; // Adjust path for nested folder

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

// Check user role for access control
if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'farm_manager') {
    $_SESSION['error_message'] = "You do not have permission to delete products.";
    header("location: ../dashboard.php"); // Redirect unauthorized users
    exit;
}

// Check if ID parameter is set
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    // Prepare a delete statement
    $sql = "DELETE FROM products WHERE product_id = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "i", $param_id);

        // Set parameters
        $param_id = trim($_GET["id"]);

        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Product deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error: Could not delete product. " . mysqli_error($conn);
            error_log("Error deleting product: " . mysqli_error($conn));
        }

        // Close statement
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_message'] = "Error preparing delete statement.";
    }
} else {
    $_SESSION['error_message'] = "Invalid request. No product ID provided.";
}

// Close connection
mysqli_close($conn);

// Redirect back to product list
header("location: index.php");
exit();
?>