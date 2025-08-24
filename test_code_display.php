<?php
require_once 'config/db.php';
require_once 'utils/code_helpers.php';

echo "<h1>Code Display Test</h1>";
echo "<p>This script tests the code helper functions to ensure they display the correct 6-digit codes for different entity IDs.</p>";

// Test with some sample data
echo "<h2>Testing Code Helper Functions</h2>";

// Test Order Code
echo "<h3>Order Codes</h3>";
$sql_orders = "SELECT order_id, order_code FROM orders ORDER BY order_id DESC LIMIT 5";
$result = mysqli_query($conn, $sql_orders);
if ($result && mysqli_num_rows($result) > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Order ID</th><th>Database Order Code</th><th>Generated Order Code</th><th>Match?</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        $generated_code = getOrderCode($row['order_id']);
        $match = ($row['order_code'] == $generated_code) ? '✅' : '❌';
        echo "<tr>";
        echo "<td>" . $row['order_id'] . "</td>";
        echo "<td>" . ($row['order_code'] ?: 'NULL') . "</td>";
        echo "<td>" . $generated_code . "</td>";
        echo "<td>" . $match . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No orders found</p>";
}

// Test Shipment Code
echo "<h3>Shipment Codes</h3>";
$sql_shipments = "SELECT shipment_id, shipment_code FROM shipments ORDER BY shipment_id DESC LIMIT 5";
$result = mysqli_query($conn, $sql_shipments);
if ($result && mysqli_num_rows($result) > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Shipment ID</th><th>Database Shipment Code</th><th>Generated Shipment Code</th><th>Match?</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        $generated_code = getShipmentCode($row['shipment_id']);
        $match = ($row['shipment_code'] == $generated_code) ? '✅' : '❌';
        echo "<tr>";
        echo "<td>" . $row['shipment_id'] . "</td>";
        echo "<td>" . ($row['shipment_code'] ?: 'NULL') . "</td>";
        echo "<td>" . $generated_code . "</td>";
        echo "<td>" . $match . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No shipments found</p>";
}

// Test Product Code
echo "<h3>Product Codes</h3>";
$sql_products = "SELECT product_id, product_code FROM products ORDER BY product_id DESC LIMIT 5";
$result = mysqli_query($conn, $sql_products);
if ($result && mysqli_num_rows($result) > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Product ID</th><th>Database Product Code</th><th>Generated Product Code</th><th>Match?</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        $generated_code = getProductCode($row['product_id']);
        $match = ($row['product_code'] == $generated_code) ? '✅' : '❌';
        echo "<tr>";
        echo "<td>" . $row['product_id'] . "</td>";
        echo "<td>" . ($row['product_code'] ?: 'NULL') . "</td>";
        echo "<td>" . $generated_code . "</td>";
        echo "<td>" . $match . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No products found</p>";
}

// Test Inventory Code
echo "<h3>Inventory Codes</h3>";
$sql_inventory = "SELECT inventory_id, inventory_code FROM inventory ORDER BY inventory_id DESC LIMIT 5";
$result = mysqli_query($conn, $sql_inventory);
if ($result && mysqli_num_rows($result) > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Inventory ID</th><th>Database Inventory Code</th><th>Generated Inventory Code</th><th>Match?</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        $generated_code = getInventoryCode($row['inventory_id']);
        $match = ($row['inventory_code'] == $generated_code) ? '✅' : '❌';
        echo "<tr>";
        echo "<td>" . $row['inventory_id'] . "</td>";
        echo "<td>" . ($row['inventory_code'] ?: 'NULL') . "</td>";
        echo "<td>" . $generated_code . "</td>";
        echo "<td>" . $match . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No inventory found</p>";
}

echo "<h2>Test Instructions</h2>";
echo "<ol>";
echo "<li><strong>Expected Behavior:</strong> The 'Generated Code' should match the 'Database Code' if the database has the code stored</li>";
echo "<li><strong>Fallback Behavior:</strong> If the database code is NULL, the generated code should provide a fallback format</li>";
echo "<li><strong>Frontend Display:</strong> The shipments/view.php page should now show codes instead of numerical IDs</li>";
echo "</ol>";

echo "<h2>Quick Test Links</h2>";
echo "<p><a href='shipments/index.php' target='_blank'>View Shipments List</a></p>";
echo "<p><a href='shipments/view.php?id=1' target='_blank'>View Sample Shipment (ID: 1)</a></p>";
echo "<p><a href='shipments/view.php?id=36' target='_blank'>View Sample Shipment (ID: 36)</a></p>";

echo "<h2>Summary</h2>";
echo "<p>The code helper functions should now display 6-digit codes (like O25001, S25001, P25001, I25001) instead of numerical IDs on the shipments/view.php page.</p>";
echo "<p>The backend URLs and logic remain unchanged, only the frontend display is affected.</p>";
?>
