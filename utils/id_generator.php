<?php
/**
 * ID Generator Utility
 * Generates meaningful 6-digit codes for different entity types
 */

/**
 * Generate a meaningful 6-digit user ID
 * Format: U + 5 digits (e.g., U00001, U00002, etc.)
 */
function generateUserId() {
    global $conn;
    
    // Check if user_code column exists
    $check_column = "SHOW COLUMNS FROM users LIKE 'user_code'";
    $result_check = mysqli_query($conn, $check_column);
    
    if (mysqli_num_rows($result_check) > 0) {
        // Column exists, use the new logic
        $sql = "SELECT MAX(CAST(SUBSTRING(user_code, 2) AS UNSIGNED)) as max_num FROM users WHERE user_code LIKE 'U%'";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    } else {
        // Column doesn't exist, use user_id as fallback
        $sql = "SELECT MAX(user_id) as max_num FROM users";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    }
    
    return 'U' . str_pad($next_num, 5, '0', STR_PAD_LEFT);
}

/**
 * Generate a meaningful 6-digit order ID
 * Format: O + YY + 3 digits (e.g., O25001, O25002, etc.)
 * YY = last 2 digits of current year
 */
function generateOrderId() {
    global $conn;
    
    $year = date('y'); // Last 2 digits of current year
    
    // Check if order_code column exists
    $check_column = "SHOW COLUMNS FROM orders LIKE 'order_code'";
    $result_check = mysqli_query($conn, $check_column);
    
    if (mysqli_num_rows($result_check) > 0) {
        // Column exists, use the new logic
        $sql = "SELECT MAX(CAST(SUBSTRING(order_code, 4) AS UNSIGNED)) as max_num 
                FROM orders 
                WHERE order_code LIKE 'O{$year}%'";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    } else {
        // Column doesn't exist, use order_id as fallback
        $sql = "SELECT MAX(order_id) as max_num FROM orders";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    }
    
    return 'O' . $year . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate a meaningful 6-digit shipment ID
 * Format: S + YY + 3 digits (e.g., S25001, S25002, etc.)
 * YY = last 2 digits of current year
 */
function generateShipmentId() {
    global $conn;
    
    $year = date('y'); // Last 2 digits of current year
    
    // Check if shipment_code column exists
    $check_column = "SHOW COLUMNS FROM shipments LIKE 'shipment_code'";
    $result_check = mysqli_query($conn, $check_column);
    
    if (mysqli_num_rows($result_check) > 0) {
        // Column exists, use the new logic
        $sql = "SELECT MAX(CAST(SUBSTRING(shipment_code, 4) AS UNSIGNED)) as max_num 
                FROM shipments 
                WHERE shipment_code LIKE 'S{$year}%'";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    } else {
        // Column doesn't exist, use shipment_id as fallback
        $sql = "SELECT MAX(shipment_id) as max_num FROM shipments";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    }
    
    return 'S' . $year . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate a meaningful 6-digit product ID
 * Format: P + YY + 3 digits (e.g., P25001, P25002, etc.)
 * YY = last 2 digits of current year
 */
function generateProductId() {
    global $conn;
    
    $year = date('y'); // Last 2 digits of current year
    
    // Check if product_code column exists
    $check_column = "SHOW COLUMNS FROM products LIKE 'product_code'";
    $result_check = mysqli_query($conn, $check_column);
    
    if (mysqli_num_rows($result_check) > 0) {
        // Column exists, use the new logic
        $sql = "SELECT MAX(CAST(SUBSTRING(product_code, 4) AS UNSIGNED)) as max_num 
                FROM products 
                WHERE product_code LIKE 'P{$year}%'";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    } else {
        // Column doesn't exist, use product_id as fallback
        $sql = "SELECT MAX(product_id) as max_num FROM products";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    }
    
    return 'P' . $year . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate a meaningful 6-digit driver ID
 * Format: D + YY + 3 digits (e.g., D25001, D25002, etc.)
 * YY = last 2 digits of current year
 */
function generateDriverId() {
    global $conn;
    
    $year = date('y'); // Last 2 digits of current year
    
    // Check if driver_code column exists
    $check_column = "SHOW COLUMNS FROM drivers LIKE 'driver_code'";
    $result_check = mysqli_query($conn, $check_column);
    
    if (mysqli_num_rows($result_check) > 0) {
        // Column exists, use the new logic
        $sql = "SELECT MAX(CAST(SUBSTRING(driver_code, 4) AS UNSIGNED)) as max_num 
                FROM drivers 
                WHERE driver_code LIKE 'D{$year}%'";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    } else {
        // Column doesn't exist, use driver_id as fallback
        $sql = "SELECT MAX(driver_id) as max_num FROM drivers";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    }
    
    return 'D' . $year . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate a meaningful 6-digit vehicle ID
 * Format: V + YY + 3 digits (e.g., V25001, V25002, etc.)
 * YY = last 2 digits of current year
 */
function generateVehicleId() {
    global $conn;
    
    $year = date('y'); // Last 2 digits of current year
    
    // Check if vehicle_code column exists
    $check_column = "SHOW COLUMNS FROM vehicles LIKE 'vehicle_code'";
    $result_check = mysqli_query($conn, $check_column);
    
    if (mysqli_num_rows($result_check) > 0) {
        // Column exists, use the new logic
        $sql = "SELECT MAX(CAST(SUBSTRING(vehicle_code, 4) AS UNSIGNED)) as max_num 
                FROM vehicles 
                WHERE vehicle_code LIKE 'V{$year}%'";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    } else {
        // Column doesn't exist, use vehicle_id as fallback
        $sql = "SELECT MAX(vehicle_id) as max_num FROM vehicles";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    }
    
    return 'V' . $year . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate a meaningful 6-digit location ID
 * Format: L + YY + 3 digits (e.g., L25001, L25002, etc.)
 * YY = last 2 digits of current year
 */
function generateLocationId() {
    global $conn;
    
    $year = date('y'); // Last 2 digits of current year
    
    // Check if location_code column exists
    $check_column = "SHOW COLUMNS FROM locations LIKE 'location_code'";
    $result_check = mysqli_query($conn, $check_column);
    
    if (mysqli_num_rows($result_check) > 0) {
        // Column exists, use the new logic
        $sql = "SELECT MAX(CAST(SUBSTRING(location_code, 4) AS UNSIGNED)) as max_num 
                FROM locations 
                WHERE location_code LIKE 'L{$year}%'";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    } else {
        // Column doesn't exist, use location_id as fallback
        $sql = "SELECT MAX(location_id) as max_num FROM locations";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    }
    
    return 'L' . $year . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate a meaningful 6-digit inventory ID
 * Format: I + YY + 3 digits (e.g., I25001, I25002, etc.)
 * YY = last 2 digits of current year
 */
function generateInventoryId() {
    global $conn;
    
    $year = date('y'); // Last 2 digits of current year
    
    // Check if inventory_code column exists
    $check_column = "SHOW COLUMNS FROM inventory LIKE 'inventory_code'";
    $result_check = mysqli_query($conn, $check_column);
    
    if (mysqli_num_rows($result_check) > 0) {
        // Column exists, use the new logic
        $sql = "SELECT MAX(CAST(SUBSTRING(inventory_code, 4) AS UNSIGNED)) as max_num 
                FROM inventory 
                WHERE inventory_code LIKE 'I{$year}%'";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    } else {
        // Column doesn't exist, use inventory_id as fallback
        $sql = "SELECT MAX(inventory_id) as max_num FROM inventory";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    }
    
    return 'I' . $year . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate a meaningful 6-digit farm production ID
 * Format: FP + YY + 3 digits (e.g., FP25001, FP25002, etc.)
 * YY = last 2 digits of current year
 */
function generateFarmProductionId() {
    global $conn;
    
    $year = date('y'); // Last 2 digits of current year
    
    // Check if production_code column exists
    $check_column = "SHOW COLUMNS FROM farm_production LIKE 'production_code'";
    $result_check = mysqli_query($conn, $check_column);
    
    if (mysqli_num_rows($result_check) > 0) {
        // Column exists, use the new logic
        $sql = "SELECT MAX(CAST(SUBSTRING(production_code, 4) AS UNSIGNED)) as max_num 
                FROM farm_production 
                WHERE production_code LIKE 'FP{$year}%'";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    } else {
        // Column doesn't exist, use production_id as fallback
        $sql = "SELECT MAX(production_id) as max_num FROM farm_production";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $next_num = ($row['max_num'] ?? 0) + 1;
        } else {
            $next_num = 1;
        }
    }
    
    return 'FP' . $year . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

/**
 * Parse ID to get the numeric part
 */
function parseId($code) {
    if (preg_match('/^[A-Z](\d{5})$/', $code, $matches)) {
        return intval($matches[1]);
    }
    return null;
}

/**
 * Get entity type from code
 */
function getEntityType($code) {
    $prefix = substr($code, 0, 1);
    $prefix2 = substr($code, 0, 2);
    
    // Check for 2-character prefixes first
    if ($prefix2 === 'FP') {
        return 'Farm Production';
    }
    
    // Check for 1-character prefixes
    $types = [
        'U' => 'User',
        'O' => 'Order', 
        'S' => 'Shipment',
        'P' => 'Product',
        'D' => 'Driver',
        'V' => 'Vehicle',
        'L' => 'Location',
        'I' => 'Inventory'
    ];
    return $types[$prefix] ?? 'Unknown';
}

/**
 * Get year from code (for codes that include year)
 */
function getYearFromCode($code) {
    // Handle FP prefix (2 characters)
    if (preg_match('/^FP(\d{2})(\d{3})$/', $code, $matches)) {
        return '20' . $matches[1]; // Convert YY to YYYY
    }
    // Handle single character prefixes
    if (preg_match('/^[A-Z](\d{2})(\d{3})$/', $code, $matches)) {
        return '20' . $matches[1]; // Convert YY to YYYY
    }
    return null;
}
?>
