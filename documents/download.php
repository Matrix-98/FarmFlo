<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

// All logged-in users can download. Access control is based on login only for simplicity.
// For more granular control, you'd check roles here if only specific roles could download specific types.

if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $document_id = trim($_GET['id']);

    $sql = "SELECT file_name, file_path, document_type FROM documents WHERE document_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $param_id);
        $param_id = $document_id;

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if (mysqli_num_rows($result) == 1) {
                $document = mysqli_fetch_assoc($result);
                $file_path = $document['file_path'];
                $file_name = $document['file_name'];

                if (file_exists($file_path)) {
                    // Set appropriate headers for download
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream'); // Generic binary file type
                    header('Content-Disposition: attachment; filename="' . basename($file_name) . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($file_path));
                    flush(); // Flush system output buffer
                    readfile($file_path);
                    exit;
                } else {
                    $_SESSION['error_message'] = "File not found on server.";
                }
            } else {
                $_SESSION['error_message'] = "Document not found in database.";
            }
        } else {
            $_SESSION['error_message'] = "Error fetching document details: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_message'] = "Error preparing document query.";
    }
} else {
    $_SESSION['error_message'] = "No document ID provided.";
}

mysqli_close($conn);
// If any error occurs, redirect to shipment documents page or dashboard
$redirect_shipment_id = isset($_GET['shipment_id']) ? $_GET['shipment_id'] : (isset($document) ? $document['shipment_id'] : null);
if ($redirect_shipment_id) {
    header("location: index.php?shipment_id=" . $redirect_shipment_id);
} else {
    header("location: ../dashboard.php");
}
exit;
?>