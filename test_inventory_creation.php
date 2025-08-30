<?php
session_start();
require_once 'config/db.php';
require_once 'utils/inventory_helpers.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Inventory Creation for Delivered Shipment</h2>";

// Test with shipment ID 61 (the one that's already delivered)
$shipment_id = 61;

echo "<p>Testing inventory creation for shipment ID: $shipment_id</p>";

// First, let's check the current inventory for the destination location
$sql_check = "SELECT s.destination_location_id, sr.product_id, sr.quantity_kg, p.name as product_name
              FROM shipments s
              LEFT JOIN shipment_requests sr ON s.request_id = sr.request_id
              LEFT JOIN products p ON sr.product_id = p.product_id
              WHERE s.shipment_id = ?";

$stmt = mysqli_prepare($conn, $sql_check);
mysqli_stmt_bind_param($stmt, "i", $shipment_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$shipment_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($shipment_data) {
    echo "<p><strong>Shipment Data:</strong></p>";
    echo "<ul>";
    echo "<li>Destination Location ID: " . $shipment_data['destination_location_id'] . "</li>";
    echo "<li>Product ID: " . $shipment_data['product_id'] . "</li>";
    echo "<li>Product Name: " . $shipment_data['product_name'] . "</li>";
    echo "<li>Quantity: " . $shipment_data['quantity_kg'] . " kg</li>";
    echo "</ul>";
    
    // Check current inventory at destination
    $sql_inventory = "SELECT SUM(quantity_kg) as total_kg, COUNT(*) as count
                      FROM inventory 
                      WHERE location_id = ? AND product_id = ? AND stage = 'available'";
    $stmt = mysqli_prepare($conn, $sql_inventory);
    mysqli_stmt_bind_param($stmt, "ii", $shipment_data['destination_location_id'], $shipment_data['product_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $current_inventory = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    echo "<p><strong>Current Inventory at Destination:</strong></p>";
    echo "<ul>";
    echo "<li>Total Quantity: " . ($current_inventory['total_kg'] ?? 0) . " kg</li>";
    echo "<li>Number of Records: " . ($current_inventory['count'] ?? 0) . "</li>";
    echo "</ul>";
    
    // Now try to create inventory
    echo "<p><strong>Attempting to create inventory...</strong></p>";
    
    // Set a user ID for testing
    $_SESSION['user_id'] = 1;
    
    // Let's debug step by step
    echo "<p><strong>Step-by-step debugging:</strong></p>";
    
    // Step 1: Check if the function exists
    if (function_exists('createInventoryFromDeliveredShipment')) {
        echo "<p>✅ Function exists</p>";
    } else {
        echo "<p>❌ Function does not exist</p>";
        exit;
    }
    
    // Step 2: Check if we can generate an inventory ID
    if (function_exists('generateInventoryId')) {
        $test_id = generateInventoryId();
        echo "<p>✅ Can generate inventory ID: $test_id</p>";
    } else {
        echo "<p>❌ Cannot generate inventory ID</p>";
        exit;
    }
    
    // Step 3: Check if we can access the database
    if ($conn) {
        echo "<p>✅ Database connection available</p>";
    } else {
        echo "<p>❌ No database connection</p>";
        exit;
    }
    
    // Step 4: Try to call the function
    echo "<p>Calling createInventoryFromDeliveredShipment($shipment_id)...</p>";
    
    $result = createInventoryFromDeliveredShipment($shipment_id);
    
    echo "<p>Function returned: " . ($result ? "TRUE" : "FALSE") . "</p>";
    
    if ($result) {
        echo "<p style='color: green;'>✅ Successfully created inventory!</p>";
        
        // Check inventory again
        $stmt = mysqli_prepare($conn, $sql_inventory);
        mysqli_stmt_bind_param($stmt, "ii", $shipment_data['destination_location_id'], $shipment_data['product_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $new_inventory = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        echo "<p><strong>New Inventory at Destination:</strong></p>";
        echo "<ul>";
        echo "<li>Total Quantity: " . ($new_inventory['total_kg'] ?? 0) . " kg</li>";
        echo "<li>Number of Records: " . ($new_inventory['count'] ?? 0) . "</li>";
        echo "</ul>";
        
        // Show the actual inventory records
        $sql_records = "SELECT inventory_id, inventory_code, quantity_kg, expiry_date, created_at
                       FROM inventory 
                       WHERE location_id = ? AND product_id = ? AND stage = 'available'
                       ORDER BY created_at DESC";
        $stmt = mysqli_prepare($conn, $sql_records);
        mysqli_stmt_bind_param($stmt, "ii", $shipment_data['destination_location_id'], $shipment_data['product_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        echo "<p><strong>Inventory Records:</strong></p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Inventory ID</th><th>Inventory Code</th><th>Quantity (kg)</th><th>Expiry Date</th><th>Created At</th></tr>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>" . $row['inventory_id'] . "</td>";
            echo "<td>" . $row['inventory_code'] . "</td>";
            echo "<td>" . $row['quantity_kg'] . "</td>";
            echo "<td>" . $row['expiry_date'] . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        mysqli_stmt_close($stmt);
        
    } else {
        echo "<p style='color: red;'>❌ Failed to create inventory!</p>";
        echo "<p>Check the error logs for more details.</p>";
        
        // Show the last few error log entries
        echo "<p><strong>Recent Error Log Entries:</strong></p>";
        
        // Try different possible log file paths
        $possible_log_files = [
            'C:/xampp/apache/logs/error.log',
            'D:/xampp/apache/logs/error.log',
            'C:/xampp/logs/apache_error.log',
            'D:/xampp/logs/apache_error.log'
        ];
        
        $log_found = false;
        foreach ($possible_log_files as $log_file) {
            if (file_exists($log_file)) {
                echo "<p>Found log file at: $log_file</p>";
                $lines = file($log_file);
                $recent_lines = array_slice($lines, -30); // Last 30 lines
                echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow-y: auto;'>";
                foreach ($recent_lines as $line) {
                    if (strpos($line, 'createInventoryFromDeliveredShipment') !== false || 
                        strpos($line, 'inventory') !== false ||
                        strpos($line, 'shipment') !== false) {
                        echo htmlspecialchars($line);
                    }
                }
                echo "</pre>";
                $log_found = true;
                break;
            }
        }
        
        if (!$log_found) {
            echo "<p>No error log file found. Trying to get PHP errors directly...</p>";
            
            // Try to capture errors directly
            ob_start();
            $result = createInventoryFromDeliveredShipment($shipment_id);
            $output = ob_get_clean();
            
            if ($output) {
                echo "<p><strong>Direct Error Output:</strong></p>";
                echo "<pre style='background: #f5f5f5; padding: 10px;'>" . htmlspecialchars($output) . "</pre>";
            }
        }
    }
    
} else {
    echo "<p style='color: red;'>❌ Could not find shipment data for ID: $shipment_id</p>";
}

mysqli_close($conn);
?>
