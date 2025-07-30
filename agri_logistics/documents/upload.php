<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

if (!in_array($_SESSION["role"], ['admin', 'logistics_manager'])) {
    $_SESSION['error_message'] = "You do not have permission to upload documents.";
    header("location: ../dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $shipment_id = $_POST['shipment_id'];
    $document_type = trim($_POST['document_type']);
    $notes = trim($_POST['notes']);
    $uploaded_by = $_SESSION['user_id'];

    if (empty($document_type)) {
        $_SESSION['error_message'] = "Please select a document type.";
        header("location: index.php?shipment_id=" . $shipment_id);
        exit;
    }

    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] != UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = "Please select a file to upload.";
        header("location: index.php?shipment_id=" . $shipment_id);
        exit;
    }

    $file = $_FILES['document_file'];
    $file_name = basename($file['name']);
    $file_tmp_name = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];
    $file_type = $file['type'];

    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    if (!in_array($file_ext, $allowed_extensions)) {
        $_SESSION['error_message'] = "Invalid file type. Only PDF, DOC, DOCX, JPG, JPEG, PNG are allowed.";
        header("location: index.php?shipment_id=" . $shipment_id);
        exit;
    }

    // Define upload directory
    $upload_dir = '../uploads/documents/'; // Create this directory in agri_logistics/
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true); // Create directory if it doesn't exist
    }

    // Generate a unique file name to prevent overwrites
    $new_file_name = uniqid('doc_', true) . '.' . $file_ext;
    $file_destination = $upload_dir . $new_file_name;

    if (move_uploaded_file($file_tmp_name, $file_destination)) {
        $sql = "INSERT INTO documents (shipment_id, document_type, file_name, file_path, uploaded_by, notes) VALUES (?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "isssis", $param_shipment_id, $param_document_type, $param_file_name, $param_file_path, $param_uploaded_by, $param_notes);

            $param_shipment_id = $shipment_id;
            $param_document_type = $document_type;
            $param_file_name = $file_name;
            $param_file_path = $file_destination; // Store the full path
            $param_uploaded_by = $uploaded_by;
            $param_notes = $notes;

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Document uploaded successfully!";
            } else {
                $_SESSION['error_message'] = "Error recording document in database: " . mysqli_error($conn);
                // Delete the file if database insertion fails
                if (file_exists($file_destination)) {
                    unlink($file_destination);
                }
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Error preparing document insert statement: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error_message'] = "Error moving uploaded file.";
    }

    mysqli_close($conn);
    header("location: index.php?shipment_id=" . $shipment_id);
    exit;
} else {
    // If not a POST request, redirect back to the documents list or dashboard
    $_SESSION['error_message'] = "Invalid request method.";
    header("location: ../dashboard.php");
    exit;
}
?>