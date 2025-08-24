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