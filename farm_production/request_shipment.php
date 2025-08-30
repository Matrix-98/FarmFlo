<?php
require_once '../config/db.php';
require_once '../utils/id_generator.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Only admin and farm managers can create shipment requests
if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'farm_manager') {
    $_SESSION['error_message'] = "You do not have permission to create shipment requests.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $production_id = isset($_POST['production_id']) ? (int)$_POST['production_id'] : 0;
    $quantity_kg = isset($_POST['quantity_kg']) ? (float)$_POST['quantity_kg'] : 0;
    $preferred_pickup_date = isset($_POST['preferred_pickup_date']) ? $_POST['preferred_pickup_date'] : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Validate inputs
    if ($production_id <= 0 || $quantity_kg <= 0 || empty($preferred_pickup_date)) {
        $_SESSION['error_message'] = "Please provide all required fields.";
        header("location: " . BASE_URL . "farm_production/view.php?id=" . $production_id);
        exit;
    }
    
    // Validate pickup date
    if (strtotime($preferred_pickup_date) < strtotime(date('Y-m-d'))) {
        $_SESSION['error_message'] = "Pickup date cannot be in the past.";
        header("location: " . BASE_URL . "farm_production/view.php?id=" . $production_id);
        exit;
    }
    
    // Check if production exists and belongs to this farm manager (or admin can access any)
    $sql_check = "SELECT fp.*, p.product_id, p.name as product_name 
                  FROM farm_production fp 
                  JOIN products p ON fp.product_id = p.product_id 
                  WHERE fp.production_id = ? AND fp.status = 'harvested'";
    
    // Add farm manager filter only for farm managers (admin can access any)
    if ($_SESSION['role'] == 'farm_manager') {
        $sql_check .= " AND fp.farm_manager_id = ?";
    }
    
    if ($stmt = mysqli_prepare($conn, $sql_check)) {
        if ($_SESSION['role'] == 'farm_manager') {
            mysqli_stmt_bind_param($stmt, "ii", $production_id, $_SESSION['user_id']);
        } else {
            mysqli_stmt_bind_param($stmt, "i", $production_id);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $production = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$production) {
            $_SESSION['error_message'] = "Production record not found or not eligible for shipment request.";
            header("location: " . BASE_URL . "farm_production/");
            exit;
        }
        
        // For admin users, we need to get the farm manager ID from the production
        $farm_manager_id = ($_SESSION['role'] == 'admin') ? $production['farm_manager_id'] : $_SESSION['user_id'];
        
        // Check if quantity is available
        if ($quantity_kg > $production['harvested_amount_kg']) {
            $_SESSION['error_message'] = "Requested quantity exceeds available harvested amount.";
            header("location: " . BASE_URL . "farm_production/view.php?id=" . $production_id);
            exit;
        }
        
        // Check if there's already a pending request for this production
        $sql_check_existing = "SELECT request_id FROM shipment_requests 
                              WHERE production_id = ? AND status IN ('pending', 'approved')";
        if ($stmt = mysqli_prepare($conn, $sql_check_existing)) {
            mysqli_stmt_bind_param($stmt, "i", $production_id);
            mysqli_stmt_execute($stmt);
            $existing_result = mysqli_stmt_get_result($stmt);
            mysqli_stmt_close($stmt);
            
            if (mysqli_num_rows($existing_result) > 0) {
                $_SESSION['error_message'] = "A shipment request already exists for this production.";
                header("location: " . BASE_URL . "farm_production/view.php?id=" . $production_id);
                exit;
            }
        }
        
        // Generate request code
        $request_code = generateShipmentRequestId();
        
        // Insert the shipment request
        $sql_insert = "INSERT INTO shipment_requests (request_code, production_id, farm_manager_id, product_id, 
                       quantity_kg, request_date, preferred_pickup_date, notes) 
                       VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?)";
        
        if ($stmt = mysqli_prepare($conn, $sql_insert)) {
            mysqli_stmt_bind_param($stmt, "siiidss", $request_code, $production_id, $farm_manager_id, 
                                  $production['product_id'], $quantity_kg, $preferred_pickup_date, $notes);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Shipment request submitted successfully. Request Code: " . $request_code;
            } else {
                $_SESSION['error_message'] = "Failed to submit shipment request. Please try again.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Database error. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = "Database error. Please try again.";
    }
} else {
    $_SESSION['error_message'] = "Invalid request method.";
}

header("location: " . BASE_URL . "farm_production/view.php?id=" . $production_id);
exit;
?>
