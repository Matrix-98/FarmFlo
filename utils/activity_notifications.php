<?php
/**
 * Activity Notifications Utility
 * Handles checking for new activities and updating dashboard visit timestamps
 */

/**
 * Check if there are new activities since user's last dashboard visit
 * @param int $user_id User ID
 * @param string $role User role
 * @return bool True if there are new activities, false otherwise
 */
function hasNewActivities($user_id, $role) {
    global $conn;
    
    // Get user's last dashboard visit
    $last_visit = getUserLastDashboardVisit($user_id, $role);
    
    if (!$last_visit) {
        return false; // No visit record found
    }
    
    // Check for new activities based on role
    switch ($role) {
        case 'admin':
            return hasNewAdminActivities($last_visit);
        case 'customer':
            return hasNewCustomerActivities($user_id, $last_visit);
        case 'driver':
            return hasNewDriverActivities($user_id, $last_visit);
        case 'farm_manager':
            return hasNewFarmManagerActivities($user_id, $last_visit);
        case 'warehouse_manager':
            return hasNewWarehouseManagerActivities($user_id, $last_visit);
        case 'logistics_manager':
            return hasNewLogisticsManagerActivities($last_visit);
        default:
            return false;
    }
}

/**
 * Get user's last dashboard visit timestamp
 * @param int $user_id User ID
 * @param string $role User role
 * @return string|null Last visit timestamp or null if not found
 */
function getUserLastDashboardVisit($user_id, $role) {
    global $conn;
    
    $sql = "SELECT last_visit FROM user_dashboard_visits WHERE user_id = ? AND role = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "is", $user_id, $role);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['last_visit'];
        }
        mysqli_stmt_close($stmt);
    }
    return null;
}

/**
 * Update user's dashboard visit timestamp
 * @param int $user_id User ID
 * @param string $role User role
 */
function updateUserDashboardVisit($user_id, $role) {
    global $conn;
    
    $sql = "INSERT INTO user_dashboard_visits (user_id, role, last_visit) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE last_visit = NOW()";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "is", $user_id, $role);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Check for new admin activities
 * @param string $last_visit Last visit timestamp
 * @return bool True if new activities exist
 */
function hasNewAdminActivities($last_visit) {
    global $conn;
    
    // Check for new inventory, shipments, or orders
    $sql = "SELECT 1 FROM (
                SELECT created_at FROM inventory WHERE created_at > ?
                UNION ALL
                SELECT created_at FROM shipments WHERE created_at > ?
                UNION ALL
                SELECT created_at FROM orders WHERE created_at > ?
            ) as activities LIMIT 1";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "sss", $last_visit, $last_visit, $last_visit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $has_new = mysqli_num_rows($result) > 0;
        mysqli_stmt_close($stmt);
        return $has_new;
    }
    return false;
}

/**
 * Check for new customer activities
 * @param int $user_id User ID
 * @param string $last_visit Last visit timestamp
 * @return bool True if new activities exist
 */
function hasNewCustomerActivities($user_id, $last_visit) {
    global $conn;
    
    // Check for new orders or shipments for this customer
    $sql = "SELECT 1 FROM (
                SELECT created_at FROM orders WHERE customer_id = ? AND created_at > ?
                UNION ALL
                SELECT s.created_at FROM shipments s 
                JOIN orders o ON s.order_id = o.order_id 
                WHERE o.customer_id = ? AND s.created_at > ?
            ) as activities LIMIT 1";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "isis", $user_id, $last_visit, $user_id, $last_visit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $has_new = mysqli_num_rows($result) > 0;
        mysqli_stmt_close($stmt);
        return $has_new;
    }
    return false;
}

/**
 * Check for new driver activities
 * @param int $user_id User ID
 * @param string $last_visit Last visit timestamp
 * @return bool True if new activities exist
 */
function hasNewDriverActivities($user_id, $last_visit) {
    global $conn;
    
    // Check for new shipments assigned to this driver
    $sql = "SELECT 1 FROM shipments s 
            JOIN drivers d ON s.driver_id = d.driver_id 
            WHERE d.user_id = ? AND s.created_at > ? 
            LIMIT 1";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "is", $user_id, $last_visit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $has_new = mysqli_num_rows($result) > 0;
        mysqli_stmt_close($stmt);
        return $has_new;
    }
    return false;
}

/**
 * Check for new farm manager activities
 * @param int $user_id User ID
 * @param string $last_visit Last visit timestamp
 * @return bool True if new activities exist
 */
function hasNewFarmManagerActivities($user_id, $last_visit) {
    global $conn;
    
    // Check for new farm productions by this manager
    $sql = "SELECT 1 FROM farm_production 
            WHERE farm_manager_id = ? AND created_at > ? 
            LIMIT 1";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "is", $user_id, $last_visit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $has_new = mysqli_num_rows($result) > 0;
        mysqli_stmt_close($stmt);
        return $has_new;
    }
    return false;
}

/**
 * Check for new warehouse manager activities
 * @param int $user_id User ID
 * @param string $last_visit Last visit timestamp
 * @return bool True if new activities exist
 */
function hasNewWarehouseManagerActivities($user_id, $last_visit) {
    global $conn;
    
    // Get assigned locations for this warehouse manager
    $assigned_locations = [];
    $sql_locations = "SELECT location_id FROM user_assigned_locations WHERE user_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql_locations)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $assigned_locations[] = $row['location_id'];
        }
        mysqli_stmt_close($stmt);
    }
    
    if (empty($assigned_locations)) {
        return false;
    }
    
    // Check for new inventory in assigned locations
    $location_ids = implode(',', $assigned_locations);
    $sql = "SELECT 1 FROM inventory 
            WHERE location_id IN ($location_ids) AND created_at > ? 
            LIMIT 1";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $last_visit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $has_new = mysqli_num_rows($result) > 0;
        mysqli_stmt_close($stmt);
        return $has_new;
    }
    return false;
}

/**
 * Check for new logistics manager activities
 * @param string $last_visit Last visit timestamp
 * @return bool True if new activities exist
 */
function hasNewLogisticsManagerActivities($last_visit) {
    global $conn;
    
    // Check for new shipments or vehicle assignments
    $sql = "SELECT 1 FROM (
                SELECT created_at FROM shipments WHERE created_at > ?
                UNION ALL
                SELECT created_at FROM vehicles WHERE created_at > ?
            ) as activities LIMIT 1";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ss", $last_visit, $last_visit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $has_new = mysqli_num_rows($result) > 0;
        mysqli_stmt_close($stmt);
        return $has_new;
    }
    return false;
}

/**
 * Check if there are pending registration requests
 * @return bool True if there are pending requests, false otherwise
 */
function hasPendingRegistrationRequests() {
    global $conn;
    
    $sql = "SELECT COUNT(*) as count FROM registration_requests";
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return $row['count'] > 0;
    }
    
    return false;
}
?>
