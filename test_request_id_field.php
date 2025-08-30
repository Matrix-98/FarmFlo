<?php
require_once 'config/db.php';

echo "<h2>Testing request_id field and shipment data</h2>";

// Check if request_id field exists
$sql_check_field = "SHOW COLUMNS FROM shipments LIKE 'request_id'";
$result = mysqli_query($conn, $sql_check_field);

if (mysqli_num_rows($result) > 0) {
    echo "<p style='color: green;'>✅ request_id field exists in shipments table</p>";
} else {
    echo "<p style='color: red;'>❌ request_id field does not exist in shipments table</p>";
}

// Check shipments with request_id
$sql_shipments = "SELECT shipment_id, shipment_code, order_id, request_id, status, created_at 
                  FROM shipments 
                  WHERE request_id IS NOT NULL 
                  ORDER BY shipment_id DESC 
                  LIMIT 10";
$result = mysqli_query($conn, $sql_shipments);

echo "<h3>Shipments with request_id:</h3>";
if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Shipment ID</th><th>Shipment Code</th><th>Order ID</th><th>Request ID</th><th>Status</th><th>Created At</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['shipment_id'] . "</td>";
        echo "<td>" . $row['shipment_code'] . "</td>";
        echo "<td>" . ($row['order_id'] ? $row['order_id'] : 'NULL') . "</td>";
        echo "<td>" . $row['request_id'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠️ No shipments found with request_id</p>";
}

// Check shipment requests
$sql_requests = "SELECT request_id, request_code, product_id, quantity_kg, status 
                 FROM shipment_requests 
                 ORDER BY request_id DESC 
                 LIMIT 10";
$result = mysqli_query($conn, $sql_requests);

echo "<h3>Shipment Requests:</h3>";
if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Request ID</th><th>Request Code</th><th>Product ID</th><th>Quantity (kg)</th><th>Status</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['request_id'] . "</td>";
        echo "<td>" . $row['request_code'] . "</td>";
        echo "<td>" . $row['product_id'] . "</td>";
        echo "<td>" . $row['quantity_kg'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠️ No shipment requests found</p>";
}

// Check delivered shipments
$sql_delivered = "SELECT s.shipment_id, s.shipment_code, s.order_id, s.request_id, s.status, 
                         sr.product_id, sr.quantity_kg, p.name as product_name
                  FROM shipments s
                  LEFT JOIN shipment_requests sr ON s.request_id = sr.request_id
                  LEFT JOIN products p ON sr.product_id = p.product_id
                  WHERE s.status = 'delivered'
                  ORDER BY s.shipment_id DESC 
                  LIMIT 10";
$result = mysqli_query($conn, $sql_delivered);

echo "<h3>Delivered Shipments:</h3>";
if (mysqli_num_rows($result) > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Shipment ID</th><th>Shipment Code</th><th>Order ID</th><th>Request ID</th><th>Product ID</th><th>Product Name</th><th>Quantity (kg)</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['shipment_id'] . "</td>";
        echo "<td>" . $row['shipment_code'] . "</td>";
        echo "<td>" . ($row['order_id'] ? $row['order_id'] : 'NULL') . "</td>";
        echo "<td>" . ($row['request_id'] ? $row['request_id'] : 'NULL') . "</td>";
        echo "<td>" . ($row['product_id'] ? $row['product_id'] : 'NULL') . "</td>";
        echo "<td>" . ($row['product_name'] ? $row['product_name'] : 'NULL') . "</td>";
        echo "<td>" . ($row['quantity_kg'] ? $row['quantity_kg'] : 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠️ No delivered shipments found</p>";
}

mysqli_close($conn);
?>
