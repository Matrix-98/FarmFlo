<?php
/**
 * Code Helpers Utility
 * Functions to get 6-digit codes for existing IDs for frontend display
 */

/**
 * Get order code by order ID
 */
function getOrderCode($order_id) {
    global $conn;
    
    if (!$order_id) return 'N/A';
    
    $sql = "SELECT order_code FROM orders WHERE order_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['order_code'] ?: 'O' . str_pad($order_id, 5, '0', STR_PAD_LEFT);
        }
    }
    return 'O' . str_pad($order_id, 5, '0', STR_PAD_LEFT);
}

/**
 * Get shipment code by shipment ID
 */
function getShipmentCode($shipment_id) {
    global $conn;
    
    if (!$shipment_id) return 'N/A';
    
    $sql = "SELECT shipment_code FROM shipments WHERE shipment_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $shipment_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['shipment_code'] ?: 'S' . str_pad($shipment_id, 5, '0', STR_PAD_LEFT);
        }
    }
    return 'S' . str_pad($shipment_id, 5, '0', STR_PAD_LEFT);
}

/**
 * Get inventory code by inventory ID
 */
function getInventoryCode($inventory_id) {
    global $conn;
    
    if (!$inventory_id) return 'N/A';
    
    $sql = "SELECT inventory_code FROM inventory WHERE inventory_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $inventory_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['inventory_code'] ?: 'I' . str_pad($inventory_id, 5, '0', STR_PAD_LEFT);
        }
    }
    return 'I' . str_pad($inventory_id, 5, '0', STR_PAD_LEFT);
}

/**
 * Get product code by product ID
 */
function getProductCode($product_id) {
    global $conn;
    
    if (!$product_id) return 'N/A';
    
    $sql = "SELECT product_code FROM products WHERE product_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['product_code'] ?: 'P' . str_pad($product_id, 5, '0', STR_PAD_LEFT);
        }
    }
    return 'P' . str_pad($product_id, 5, '0', STR_PAD_LEFT);
}

/**
 * Get driver code by driver ID
 */
function getDriverCode($driver_id) {
    global $conn;
    
    if (!$driver_id) return 'N/A';
    
    $sql = "SELECT driver_code FROM drivers WHERE driver_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $driver_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['driver_code'] ?: 'D' . str_pad($driver_id, 5, '0', STR_PAD_LEFT);
        }
    }
    return 'D' . str_pad($driver_id, 5, '0', STR_PAD_LEFT);
}

/**
 * Get vehicle code by vehicle ID
 */
function getVehicleCode($vehicle_id) {
    global $conn;
    
    if (!$vehicle_id) return 'N/A';
    
    $sql = "SELECT vehicle_code FROM vehicles WHERE vehicle_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $vehicle_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['vehicle_code'] ?: 'V' . str_pad($vehicle_id, 5, '0', STR_PAD_LEFT);
        }
    }
    return 'V' . str_pad($vehicle_id, 5, '0', STR_PAD_LEFT);
}

/**
 * Get location code by location ID
 */
function getLocationCode($location_id) {
    global $conn;
    
    if (!$location_id) return 'N/A';
    
    $sql = "SELECT location_code FROM locations WHERE location_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $location_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['location_code'] ?: 'L' . str_pad($location_id, 5, '0', STR_PAD_LEFT);
        }
    }
    return 'L' . str_pad($location_id, 5, '0', STR_PAD_LEFT);
}

/**
 * Get user code by user ID
 */
function getUserCode($user_id) {
    global $conn;
    
    if (!$user_id) return 'N/A';
    
    $sql = "SELECT user_code FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['user_code'] ?: 'U' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
        }
    }
    return 'U' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
}
?>
