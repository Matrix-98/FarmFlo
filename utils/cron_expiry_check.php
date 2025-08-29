<?php
/**
 * Cron Job Script for Automatic Shelf Life Management
 * 
 * This script should be run periodically (e.g., daily) to automatically:
 * 1. Check for expired inventory and mark as lost
 * 2. Send notifications for expiring inventory
 * 3. Update supply chain events
 * 
 * Usage:
 * - Add to crontab: 0 2 * * * /usr/bin/php /path/to/agri_logistics/utils/cron_expiry_check.php
 * - Or run manually: php utils/cron_expiry_check.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start timing
$start_time = microtime(true);

// Include required files
require_once __DIR__ . '/../config/db.php';
require_once 'inventory_helpers.php';

// Log function
function logCronMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] CRON: $message" . PHP_EOL;
    
    // Log to file
    $log_file = __DIR__ . '/../logs/cron_expiry.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    
    // Also output to console if running from command line
    if (php_sapi_name() === 'cli') {
        echo $log_message;
    }
}

try {
    logCronMessage("Starting automatic shelf life management check");
    
    // Check if database connection is available
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // 1. Auto-update expired inventory
    logCronMessage("Checking for expired inventory...");
    $expiry_result = autoUpdateExpiredInventory();
    
    if ($expiry_result['success']) {
        logCronMessage("Expired inventory check completed: " . $expiry_result['message']);
    } else {
        logCronMessage("Error in expired inventory check: " . $expiry_result['message']);
    }
    
    // 2. Get expiring inventory (within 7 days)
    logCronMessage("Checking for inventory expiring within 7 days...");
    $expiring_inventory = getExpiringInventory(7);
    
    if (!empty($expiring_inventory)) {
        logCronMessage("Found " . count($expiring_inventory) . " items expiring within 7 days");
        
        // Group by location for better reporting
        $location_groups = [];
        foreach ($expiring_inventory as $item) {
            $location = $item['location_name'];
            if (!isset($location_groups[$location])) {
                $location_groups[$location] = [];
            }
            $location_groups[$location][] = $item;
        }
        
        foreach ($location_groups as $location => $items) {
            $total_kg = array_sum(array_column($items, 'quantity'));
            logCronMessage("Location '$location': " . count($items) . " items expiring, total " . number_format($total_kg, 2) . " kg");
        }
    } else {
        logCronMessage("No inventory expiring within 7 days");
    }
    
    // 3. Get inventory alerts
    logCronMessage("Checking for inventory alerts...");
    $alerts = getInventoryAlerts();
    
    if (!empty($alerts)) {
        logCronMessage("Found " . count($alerts) . " inventory alerts");
        
        $low_stock_count = 0;
        $capacity_warning_count = 0;
        
        foreach ($alerts as $alert) {
            if ($alert['type'] == 'low_stock') {
                $low_stock_count++;
            } elseif ($alert['type'] == 'capacity_warning') {
                $capacity_warning_count++;
            }
        }
        
        logCronMessage("Alerts breakdown: $low_stock_count low stock, $capacity_warning_count capacity warnings");
    } else {
        logCronMessage("No inventory alerts found");
    }
    
    // 4. Get real-time statistics for reporting
    logCronMessage("Generating inventory statistics...");
    $stats = getRealTimeInventoryStats();
    
    $total_available = $stats['available']['total_kg'] ?? 0;
    $total_sold = $stats['sold']['total_kg'] ?? 0;
    $total_lost = $stats['lost']['total_kg'] ?? 0;
    $total_transit = $stats['in_transit']['total_kg'] ?? 0;
    
    logCronMessage("Inventory Summary:");
    logCronMessage("- Available: " . number_format($total_available, 2) . " kg");
    logCronMessage("- In Transit: " . number_format($total_transit, 2) . " kg");
    logCronMessage("- Total Sold: " . number_format($total_sold, 2) . " kg");
    logCronMessage("- Total Lost: " . number_format($total_lost, 2) . " kg");
    
    // 5. Warehouse capacity utilization report
    if (!empty($stats['warehouse_capacity'])) {
        logCronMessage("Warehouse Capacity Utilization:");
        foreach ($stats['warehouse_capacity'] as $warehouse) {
            $usage_percent = $warehouse['capacity_kg'] > 0 ? 
                ($warehouse['current_weight'] / $warehouse['capacity_kg']) * 100 : 0;
            
            $status = $usage_percent >= 90 ? 'CRITICAL' : ($usage_percent >= 80 ? 'WARNING' : 'OK');
            logCronMessage("- {$warehouse['name']}: " . number_format($usage_percent, 1) . "% ($status)");
        }
    }
    
    // Calculate execution time
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    
    logCronMessage("Automatic shelf life management completed successfully in {$execution_time} seconds");
    
    // Return success status for command line usage
    if (php_sapi_name() === 'cli') {
        exit(0);
    }
    
} catch (Exception $e) {
    $error_message = "Error in automatic shelf life management: " . $e->getMessage();
    logCronMessage($error_message);
    
    // Log full error details
    error_log($error_message);
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return error status for command line usage
    if (php_sapi_name() === 'cli') {
        exit(1);
    }
}
?>
