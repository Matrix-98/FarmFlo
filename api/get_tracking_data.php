<?php
require_once '../config/db.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if shipment_id is provided
if (!isset($_GET['shipment_id']) || !is_numeric($_GET['shipment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid shipment ID']);
    exit;
}

$shipment_id = intval($_GET['shipment_id']);
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Verify user has access to this shipment
$access_sql = "";
$access_params = [];
$access_param_types = "";

if ($user_role == 'customer') {
    // Customer can only see their own shipments
    $access_sql = "SELECT 1 FROM shipments s 
                   LEFT JOIN orders o ON s.order_id = o.order_id 
                   WHERE s.shipment_id = ? AND o.customer_id = ?";
    $access_params = [$shipment_id, $user_id];
    $access_param_types = "ii";
} elseif ($user_role == 'driver') {
    // Driver can only see shipments assigned to them
    $access_sql = "SELECT 1 FROM shipments s 
                   JOIN drivers d ON s.driver_id = d.driver_id 
                   WHERE s.shipment_id = ? AND d.user_id = ?";
    $access_params = [$shipment_id, $user_id];
    $access_param_types = "ii";
} elseif (in_array($user_role, ['admin', 'logistics_manager'])) {
    // Admin and logistics managers can see all shipments
    $access_sql = "SELECT 1 FROM shipments WHERE shipment_id = ?";
    $access_params = [$shipment_id];
    $access_param_types = "i";
} else {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Check access
$access_stmt = mysqli_prepare($conn, $access_sql);
if ($access_stmt) {
    mysqli_stmt_bind_param($access_stmt, $access_param_types, ...$access_params);
    mysqli_stmt_execute($access_stmt);
    $access_result = mysqli_stmt_get_result($access_stmt);
    
    if (mysqli_num_rows($access_result) == 0) {
        echo json_encode(['success' => false, 'message' => 'Access denied to this shipment']);
        exit;
    }
    mysqli_stmt_close($access_stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

// Get tracking data for the shipment
$tracking_data = [];
        $sql = "SELECT td.*, CONCAT(d.first_name, ' ', d.last_name) as driver_name 
                FROM tracking_data td
                LEFT JOIN drivers d ON td.recorded_by = d.user_id
                WHERE td.shipment_id = ?
                ORDER BY td.recorded_at DESC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $shipment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $tracking_data[] = [
            'tracking_id' => $row['tracking_id'],
            'shipment_id' => $row['shipment_id'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'temperature' => $row['temperature'],
            'humidity' => $row['humidity'],
            'delivery_status' => $row['delivery_status'],
            'order_notes' => $row['order_notes'],
            'recorded_at' => $row['recorded_at'],
            'driver_name' => $row['driver_name']
        ];
    }
    mysqli_stmt_close($stmt);
    
    echo json_encode([
        'success' => true,
        'tracking_data' => $tracking_data,
        'count' => count($tracking_data)
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
