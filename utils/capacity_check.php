<?php
// agri_logistics/utils/capacity_check.php
// API endpoint for real-time capacity validation

require_once '../config/db.php';
require_once 'inventory_helpers.php';

// Set JSON content type
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if user has permission
if (!in_array($_SESSION["role"], ['admin', 'farm_manager', 'warehouse_manager'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// Validate required fields
$required_fields = ['location_id', 'product_id', 'quantity', 'operation'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

$location_id = intval($input['location_id']);
$product_id = intval($input['product_id']);
$quantity = floatval($input['quantity']);
$operation = $input['operation'];
$existing_inventory_id = isset($input['existing_inventory_id']) ? intval($input['existing_inventory_id']) : null;

// Validate quantity
if ($quantity <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Quantity must be positive']);
    exit;
}

// Validate operation
if (!in_array($operation, ['add', 'remove', 'update'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid operation']);
    exit;
}

// Check if warehouse manager has access to this location
if ($_SESSION["role"] == 'warehouse_manager') {
    $user_id = $_SESSION['user_id'];
    $sql_check_access = "SELECT COUNT(*) as count FROM user_assigned_locations WHERE user_id = ? AND location_id = ?";
    if ($stmt_check = mysqli_prepare($conn, $sql_check_access)) {
        mysqli_stmt_bind_param($stmt_check, "ii", $user_id, $location_id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $row_check = mysqli_fetch_assoc($result_check);
        mysqli_stmt_close($stmt_check);
        
        if ($row_check['count'] == 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied to this location']);
            exit;
        }
    }
}

// Perform capacity validation
try {
    $validation_result = validateInventoryCapacity($conn, $location_id, $product_id, $quantity, $operation, $existing_inventory_id);
    
    // Return the validation result
    echo json_encode($validation_result);
    
} catch (Exception $e) {
    error_log("Capacity check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?> 