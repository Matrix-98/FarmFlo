<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

if (!in_array($_SESSION["role"], ['admin', 'logistics_manager'])) {
    $_SESSION['error_message'] = "You do not have permission to delete documents.";
    header("location: ../dashboard.php");
    exit;
}

$shipment_id = isset($_GET['shipment_id']) ? trim($_GET['shipment_id']) : null;

if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $document_id = trim($_GET['id']);

    // First, get the file path to delete the actual file
    $file_path = '';
    $sql_get_path = "SELECT file_path FROM documents WHERE document_id = ?";
    if ($stmt_path = mysqli_prepare($conn, $sql_get_path)) {
        mysqli_stmt_bind_param($stmt_path, "i", $param_id);
        $param_id = $document_id;
        if (mysqli_stmt_execute($stmt_path)) {
            $result_path = mysqli_stmt_get_result($stmt_path);
            if ($row_path = mysqli_fetch_assoc($result_path)) {
                $file_path = $row_path['file_path'];
            }
        }
        mysqli_stmt_close($stmt_path);
    }

    // Then, delete the record from the database
    $sql_delete = "DELETE FROM documents WHERE document_id = ?";
    if ($stmt_delete = mysqli_prepare($conn, $sql_delete)) {
        mysqli_stmt_bind_param($stmt_delete, "i", $param_id);
        $param_id = $document_id;

        if (mysqli_stmt_execute($stmt_delete)) {
            // If DB record deleted, attempt to delete the file
            if (!empty($file_path) && file_exists($file_path)) {
                if (unlink($file_path)) {
                    $_SESSION['success_message'] = "Document and file deleted successfully.";
                } else {
                    $_SESSION['success_message'] = "Document deleted from database, but file could not be removed from server.";
                    error_log("Error deleting document file: " . $file_path);
                }
            } else {
                $_SESSION['success_message'] = "Document deleted from database. File not found on server or path was empty.";
            }
        } else {
            $_SESSION['error_message'] = "Error deleting document from database: " . mysqli_error($conn);
            error_log("Error deleting document from database: " . mysqli_error($conn));
        }
        mysqli_stmt_close($stmt_delete);
    } else {
        $_SESSION['error_message'] = "Error preparing delete statement.";
    }
} else {
    $_SESSION['error_message'] = "No document ID provided for deletion.";
}

mysqli_close($conn);
// Redirect back to the documents list for the specific shipment
if ($shipment_id) {
    header("location: index.php?shipment_id=" . $shipment_id);
} else {
    header("location: ../dashboard.php"); // Fallback
}
exit;
?>