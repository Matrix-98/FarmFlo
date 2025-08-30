<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/id_generator.php';

/**
 * Calculate total inventory capacity for a location
 */
function calculateLocationCapacity($location_id) {
    global $conn;
    
    $sql = "SELECT 
                SUM(i.quantity_kg) as total_weight,
                SUM(i.quantity_kg * 0.001) as total_volume
            FROM inventory i 
            WHERE i.location_id = ? AND i.stage NOT IN ('sold', 'lost', 'damaged')";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $location_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $capacity = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return [
            'weight_kg' => $capacity['total_weight'] ?? 0,
            'volume_m3' => $capacity['total_volume'] ?? 0
        ];
    }
    return ['weight_kg' => 0, 'volume_m3' => 0];
}

/**
 * Get dynamic inventory summary for a location
 */
function getLocationInventorySummary($location_id) {
    global $conn;
    
    $sql = "SELECT 
                i.stage,
                SUM(i.quantity_kg) as total_quantity,
                COUNT(*) as item_count,
                p.name as product_name,
                p.product_id
            FROM inventory i
            JOIN products p ON i.product_id = p.product_id
            WHERE i.location_id = ?
            GROUP BY i.stage, p.product_id, p.name
            ORDER BY i.stage, p.name";
    
    $summary = [];
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $location_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $summary[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    
    return $summary;
}

/**
 * Check if location has available capacity
 */
function checkLocationCapacity($location_id, $required_weight_kg) {
    $capacity = calculateLocationCapacity($location_id);
    
    // Get location's maximum capacity
    global $conn;
    $sql = "SELECT capacity_kg, capacity_m3 FROM locations WHERE location_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $location_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $location = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        $available_weight = $location['capacity_kg'] - $capacity['weight_kg'];
        $available_volume = $location['capacity_m3'] - $capacity['volume_m3'];
        
        return [
            'has_capacity' => $available_weight >= $required_weight_kg,
            'available_weight' => $available_weight,
            'available_volume' => $available_volume,
            'required_weight' => $required_weight_kg
        ];
    }
    return ['has_capacity' => false, 'available_weight' => 0, 'available_volume' => 0, 'required_weight' => $required_weight_kg];
}

/**
 * Update inventory when order is processed
 * This function handles the simple case: move reserved inventory to sold/lost based on status
 */
function updateInventoryForOrder($order_id, $status = 'sold', $user_id = 1) {
    global $conn;
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get order details
        $sql_order = "SELECT op.product_id, op.quantity_kg, o.order_id, o.total_amount
                      FROM order_products op
                      JOIN orders o ON op.order_id = o.order_id
                      WHERE o.order_id = ?";
        
        if ($stmt_order = mysqli_prepare($conn, $sql_order)) {
            mysqli_stmt_bind_param($stmt_order, "i", $order_id);
            mysqli_stmt_execute($stmt_order);
            $result_order = mysqli_stmt_get_result($stmt_order);
            
            while ($order_item = mysqli_fetch_assoc($result_order)) {
                // Find inventory specifically reserved for this order
                $sql_inventory = "SELECT inventory_id, quantity_kg, location_id, order_id
                                 FROM inventory 
                                 WHERE product_id = ? AND stage = 'reserved' AND order_id = ?
                                 ORDER BY expiry_date ASC";
                
                if ($stmt_inventory = mysqli_prepare($conn, $sql_inventory)) {
                    mysqli_stmt_bind_param($stmt_inventory, "ii", $order_item['product_id'], $order_id);
                    mysqli_stmt_execute($stmt_inventory);
                    $result_inventory = mysqli_stmt_get_result($stmt_inventory);
                    
                    $remaining_quantity = $order_item['quantity_kg'];
                    
                    while (($inventory = mysqli_fetch_assoc($result_inventory)) && $remaining_quantity > 0) {
                        $quantity_to_move = min($remaining_quantity, $inventory['quantity_kg']);
                        
                        // Move inventory to the target stage (sold or lost)
                        if ($quantity_to_move == $inventory['quantity_kg']) {
                            // Move entire inventory record
                            $sql_update = "UPDATE inventory SET stage = ?, updated_at = NOW(), updated_by = ? WHERE inventory_id = ?";
                            $update_stmt = mysqli_prepare($conn, $sql_update);
                            mysqli_stmt_bind_param($update_stmt, "sii", $status, $user_id, $inventory['inventory_id']);
                            mysqli_stmt_execute($update_stmt);
                            mysqli_stmt_close($update_stmt);
                        } else {
                            // Reduce quantity from reserved and create new record in target stage
                            $sql_reduce = "UPDATE inventory SET quantity_kg = quantity_kg - ?, updated_at = NOW(), updated_by = ? WHERE inventory_id = ?";
                            $reduce_stmt = mysqli_prepare($conn, $sql_reduce);
                            mysqli_stmt_bind_param($reduce_stmt, "dii", $quantity_to_move, $user_id, $inventory['inventory_id']);
                            mysqli_stmt_execute($reduce_stmt);
                            mysqli_stmt_close($reduce_stmt);
                            
                            // Create new inventory record in target stage
                            $inventory_code = generateInventoryId();
                            $sql_create = "INSERT INTO inventory (inventory_code, product_id, location_id, quantity_kg, stage, order_id, created_at, created_by) 
                                          VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
                            $create_stmt = mysqli_prepare($conn, $sql_create);
                            mysqli_stmt_bind_param($create_stmt, "siidsii", $inventory_code, $order_item['product_id'], $inventory['location_id'], $quantity_to_move, $status, $order_id, $user_id);
                            mysqli_stmt_execute($create_stmt);
                            mysqli_stmt_close($create_stmt);
                        }
                        
                        $remaining_quantity -= $quantity_to_move;
                    }
                    mysqli_stmt_close($stmt_inventory);
                }
            }
            mysqli_stmt_close($stmt_order);
        }
        
        mysqli_commit($conn);
        return true;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error updating inventory for order: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and update expired inventory
 */
function checkAndUpdateExpiredInventory() {
    global $conn;
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get expired inventory items
        $sql = "SELECT inventory_id, product_id, quantity_kg, location_id 
                FROM inventory 
                WHERE expiry_date < CURDATE() AND stage = 'available'";
        
        $expired_items = [];
        if ($result = mysqli_query($conn, $sql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $expired_items[] = $row;
            }
            mysqli_free_result($result);
        }
        
        // Update expired items to lost status
        $sql_update = "UPDATE inventory 
                       SET stage = 'lost', updated_at = NOW() 
                       WHERE expiry_date < CURDATE() AND stage = 'available'";
        
        $result = mysqli_query($conn, $sql_update);
        
        if ($result) {
            $affected_rows = mysqli_affected_rows($conn);
            
            // Log supply chain events for expired items
            foreach ($expired_items as $item) {
                $sql_event = "INSERT INTO supply_chain_events 
                             (event_type, product_id, quantity_kg, location_id, notes, created_by) 
                             VALUES ('expired', ?, ?, ?, 'Automatically marked as expired', 1)";
                
                if ($stmt = mysqli_prepare($conn, $sql_event)) {
                    mysqli_stmt_bind_param($stmt, "idi", $item['product_id'], $item['quantity_kg'], $item['location_id']);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
            
            if ($affected_rows > 0) {
                error_log("Marked $affected_rows inventory items as expired/lost");
            }
            
            mysqli_commit($conn);
            return $affected_rows;
        }
        
        mysqli_rollback($conn);
        return 0;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error updating expired inventory: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get expiring inventory (within specified days)
 */
function getExpiringInventory($days = 7) {
    global $conn;
    
    $sql = "SELECT 
                i.inventory_id,
                i.quantity_kg,
                i.expiry_date,
                p.name as product_name,
                l.name as location_name,
                DATEDIFF(i.expiry_date, CURDATE()) as days_until_expiry
            FROM inventory i
            JOIN products p ON i.product_id = p.product_id
            JOIN locations l ON i.location_id = l.location_id
            WHERE i.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND i.stage = 'available'
            ORDER BY i.expiry_date ASC";
    
    $expiring = [];
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $days);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $expiring[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    
    return $expiring;
}

/**
 * Get total sales value (based on delivered shipments)
 */
function getTotalSalesValue() {
    global $conn;
    
    // Calculate based on delivered shipments (order values)
    $sql = "SELECT 
                SUM(o.total_amount) as total_sales
            FROM orders o
            JOIN shipments s ON o.order_id = s.order_id
            WHERE s.status = 'delivered'";
    
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    
    return $row['total_sales'] ?? 0;
}

/**
 * Get total loss value (based on failed shipments)
 */
function getTotalLossValue() {
    global $conn;
    
    // Calculate loss based on failed shipments (order values)
    $sql = "SELECT 
                SUM(o.total_amount) as total_loss
            FROM orders o
            JOIN shipments s ON o.order_id = s.order_id
            WHERE s.status = 'failed'";
    
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    
    return $row['total_loss'] ?? 0;
}

/**
 * Get inventory statistics for dashboard
 */
function getInventoryStats() {
    global $conn;
    
    $stats = [];
    
    // Total available inventory
    $sql_available = "SELECT SUM(quantity_kg) as total_kg, COUNT(*) as count 
                      FROM inventory WHERE stage = 'available'";
    $result = mysqli_query($conn, $sql_available);
    $available = mysqli_fetch_assoc($result);
    $stats['available'] = $available;
    
    // Total sold inventory
    $sql_sold = "SELECT SUM(quantity_kg) as total_kg, COUNT(*) as count 
                 FROM inventory WHERE stage = 'sold'";
    $result = mysqli_query($conn, $sql_sold);
    $sold = mysqli_fetch_assoc($result);
    $stats['sold'] = $sold;
    
    // Total lost inventory (including damaged)
    $sql_lost = "SELECT SUM(quantity_kg) as total_kg, COUNT(*) as count 
                 FROM inventory WHERE stage IN ('lost', 'damaged')";
    $result = mysqli_query($conn, $sql_lost);
    $lost = mysqli_fetch_assoc($result);
    $stats['lost'] = $lost;
    
    // Total reserved inventory
    $sql_reserved = "SELECT SUM(quantity_kg) as total_kg, COUNT(*) as count 
                     FROM inventory WHERE stage = 'reserved'";
    $result = mysqli_query($conn, $sql_reserved);
    $reserved = mysqli_fetch_assoc($result);
    $stats['reserved'] = $reserved;
    
    // Expiring soon (within 7 days)
    $sql_expiring = "SELECT SUM(quantity_kg) as total_kg, COUNT(*) as count 
                     FROM inventory 
                     WHERE stage = 'available' 
                     AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    $result = mysqli_query($conn, $sql_expiring);
    $expiring = mysqli_fetch_assoc($result);
    $stats['expiring_soon'] = $expiring;
    
    return $stats;
}

/**
 * Update shipment status and handle inventory accordingly
 * Simplified version that handles the core business logic
 */
function updateShipmentStatus($shipment_id, $new_status, $logged_in_user_id, $planned_departure = NULL, $planned_arrival = NULL, $notes = '', $failure_photo = NULL) {
    global $conn;
    
    // Validate status
    $valid_statuses = ['pending', 'assigned', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        return false;
    }
    
    // Get current shipment info
    $sql_get = "SELECT s.*, sr.request_code, sr.product_id, sr.quantity_kg 
                FROM shipments s 
                LEFT JOIN shipment_requests sr ON s.request_id = sr.request_id
                WHERE s.shipment_id = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql_get)) {
        mysqli_stmt_bind_param($stmt, "i", $shipment_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $shipment = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$shipment) {
            return false;
        }
        
        // Update shipment status
        $sql_update = "UPDATE shipments SET status = ?, updated_by = ?, updated_at = NOW()";
        $params = [$new_status, $logged_in_user_id];
        $types = "si";
        
        // Add optional fields
        if ($planned_departure) {
            $sql_update .= ", planned_departure = ?";
            $params[] = $planned_departure;
            $types .= "s";
        }
        if ($planned_arrival) {
            $sql_update .= ", planned_arrival = ?";
            $params[] = $planned_arrival;
            $types .= "s";
        }
        if ($notes) {
            $sql_update .= ", notes = CONCAT(notes, '\n', ?)";
            $params[] = $notes;
            $types .= "s";
        }
        if ($failure_photo) {
            $sql_update .= ", failure_photo = ?";
            $params[] = $failure_photo;
            $types .= "s";
        }
        if ($new_status == 'delivered') {
            $sql_update .= ", actual_arrival = NOW()";
        }
        
        $sql_update .= " WHERE shipment_id = ?";
        $params[] = $shipment_id;
        $types .= "i";
        
        if ($stmt = mysqli_prepare($conn, $sql_update)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            if ($success) {
                // Log the event
                logSupplyChainEvent($conn, 'shipment_status_updated', $shipment_id, $logged_in_user_id);
                
                // If delivered, handle inventory based on shipment type
                if ($new_status == 'delivered') {
                    // Check if this is a farm request shipment (no order_id) or regular order shipment
                    if ($shipment['order_id']) {
                        // Regular order shipment - deduct inventory and calculate sold/loss
                        error_log("Processing regular order shipment delivery for shipment_id: $shipment_id, order_id: " . $shipment['order_id']);
                        updateInventoryForOrder($shipment['order_id'], 'sold', $logged_in_user_id);
                    } else {
                        // Farm request shipment - add inventory to destination warehouse
                        error_log("Processing farm request shipment delivery for shipment_id: $shipment_id, request_id: " . $shipment['request_id']);
                        $result = createInventoryFromDeliveredShipment($shipment_id);
                        if ($result) {
                            error_log("Successfully created inventory from farm request shipment: $shipment_id");
                        } else {
                            error_log("Failed to create inventory from farm request shipment: $shipment_id");
                        }
                    }
                } elseif ($new_status == 'failed') {
                    // Check if this is a farm request shipment or regular order shipment
                    if ($shipment['order_id']) {
                        // Regular order shipment - handle loss calculation
                        updateInventoryForOrder($shipment['order_id'], 'lost', $logged_in_user_id);
                    }
                    // For farm request shipments, no loss calculation needed
                }
                
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Restore inventory from final stages (sold/lost) back to intermediate stages
 */
function restoreInventoryFromFinalStage($conn, $order_id, $from_status, $to_status, $user_id) {
    error_log("Restoring inventory from $from_status to $to_status for order $order_id");
    
    // Get order products
    $sql_order = "SELECT op.product_id, op.quantity_kg
                  FROM order_products op
                  WHERE op.order_id = ?";
    
    if ($stmt_order = mysqli_prepare($conn, $sql_order)) {
        mysqli_stmt_bind_param($stmt_order, "i", $order_id);
        mysqli_stmt_execute($stmt_order);
        $result_order = mysqli_stmt_get_result($stmt_order);
        
        while ($order_item = mysqli_fetch_assoc($result_order)) {
            // Determine the stage to restore to based on the new status
            $restore_stage = 'available'; // Default
            if ($to_status == 'in_transit' || $to_status == 'out_for_delivery') {
                $restore_stage = 'in-transit';
            } elseif ($to_status == 'assigned') {
                $restore_stage = 'reserved';
            }
            
            // Find inventory in sold/lost stage for this product
            $final_stage = ($from_status == 'delivered') ? 'sold' : 'lost';
            $sql_inventory = "SELECT inventory_id, quantity_kg, location_id
                             FROM inventory 
                             WHERE product_id = ? AND stage = ? 
                             ORDER BY expiry_date ASC";
            
            if ($stmt_inventory = mysqli_prepare($conn, $sql_inventory)) {
                mysqli_stmt_bind_param($stmt_inventory, "is", $order_item['product_id'], $final_stage);
                mysqli_stmt_execute($stmt_inventory);
                $result_inventory = mysqli_stmt_get_result($stmt_inventory);
                
                $remaining_quantity = $order_item['quantity_kg'];
                error_log("Need to restore $remaining_quantity kg for product {$order_item['product_id']} from $final_stage to $restore_stage");
                
                while (($inventory = mysqli_fetch_assoc($result_inventory)) && $remaining_quantity > 0) {
                    $quantity_to_restore = min($remaining_quantity, $inventory['quantity_kg']);
                    error_log("Restoring $quantity_to_restore kg from inventory_id {$inventory['inventory_id']}");
                    
                    if ($quantity_to_restore == $inventory['quantity_kg']) {
                        // Move entire inventory record to new stage
                        $sql_update = "UPDATE inventory SET stage = ?, updated_at = NOW(), updated_by = ? WHERE inventory_id = ?";
                        $update_stmt = mysqli_prepare($conn, $sql_update);
                        mysqli_stmt_bind_param($update_stmt, "sii", $restore_stage, $user_id, $inventory['inventory_id']);
                        mysqli_stmt_execute($update_stmt);
                        mysqli_stmt_close($update_stmt);
                    } else {
                        // Reduce quantity and create new record in restore stage
                        $sql_reduce = "UPDATE inventory SET quantity_kg = quantity_kg - ?, updated_at = NOW(), updated_by = ? WHERE inventory_id = ?";
                        $reduce_stmt = mysqli_prepare($conn, $sql_reduce);
                        mysqli_stmt_bind_param($reduce_stmt, "dii", $quantity_to_restore, $user_id, $inventory['inventory_id']);
                        mysqli_stmt_execute($reduce_stmt);
                        mysqli_stmt_close($reduce_stmt);
                        
                        // Create new inventory record in restore stage
                        $inventory_code = generateInventoryId();
                        $sql_create = "INSERT INTO inventory (inventory_code, product_id, location_id, quantity_kg, stage, created_at, created_by) 
                                      VALUES (?, ?, ?, ?, ?, NOW(), ?)";
                        $create_stmt = mysqli_prepare($conn, $sql_create);
                        mysqli_stmt_bind_param($create_stmt, "siidsi", $inventory_code, $order_item['product_id'], $inventory['location_id'], $quantity_to_restore, $restore_stage, $user_id);
                        mysqli_stmt_execute($create_stmt);
                        mysqli_stmt_close($create_stmt);
                    }
                    
                    $remaining_quantity -= $quantity_to_restore;
                }
                mysqli_stmt_close($stmt_inventory);
            }
        }
        mysqli_stmt_close($stmt_order);
    }
}

/**
 * Move inventory between stages (for intermediate stages like available, reserved, in-transit)
 */
function moveInventoryStage($conn, $product_id, $location_id, $quantity_kg, $from_stage, $to_stage, $user_id) {
    // Check if source inventory exists and has sufficient quantity
    $sql_check = "SELECT inventory_id, quantity_kg, order_id FROM inventory 
                  WHERE product_id = ? AND location_id = ? AND stage = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "iis", $product_id, $location_id, $from_stage);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $source_inventory = mysqli_fetch_assoc($result_check);
    mysqli_stmt_close($stmt_check);

    if (!$source_inventory || $source_inventory['quantity_kg'] < $quantity_kg) {
        throw new Exception("Insufficient inventory for product ID $product_id in $from_stage stage");
    }

    // Deduct from source stage
    $sql_deduct = "UPDATE inventory SET quantity_kg = quantity_kg - ?, updated_at = NOW(), updated_by = ? 
                   WHERE inventory_id = ?";
    $stmt_deduct = mysqli_prepare($conn, $sql_deduct);
    mysqli_stmt_bind_param($stmt_deduct, "dii", $quantity_kg, $user_id, $source_inventory['inventory_id']);
    mysqli_stmt_execute($stmt_deduct);
    mysqli_stmt_close($stmt_deduct);

    // Add to destination stage (upsert)
    $inventory_code = generateInventoryId();
    $sql_upsert = "INSERT INTO inventory (inventory_code, product_id, location_id, quantity_kg, stage, order_id, created_at, created_by) 
                   VALUES (?, ?, ?, ?, ?, ?, NOW(), ?) 
                   ON DUPLICATE KEY UPDATE 
                   quantity_kg = quantity_kg + VALUES(quantity_kg), 
                   updated_at = NOW(), 
                   updated_by = VALUES(created_by)";
    $stmt_upsert = mysqli_prepare($conn, $sql_upsert);
    $order_id = $source_inventory['order_id'] ?? null;
    mysqli_stmt_bind_param($stmt_upsert, "siidsii", $inventory_code, $product_id, $location_id, $quantity_kg, $to_stage, $order_id, $user_id);
    mysqli_stmt_execute($stmt_upsert);
    mysqli_stmt_close($stmt_upsert);
}

/**
 * Log supply chain events
 */
function logSupplyChainEvent($conn, $event_type, $shipment_id, $user_id, $product_id = NULL, $quantity_kg = NULL, $location_id = NULL, $order_id = NULL, $notes = NULL) {
    try {
        // For shipment events, we don't need product_id and quantity_kg
        if (in_array($event_type, ['shipment_started', 'shipment_out_for_delivery', 'shipment_delivered', 'shipment_failed', 'shipment_reverted', 'shipment_status_changed', 'shipment_assigned', 'shipment_in_transit', 'shipment_status_updated', 'inventory_created'])) {
            $sql_event = "INSERT INTO supply_chain_events (event_type, shipment_id, event_date, created_by) 
                          VALUES (?, ?, NOW(), ?)";
            $stmt_event = mysqli_prepare($conn, $sql_event);
            if (!$stmt_event) {
                error_log("Error preparing shipment event statement: " . mysqli_error($conn));
                return false;
            }
            mysqli_stmt_bind_param($stmt_event, "sii", $event_type, $shipment_id, $user_id);
        } else {
            // For other events, we need product_id and quantity_kg
            $sql_event = "INSERT INTO supply_chain_events (event_type, product_id, quantity_kg, location_id, shipment_id, order_id, event_date, notes, created_by) 
                          VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
            $stmt_event = mysqli_prepare($conn, $sql_event);
            if (!$stmt_event) {
                error_log("Error preparing general event statement: " . mysqli_error($conn));
                return false;
            }
            mysqli_stmt_bind_param($stmt_event, "sddiiissi", $event_type, $product_id, $quantity_kg, $location_id, $shipment_id, $order_id, $notes, $user_id);
        }
        
        $result = mysqli_stmt_execute($stmt_event);
        if (!$result) {
            error_log("Error executing event log statement: " . mysqli_stmt_error($stmt_event));
        }
        mysqli_stmt_close($stmt_event);
        return $result;
    } catch (Exception $e) {
        error_log("Error in logSupplyChainEvent: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent inventory activities
 */
function getRecentInventoryActivities($limit = 10) {
    global $conn;
    
    $sql = "SELECT 
                i.inventory_id,
                p.name as product_name,
                i.quantity_kg,
                i.stage,
                i.updated_at,
                l.name as location_name
            FROM inventory i
            JOIN products p ON i.product_id = p.product_id
            JOIN locations l ON i.location_id = l.location_id
            WHERE i.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY i.updated_at DESC
            LIMIT ?";
    
    $activities = [];
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $activities[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
    
    return $activities;
}

/**
 * Get capacity summary for warehouses
 */
function getCapacitySummary($conn, $user_id, $user_role) {
    $locations = [];
    
    if ($user_role == 'warehouse_manager') {
        // Get only locations assigned to this warehouse manager
        $sql = "SELECT l.location_id, l.name, l.type, l.capacity_kg, l.capacity_m3
                FROM locations l
                JOIN user_assigned_locations ual ON l.location_id = ual.location_id
                WHERE ual.user_id = ? AND l.type = 'warehouse'
                ORDER BY l.name ASC";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $locations[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        // Admin can see all warehouse locations
        $sql = "SELECT location_id, name, type, capacity_kg, capacity_m3
                FROM locations 
                WHERE type = 'warehouse'
                ORDER BY name ASC";
        
        $result = mysqli_query($conn, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $locations[] = $row;
            }
        }
    }
    
    // Calculate current usage for each location
    foreach ($locations as &$location) {
        $capacity = calculateLocationCapacity($location['location_id']);
        
        $location['current_weight'] = $capacity['weight_kg'];
        $location['current_volume'] = $capacity['volume_m3'];
        
        $location['weight_usage_percent'] = $location['capacity_kg'] > 0 ? 
            ($capacity['weight_kg'] / $location['capacity_kg']) * 100 : 0;
        $location['volume_usage_percent'] = $location['capacity_m3'] > 0 ? 
            ($capacity['volume_m3'] / $location['capacity_m3']) * 100 : 0;
    }
    
    return $locations;
}

/**
 * Get real-time inventory statistics
 */
function getRealTimeInventoryStats($user_id = null, $user_role = null) {
    global $conn;
    
    $stats = [];
    
    // Build location filter for warehouse managers
    $location_filter = "";
    if ($user_role == 'warehouse_manager' && $user_id) {
        $sql_assigned = "SELECT location_id FROM user_assigned_locations WHERE user_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql_assigned)) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $assigned_locations = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $assigned_locations[] = $row['location_id'];
            }
            mysqli_stmt_close($stmt);
            
            if (!empty($assigned_locations)) {
                $location_ids = implode(',', $assigned_locations);
                $location_filter = " AND location_id IN ($location_ids)";
            } else {
                // No assigned locations, return empty stats
                return [
                    'available' => ['total_kg' => 0, 'count' => 0, 'product_count' => 0, 'location_count' => 0],
                    'sold' => ['total_kg' => 0, 'count' => 0],
                    'lost' => ['total_kg' => 0, 'count' => 0],
                    'in_transit' => ['total_kg' => 0, 'count' => 0],
                    'warehouse_capacity' => []
                ];
            }
        }
    }
    
    // Total available inventory
    $sql_available = "SELECT 
                        SUM(quantity_kg) as total_kg, 
                        COUNT(*) as count,
                        COUNT(DISTINCT product_id) as product_count,
                        COUNT(DISTINCT location_id) as location_count
                      FROM inventory 
                      WHERE stage = 'available'" . $location_filter;
    $result = mysqli_query($conn, $sql_available);
    $available = mysqli_fetch_assoc($result);
    $stats['available'] = $available;
    
    // Total sold inventory
    $sql_sold = "SELECT 
                    SUM(quantity_kg) as total_kg, 
                    COUNT(*) as count
                 FROM inventory 
                 WHERE stage = 'sold'" . $location_filter;
    $result = mysqli_query($conn, $sql_sold);
    $sold = mysqli_fetch_assoc($result);
    $stats['sold'] = $sold;
    
    // Total lost/damaged inventory
    $sql_lost = "SELECT 
                    SUM(quantity_kg) as total_kg, 
                    COUNT(*) as count
                 FROM inventory 
                 WHERE stage IN ('lost', 'damaged')" . $location_filter;
    $result = mysqli_query($conn, $sql_lost);
    $lost = mysqli_fetch_assoc($result);
    $stats['lost'] = $lost;
    
    // In-transit inventory
    $sql_transit = "SELECT 
                       SUM(quantity_kg) as total_kg, 
                       COUNT(*) as count
                    FROM inventory 
                    WHERE stage = 'in-transit'" . $location_filter;
    $result = mysqli_query($conn, $sql_transit);
    $transit = mysqli_fetch_assoc($result);
    $stats['in_transit'] = $transit;
    
    // Reserved inventory
    $sql_reserved = "SELECT 
                       SUM(quantity_kg) as total_kg, 
                       COUNT(*) as count
                    FROM inventory 
                    WHERE stage = 'reserved'" . $location_filter;
    $result = mysqli_query($conn, $sql_reserved);
    $reserved = mysqli_fetch_assoc($result);
    $stats['reserved'] = $reserved;
    
    // Warehouse capacity utilization
    $capacity_where = "WHERE l.type = 'warehouse'";
    if ($user_role == 'warehouse_manager' && $user_id && !empty($assigned_locations)) {
        $location_ids = implode(',', $assigned_locations);
        $capacity_where .= " AND l.location_id IN ($location_ids)";
    }
    
    $sql_capacity = "SELECT 
                        l.location_id,
                        l.name,
                        l.capacity_kg,
                        l.capacity_m3,
                        COALESCE(SUM(i.quantity_kg), 0) as current_weight,
                        COALESCE(SUM(i.quantity_kg * 0.001), 0) as current_volume
                     FROM locations l
                     LEFT JOIN inventory i ON l.location_id = i.location_id 
                        AND i.stage = 'available'
                     $capacity_where
                     GROUP BY l.location_id, l.name, l.capacity_kg, l.capacity_m3";
    
    $result = mysqli_query($conn, $sql_capacity);
    $capacity_data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $capacity_data[] = $row;
    }
    $stats['warehouse_capacity'] = $capacity_data;
    
    return $stats;
}

/**
 * Validate inventory capacity for real-time operations
 */
function validateInventoryCapacity($conn, $location_id, $product_id, $quantity, $operation, $existing_inventory_id = null) {
    // Get location capacity
    $sql_location = "SELECT capacity_kg, capacity_m3 FROM locations WHERE location_id = ?";
    $location = null;
    if ($stmt = mysqli_prepare($conn, $sql_location)) {
        mysqli_stmt_bind_param($stmt, "i", $location_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $location = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
    
    if (!$location) {
        return ['valid' => false, 'message' => 'Location not found'];
    }
    
    // Calculate current inventory
    $current_capacity = calculateLocationCapacity($location_id);
    
    // Calculate new capacity based on operation
    $new_weight = $current_capacity['weight_kg'];
    $new_volume = $current_capacity['volume_m3'];
    
    if ($operation == 'add') {
        $new_weight += $quantity;
        $new_volume += ($quantity * 0.001); // 1kg ≈ 0.001 m³
    } elseif ($operation == 'remove') {
        $new_weight -= $quantity;
        $new_volume -= ($quantity * 0.001);
    } elseif ($operation == 'update' && $existing_inventory_id) {
        // Get current quantity of the inventory being updated
        $sql_current = "SELECT quantity_kg FROM inventory WHERE inventory_id = ?";
        $current_quantity = 0;
        if ($stmt = mysqli_prepare($conn, $sql_current)) {
            mysqli_stmt_bind_param($stmt, "i", $existing_inventory_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            $current_quantity = $row['quantity_kg'] ?? 0;
            mysqli_stmt_close($stmt);
        }
        
        // Calculate difference
        $difference = $quantity - $current_quantity;
        $new_weight += $difference;
        $new_volume += ($difference * 0.001);
    }
    
    // Check capacity limits
    $weight_valid = $location['capacity_kg'] === null || $new_weight <= $location['capacity_kg'];
    $volume_valid = $location['capacity_m3'] === null || $new_volume <= $location['capacity_m3'];
    
    $weight_usage_percent = $location['capacity_kg'] > 0 ? ($new_weight / $location['capacity_kg']) * 100 : 0;
    $volume_usage_percent = $location['capacity_m3'] > 0 ? ($new_volume / $location['capacity_m3']) * 100 : 0;
    
    return [
        'valid' => $weight_valid && $volume_valid,
        'message' => $weight_valid && $volume_valid ? 'Capacity check passed' : 'Capacity exceeded',
        'current_weight' => $current_capacity['weight_kg'],
        'current_volume' => $current_capacity['volume_m3'],
        'new_weight' => $new_weight,
        'new_volume' => $new_volume,
        'capacity_kg' => $location['capacity_kg'],
        'capacity_m3' => $location['capacity_m3'],
        'weight_usage_percent' => $weight_usage_percent,
        'volume_usage_percent' => $volume_usage_percent,
        'available_weight' => $location['capacity_kg'] ? $location['capacity_kg'] - $new_weight : null,
        'available_volume' => $location['capacity_m3'] ? $location['capacity_m3'] - $new_volume : null
    ];
}

/**
 * Auto-update expired inventory (called via cron job or manual trigger)
 */
function autoUpdateExpiredInventory() {
    global $conn;
    
    $updated_count = checkAndUpdateExpiredInventory();
    
    if ($updated_count > 0) {
        error_log("Auto-updated $updated_count expired inventory items");
        
        // Send notification to admins (if notification system exists)
        // This could be expanded to send emails or push notifications
        
        return [
            'success' => true,
            'updated_count' => $updated_count,
            'message' => "Successfully updated $updated_count expired inventory items"
        ];
    }
    
    return [
        'success' => true,
        'updated_count' => 0,
        'message' => 'No expired inventory found'
    ];
}

/**
 * Get inventory alerts (low stock, expiring soon, capacity warnings)
 */
function getInventoryAlerts($user_id = null, $user_role = null) {
    global $conn;
    
    $alerts = [];
    
    // Build location filter for warehouse managers
    $location_filter = "";
    if ($user_role == 'warehouse_manager' && $user_id) {
        $sql_assigned = "SELECT location_id FROM user_assigned_locations WHERE user_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql_assigned)) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $assigned_locations = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $assigned_locations[] = $row['location_id'];
            }
            mysqli_stmt_close($stmt);
            
            if (!empty($assigned_locations)) {
                $location_ids = implode(',', $assigned_locations);
                $location_filter = " AND i.location_id IN ($location_ids)";
            } else {
                // No assigned locations, return empty alerts
                return [];
            }
        }
    }
    
    // Low stock alerts (less than 100kg of any product)
    $sql_low_stock = "SELECT 
                        p.name as product_name,
                        l.name as location_name,
                        SUM(i.quantity_kg) as total_quantity
                     FROM inventory i
                     JOIN products p ON i.product_id = p.product_id
                     JOIN locations l ON i.location_id = l.location_id
                     WHERE i.stage = 'available'" . $location_filter . "
                     GROUP BY i.product_id, i.location_id
                     HAVING total_quantity < 100";
    
    $result = mysqli_query($conn, $sql_low_stock);
    while ($row = mysqli_fetch_assoc($result)) {
        $alerts[] = [
            'type' => 'low_stock',
            'message' => "Low stock alert: {$row['product_name']} at {$row['location_name']} ({$row['total_quantity']} kg)",
            'data' => $row
        ];
    }
    
    // Capacity warnings (warehouses at 80%+ capacity)
    $capacity_where = "WHERE l.type = 'warehouse' AND l.capacity_kg > 0";
    if ($user_role == 'warehouse_manager' && $user_id && !empty($assigned_locations)) {
        $location_ids = implode(',', $assigned_locations);
        $capacity_where .= " AND l.location_id IN ($location_ids)";
    }
    
    $sql_capacity = "SELECT 
                        l.name,
                        l.capacity_kg,
                        COALESCE(SUM(i.quantity_kg), 0) as current_weight,
                        (COALESCE(SUM(i.quantity_kg), 0) / l.capacity_kg) * 100 as usage_percent
                     FROM locations l
                     LEFT JOIN inventory i ON l.location_id = i.location_id 
                        AND i.stage = 'available'
                     $capacity_where
                     GROUP BY l.location_id, l.name, l.capacity_kg
                     HAVING usage_percent >= 80";
    
    $result = mysqli_query($conn, $sql_capacity);
    while ($row = mysqli_fetch_assoc($result)) {
        $alerts[] = [
            'type' => 'capacity_warning',
            'message' => "Capacity warning: {$row['name']} at " . number_format($row['usage_percent'], 1) . "% capacity",
            'data' => $row
        ];
    }
    
    return $alerts;
}

/**
 * Reserve inventory for an order (move from 'available' to 'reserved')
 */
function reserveInventoryForOrder($conn, $product_id, $quantity_kg, $order_id, $user_id) {
    // Start transaction for atomicity
    mysqli_begin_transaction($conn);
    
    try {
        // Log the reservation attempt
        error_log("Reserving inventory: Product ID $product_id, Quantity $quantity_kg kg, Order ID $order_id, User ID $user_id");
        
        // Check current available inventory
        $sql_check = "SELECT SUM(quantity_kg) as available_kg FROM inventory WHERE product_id = ? AND stage = 'available'";
        $stmt_check = mysqli_prepare($conn, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "i", $product_id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $available = mysqli_fetch_assoc($result_check);
        mysqli_stmt_close($stmt_check);
        
        $available_kg = $available['available_kg'] ?? 0;
        error_log("Available inventory for product $product_id: $available_kg kg");
        
        if ($available_kg < $quantity_kg) {
            throw new Exception("Insufficient inventory to reserve for product ID $product_id. Available: $available_kg kg, Requested: $quantity_kg kg");
        }
        
        // Find available inventory records for this product, ordered by expiry date
        $sql_find_inventory = "SELECT inventory_id, quantity_kg, location_id, expiry_date
                              FROM inventory 
                              WHERE product_id = ? AND stage = 'available' 
                              ORDER BY expiry_date ASC";
        $stmt_find = mysqli_prepare($conn, $sql_find_inventory);
        mysqli_stmt_bind_param($stmt_find, "i", $product_id);
        mysqli_stmt_execute($stmt_find);
        $result_find = mysqli_stmt_get_result($stmt_find);
        
        $remaining_quantity = $quantity_kg;
        $reserved_records = 0;
        
        while (($inventory = mysqli_fetch_assoc($result_find)) && $remaining_quantity > 0) {
            $quantity_to_reserve = min($remaining_quantity, $inventory['quantity_kg']);
            
            if ($quantity_to_reserve == $inventory['quantity_kg']) {
                // Reserve entire inventory record
                $sql_reserve = "UPDATE inventory SET stage = 'reserved', order_id = ?, updated_at = NOW(), updated_by = ? WHERE inventory_id = ?";
                $stmt_reserve = mysqli_prepare($conn, $sql_reserve);
                mysqli_stmt_bind_param($stmt_reserve, "iii", $order_id, $user_id, $inventory['inventory_id']);
                mysqli_stmt_execute($stmt_reserve);
                mysqli_stmt_close($stmt_reserve);
                error_log("Reserved entire inventory record ID " . $inventory['inventory_id'] . " ($quantity_to_reserve kg)");
            } else {
                // Reduce quantity and create new reserved record
                $sql_reduce = "UPDATE inventory SET quantity_kg = quantity_kg - ?, updated_at = NOW(), updated_by = ? WHERE inventory_id = ?";
                $stmt_reduce = mysqli_prepare($conn, $sql_reduce);
                mysqli_stmt_bind_param($stmt_reduce, "dii", $quantity_to_reserve, $user_id, $inventory['inventory_id']);
                mysqli_stmt_execute($stmt_reduce);
                mysqli_stmt_close($stmt_reduce);
                
                // Create reserved inventory record
                $inventory_code = generateInventoryId();
                $sql_create_reserved = "INSERT INTO inventory (inventory_code, product_id, location_id, quantity_kg, stage, order_id, expiry_date, created_at, created_by) 
                                       VALUES (?, ?, ?, ?, 'reserved', ?, ?, NOW(), ?)";
                $stmt_create = mysqli_prepare($conn, $sql_create_reserved);
                mysqli_stmt_bind_param($stmt_create, "siiisii", $inventory_code, $product_id, $inventory['location_id'], $quantity_to_reserve, $order_id, $inventory['expiry_date'], $user_id);
                mysqli_stmt_execute($stmt_create);
                $new_inventory_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt_create);
                error_log("Created new reserved inventory record ID $new_inventory_id ($quantity_to_reserve kg)");
            }
            
            $remaining_quantity -= $quantity_to_reserve;
            $reserved_records++;
        }
        
        mysqli_stmt_close($stmt_find);
        
        if ($remaining_quantity > 0) {
            throw new Exception("Insufficient inventory to reserve for product ID $product_id. Could only reserve " . ($quantity_kg - $remaining_quantity) . " kg out of $quantity_kg kg requested");
        }
        
        // Verify the reservation was successful
        $sql_verify = "SELECT SUM(quantity_kg) as reserved_kg FROM inventory WHERE product_id = ? AND stage = 'reserved'";
        $stmt_verify = mysqli_prepare($conn, $sql_verify);
        mysqli_stmt_bind_param($stmt_verify, "i", $product_id);
        mysqli_stmt_execute($stmt_verify);
        $result_verify = mysqli_stmt_get_result($stmt_verify);
        $reserved = mysqli_fetch_assoc($result_verify);
        mysqli_stmt_close($stmt_verify);
        
        $total_reserved = $reserved['reserved_kg'] ?? 0;
        error_log("Successfully reserved $quantity_kg kg for product $product_id. Total reserved now: $total_reserved kg");
        
        mysqli_commit($conn);
        return true;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error reserving inventory: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Create unique inventory reservation for a specific order
 * This function creates separate inventory records for each order
 */
function createUniqueInventoryReservation($conn, $product_id, $quantity_kg, $order_id, $user_id) {
    // Start transaction for atomicity
    mysqli_begin_transaction($conn);
    
    try {
        // Log the reservation attempt
        error_log("Creating unique inventory reservation: Product ID $product_id, Quantity $quantity_kg kg, Order ID $order_id, User ID $user_id");
        
        // Check current available inventory
        $sql_check = "SELECT SUM(quantity_kg) as available_kg FROM inventory WHERE product_id = ? AND stage = 'available'";
        $stmt_check = mysqli_prepare($conn, $sql_check);
        if ($stmt_check === false) {
            error_log("MySQLi Prepare Error: " . mysqli_error($conn) . " for query: " . $sql_check);
            throw new Exception("Failed to prepare inventory check statement: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt_check, "i", $product_id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $available = mysqli_fetch_assoc($result_check);
        mysqli_stmt_close($stmt_check);
        
        $available_kg = $available['available_kg'] ?? 0;
        error_log("Available inventory for product $product_id: $available_kg kg");
        
        if ($available_kg < $quantity_kg) {
            throw new Exception("Insufficient inventory to reserve for product ID $product_id. Available: $available_kg kg, Requested: $quantity_kg kg");
        }
        
        // Find available inventory records for this product, ordered by expiry date
        $sql_find_inventory = "SELECT inventory_id, quantity_kg, location_id, expiry_date
                              FROM inventory 
                              WHERE product_id = ? AND stage = 'available' 
                              ORDER BY expiry_date ASC";
        $stmt_find = mysqli_prepare($conn, $sql_find_inventory);
        mysqli_stmt_bind_param($stmt_find, "i", $product_id);
        mysqli_stmt_execute($stmt_find);
        $result_find = mysqli_stmt_get_result($stmt_find);
        
        $remaining_quantity = $quantity_kg;
        $reserved_records = 0;
        $reserved_inventory_ids = array();
        
        while (($inventory = mysqli_fetch_assoc($result_find)) && $remaining_quantity > 0) {
            $quantity_to_reserve = min($remaining_quantity, $inventory['quantity_kg']);
            
                                        // Always create a NEW reserved inventory record for this order
        $inventory_code = generateInventoryId();
        $sql_create_reserved = "INSERT INTO inventory (inventory_code, product_id, location_id, quantity_kg, stage, expiry_date, order_id, created_at, created_by)
                               VALUES (?, ?, ?, ?, 'reserved', ?, ?, NOW(), ?)";
        $stmt_create = mysqli_prepare($conn, $sql_create_reserved);
        if ($stmt_create === false) {
            error_log("MySQLi Prepare Error: " . mysqli_error($conn) . " for query: " . $sql_create_reserved);
            throw new Exception("Failed to prepare inventory creation statement: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt_create, "siiisii", $inventory_code, $product_id, $inventory['location_id'], $quantity_to_reserve, $inventory['expiry_date'], $order_id, $user_id);
            mysqli_stmt_execute($stmt_create);
            $new_inventory_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_create);
            
            $reserved_inventory_ids[] = $new_inventory_id;
            error_log("Created unique reserved inventory record ID $new_inventory_id ($quantity_to_reserve kg) for order $order_id");
            
            // Reduce the available inventory
            if ($quantity_to_reserve == $inventory['quantity_kg']) {
                // Remove entire inventory record
                $sql_delete = "DELETE FROM inventory WHERE inventory_id = ?";
                $stmt_delete = mysqli_prepare($conn, $sql_delete);
                if ($stmt_delete === false) {
                    error_log("MySQLi Prepare Error: " . mysqli_error($conn) . " for query: " . $sql_delete);
                    throw new Exception("Failed to prepare inventory delete statement: " . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt_delete, "i", $inventory['inventory_id']);
                mysqli_stmt_execute($stmt_delete);
                mysqli_stmt_close($stmt_delete);
                error_log("Removed entire available inventory record ID " . $inventory['inventory_id']);
            } else {
                // Reduce quantity of available inventory
                $sql_reduce = "UPDATE inventory SET quantity_kg = quantity_kg - ?, updated_at = NOW(), updated_by = ? WHERE inventory_id = ?";
                $stmt_reduce = mysqli_prepare($conn, $sql_reduce);
                if ($stmt_reduce === false) {
                    error_log("MySQLi Prepare Error: " . mysqli_error($conn) . " for query: " . $sql_reduce);
                    throw new Exception("Failed to prepare inventory reduce statement: " . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt_reduce, "dii", $quantity_to_reserve, $user_id, $inventory['inventory_id']);
                mysqli_stmt_execute($stmt_reduce);
                mysqli_stmt_close($stmt_reduce);
                error_log("Reduced available inventory record ID " . $inventory['inventory_id'] . " by $quantity_to_reserve kg");
            }
            
            $remaining_quantity -= $quantity_to_reserve;
            $reserved_records++;
        }
        
        mysqli_stmt_close($stmt_find);
        
        if ($remaining_quantity > 0) {
            throw new Exception("Insufficient inventory to reserve for product ID $product_id. Could only reserve " . ($quantity_kg - $remaining_quantity) . " kg out of $quantity_kg kg requested");
        }
        
        // Verify the reservation was successful
        $sql_verify = "SELECT SUM(quantity_kg) as reserved_kg FROM inventory WHERE product_id = ? AND stage = 'reserved' AND order_id = ?";
        $stmt_verify = mysqli_prepare($conn, $sql_verify);
        if ($stmt_verify === false) {
            error_log("MySQLi Prepare Error: " . mysqli_error($conn) . " for query: " . $sql_verify);
            throw new Exception("Failed to prepare inventory verify statement: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt_verify, "ii", $product_id, $order_id);
        mysqli_stmt_execute($stmt_verify);
        $result_verify = mysqli_stmt_get_result($stmt_verify);
        $reserved = mysqli_fetch_assoc($result_verify);
        mysqli_stmt_close($stmt_verify);
        
        $total_reserved = $reserved['reserved_kg'] ?? 0;
        error_log("Successfully created unique reservation: $quantity_kg kg for product $product_id, order $order_id. Total reserved for this order: $total_reserved kg");
        
        mysqli_commit($conn);
        return $reserved_inventory_ids;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error creating unique inventory reservation: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Move inventory from 'sold' to 'lost' stage
 */
function moveInventoryFromSoldToLost($conn, $order_id, $user_id) {
    $sql_order_products = "SELECT op.product_id, op.quantity_kg
                          FROM order_products op
                          WHERE op.order_id = ?";
    $stmt_products = mysqli_prepare($conn, $sql_order_products);
    mysqli_stmt_bind_param($stmt_products, "i", $order_id);
    mysqli_stmt_execute($stmt_products);
    $result_products = mysqli_stmt_get_result($stmt_products);
    
    while ($order_item = mysqli_fetch_assoc($result_products)) {
        // Find available inventory for this product
        $sql_inventory = "SELECT inventory_id, quantity_kg, location_id, created_by
                         FROM inventory 
                         WHERE product_id = ? AND stage = 'sold' 
                         ORDER BY expiry_date ASC";
        $stmt_inventory = mysqli_prepare($conn, $sql_inventory);
        mysqli_stmt_bind_param($stmt_inventory, "i", $order_item['product_id']);
        mysqli_stmt_execute($stmt_inventory);
        $result_inventory = mysqli_stmt_get_result($stmt_inventory);
        
        $remaining_quantity = $order_item['quantity_kg'];
        
        while (($inventory = mysqli_fetch_assoc($result_inventory)) && $remaining_quantity > 0) {
            $quantity_to_move = min($remaining_quantity, $inventory['quantity_kg']);
            
            if ($quantity_to_move == $inventory['quantity_kg']) {
                // Move entire inventory record to lost stage
                $sql_update = "UPDATE inventory SET stage = 'lost', updated_at = NOW() WHERE inventory_id = ?";
                $update_stmt = mysqli_prepare($conn, $sql_update);
                mysqli_stmt_bind_param($update_stmt, "i", $inventory['inventory_id']);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            } else {
                // Reduce quantity from sold and create new lost record
                $sql_reduce = "UPDATE inventory SET quantity_kg = quantity_kg - ?, updated_at = NOW() WHERE inventory_id = ?";
                $reduce_stmt = mysqli_prepare($conn, $sql_reduce);
                mysqli_stmt_bind_param($reduce_stmt, "di", $quantity_to_move, $inventory['inventory_id']);
                mysqli_stmt_execute($reduce_stmt);
                mysqli_stmt_close($reduce_stmt);
                
                // Create new inventory record in lost stage
                $inventory_code = generateInventoryId();
                $sql_create = "INSERT INTO inventory (inventory_code, product_id, location_id, quantity_kg, stage, order_id, created_at, created_by) 
                              VALUES (?, ?, ?, ?, 'lost', ?, NOW(), ?)";
                $create_stmt = mysqli_prepare($conn, $sql_create);
                $created_by = $inventory['created_by'] ?? 1;
                mysqli_stmt_bind_param($create_stmt, "siidsii", $inventory_code, $order_item['product_id'], $inventory['location_id'], $quantity_to_move, $order_id, $created_by);
                mysqli_stmt_execute($create_stmt);
                mysqli_stmt_close($create_stmt);
            }
            
            $remaining_quantity -= $quantity_to_move;
        }
        mysqli_stmt_close($stmt_inventory);
    }
    mysqli_stmt_close($stmt_products);
}

/**
 * Move inventory from 'lost' to 'sold' stage
 */
function moveInventoryFromLostToSold($conn, $order_id, $user_id) {
    $sql_order_products = "SELECT op.product_id, op.quantity_kg
                          FROM order_products op
                          WHERE op.order_id = ?";
    $stmt_products = mysqli_prepare($conn, $sql_order_products);
    mysqli_stmt_bind_param($stmt_products, "i", $order_id);
    mysqli_stmt_execute($stmt_products);
    $result_products = mysqli_stmt_get_result($stmt_products);
    
    while ($order_item = mysqli_fetch_assoc($result_products)) {
        // Find available inventory for this product
        $sql_inventory = "SELECT inventory_id, quantity_kg, location_id, created_by
                         FROM inventory 
                         WHERE product_id = ? AND stage = 'lost' 
                         ORDER BY expiry_date ASC";
        $stmt_inventory = mysqli_prepare($conn, $sql_inventory);
        mysqli_stmt_bind_param($stmt_inventory, "i", $order_item['product_id']);
        mysqli_stmt_execute($stmt_inventory);
        $result_inventory = mysqli_stmt_get_result($stmt_inventory);
        
        $remaining_quantity = $order_item['quantity_kg'];
        
        while (($inventory = mysqli_fetch_assoc($result_inventory)) && $remaining_quantity > 0) {
            $quantity_to_move = min($remaining_quantity, $inventory['quantity_kg']);
            
            if ($quantity_to_move == $inventory['quantity_kg']) {
                // Move entire inventory record to sold stage
                $sql_update = "UPDATE inventory SET stage = 'sold', updated_at = NOW() WHERE inventory_id = ?";
                $update_stmt = mysqli_prepare($conn, $sql_update);
                mysqli_stmt_bind_param($update_stmt, "i", $inventory['inventory_id']);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            } else {
                // Reduce quantity from lost and create new sold record
                $sql_reduce = "UPDATE inventory SET quantity_kg = quantity_kg - ?, updated_at = NOW() WHERE inventory_id = ?";
                $reduce_stmt = mysqli_prepare($conn, $sql_reduce);
                mysqli_stmt_bind_param($reduce_stmt, "di", $quantity_to_move, $inventory['inventory_id']);
                mysqli_stmt_execute($reduce_stmt);
                mysqli_stmt_close($reduce_stmt);
                
                // Create new inventory record in sold stage
                $inventory_code = generateInventoryId();
                $sql_create = "INSERT INTO inventory (inventory_code, product_id, location_id, quantity_kg, stage, order_id, created_at, created_by) 
                              VALUES (?, ?, ?, ?, 'sold', ?, NOW(), ?)";
                $create_stmt = mysqli_prepare($conn, $sql_create);
                $created_by = $inventory['created_by'] ?? 1;
                mysqli_stmt_bind_param($create_stmt, "siidsii", $inventory_code, $order_item['product_id'], $inventory['location_id'], $quantity_to_move, $order_id, $created_by);
                mysqli_stmt_execute($create_stmt);
                mysqli_stmt_close($create_stmt);
            }
            
            $remaining_quantity -= $quantity_to_move;
        }
        mysqli_stmt_close($stmt_inventory);
    }
    mysqli_stmt_close($stmt_products);
}

/**
 * Reset inventory state for testing (move all reserved back to available)
 * WARNING: This function should only be used for testing/debugging
 */
function resetInventoryForTesting($conn, $product_id = null) {
    mysqli_begin_transaction($conn);
    
    try {
        if ($product_id) {
            // Reset specific product
            $sql = "UPDATE inventory SET stage = 'available', updated_at = NOW() WHERE product_id = ? AND stage = 'reserved'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $product_id);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_affected_rows($conn);
            mysqli_stmt_close($stmt);
            
            error_log("Reset $affected reserved inventory records for product $product_id back to available");
        } else {
            // Reset all reserved inventory
            $sql = "UPDATE inventory SET stage = 'available', updated_at = NOW() WHERE stage = 'reserved'";
            $result = mysqli_query($conn, $sql);
            $affected = mysqli_affected_rows($conn);
            
            error_log("Reset $affected reserved inventory records back to available");
        }
        
        mysqli_commit($conn);
        return $affected;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error resetting inventory: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Get detailed inventory information for debugging
 */
function getDetailedInventoryInfo($conn, $product_id = null) {
    $info = [];
    
    if ($product_id) {
        // Get detailed info for specific product
        $sql = "SELECT i.inventory_id, i.quantity_kg, i.stage, i.created_at, i.updated_at,
                       p.name as product_name, l.name as location_name
                FROM inventory i
                JOIN products p ON i.product_id = p.product_id
                JOIN locations l ON i.location_id = l.location_id
                WHERE i.product_id = ?
                ORDER BY i.stage, i.created_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $info[] = $row;
        }
        mysqli_stmt_close($stmt);
    } else {
        // Get summary for all products
        $sql = "SELECT p.product_id, p.name as product_name,
                       SUM(CASE WHEN i.stage = 'available' THEN i.quantity_kg ELSE 0 END) as available_kg,
                       SUM(CASE WHEN i.stage = 'reserved' THEN i.quantity_kg ELSE 0 END) as reserved_kg,
                       COUNT(CASE WHEN i.stage = 'reserved' THEN 1 END) as reserved_records
                FROM products p
                LEFT JOIN inventory i ON p.product_id = i.product_id
                GROUP BY p.product_id, p.name
                HAVING available_kg > 0 OR reserved_kg > 0
                ORDER BY reserved_kg DESC";
        $result = mysqli_query($conn, $sql);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $info[] = $row;
        }
    }
    
    return $info;
}

/**
 * Create inventory from delivered shipment
 * Adds products to destination warehouse with +30 days expiry
 */
function createInventoryFromDeliveredShipment($shipment_id) {
    global $conn;
    
    error_log("=== Starting createInventoryFromDeliveredShipment for shipment_id: $shipment_id ===");
    
    // Get shipment details with request information using request_id
    $sql = "SELECT s.*, sr.product_id, sr.quantity_kg, p.name as product_name, p.price_per_unit
            FROM shipments s
            LEFT JOIN shipment_requests sr ON s.request_id = sr.request_id
            LEFT JOIN products p ON sr.product_id = p.product_id
            WHERE s.shipment_id = ? AND s.status = 'delivered'";
    
    error_log("Querying shipment data for shipment_id: $shipment_id");
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $shipment_id);
        $execute_result = mysqli_stmt_execute($stmt);
        
        if (!$execute_result) {
            error_log("Failed to execute shipment query: " . mysqli_error($conn));
            mysqli_stmt_close($stmt);
            return false;
        }
        
        $result = mysqli_stmt_get_result($stmt);
        $shipment = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        error_log("Shipment data retrieved: " . ($shipment ? "SUCCESS" : "FAILED"));
        if ($shipment) {
            error_log("Shipment details: request_id=" . $shipment['request_id'] . 
                     ", product_id=" . $shipment['product_id'] . 
                     ", quantity_kg=" . $shipment['quantity_kg'] . 
                     ", destination_location_id=" . $shipment['destination_location_id']);
        }
        
        if (!$shipment || !$shipment['product_id']) {
            error_log("No valid shipment or product found for shipment_id: " . $shipment_id);
            return false;
        }
        
        // Calculate expiry date (+30 days from shipment creation)
        $expiry_date = date('Y-m-d', strtotime($shipment['created_at'] . ' +30 days'));
        
        // Generate inventory code
        require_once 'id_generator.php';
        $inventory_code = generateInventoryId();
        
        error_log("Creating inventory with: Product ID: " . $shipment['product_id'] . 
                 ", Destination Location: " . $shipment['destination_location_id'] . 
                 ", Quantity: " . $shipment['quantity_kg'] . 
                 ", Expiry: " . $expiry_date . 
                 ", Inventory Code: " . $inventory_code);
        
        // Create inventory record
        $sql_insert = "INSERT INTO inventory (inventory_code, product_id, location_id, quantity_kg, 
                       stage, expiry_date, created_by) 
                       VALUES (?, ?, ?, ?, 'available', ?, ?)";
        
        if ($stmt = mysqli_prepare($conn, $sql_insert)) {
            $user_id = $_SESSION['user_id'] ?? 1; // Fallback to admin user if session not available
            
            error_log("Preparing to insert with parameters: inventory_code=$inventory_code, product_id=" . $shipment['product_id'] . 
                     ", location_id=" . $shipment['destination_location_id'] . 
                     ", quantity_kg=" . $shipment['quantity_kg'] . 
                     ", expiry_date=$expiry_date, user_id=$user_id");
            
            mysqli_stmt_bind_param($stmt, "siidsi", $inventory_code, $shipment['product_id'], 
                                 $shipment['destination_location_id'], $shipment['quantity_kg'], 
                                 $expiry_date, $user_id);
            
            $success = mysqli_stmt_execute($stmt);
            
            if (!$success) {
                error_log("Failed to execute inventory insert: " . mysqli_error($conn));
                error_log("MySQL Error: " . mysqli_stmt_error($stmt));
            } else {
                error_log("Inventory insert executed successfully");
            }
            
            mysqli_stmt_close($stmt);
            
            if ($success) {
                // Log the event
                logSupplyChainEvent($conn, 'inventory_created', $shipment_id, $user_id, 
                                   $shipment['product_id'], $shipment['quantity_kg'], 
                                   $shipment['destination_location_id'], null, 
                                   "Inventory created from delivered farm request shipment. Product: " . $shipment['product_name'] . 
                                   ", Quantity: " . $shipment['quantity_kg'] . " kg, Expiry: " . $expiry_date);
                
                error_log("Successfully created inventory from delivered farm request shipment. Shipment ID: $shipment_id, Product: " . $shipment['product_name'] . ", Quantity: " . $shipment['quantity_kg'] . " kg, Destination: " . $shipment['destination_location_id']);
                return true;
            } else {
                error_log("Failed to create inventory record for shipment_id: " . $shipment_id);
            }
        } else {
            error_log("Failed to prepare inventory insert statement for shipment_id: " . $shipment_id . ". Error: " . mysqli_error($conn));
        }
    } else {
        error_log("Failed to prepare shipment query for shipment_id: " . $shipment_id . ". Error: " . mysqli_error($conn));
    }
    
    error_log("=== Ending createInventoryFromDeliveredShipment for shipment_id: $shipment_id (FAILED) ===");
    return false;
}
?>