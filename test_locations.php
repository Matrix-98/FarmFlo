<?php
require_once 'config/db.php';

echo "<h2>Testing Locations Table</h2>";

// Check all locations
$sql = "SELECT location_id, location_code, name, type, capacity_kg, capacity_m3 FROM locations ORDER BY location_id";
$result = mysqli_query($conn, $sql);

if ($result) {
    echo "<h3>All Locations:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Code</th><th>Name</th><th>Type</th><th>Capacity KG</th><th>Capacity m³</th></tr>";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['location_id'] . "</td>";
        echo "<td>" . $row['location_code'] . "</td>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td>" . $row['type'] . "</td>";
        echo "<td>" . $row['capacity_kg'] . "</td>";
        echo "<td>" . $row['capacity_m3'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . mysqli_error($conn);
}

// Check warehouse locations specifically
echo "<h3>Warehouse Locations Only:</h3>";
$sql_warehouse = "SELECT location_id, location_code, name, type, capacity_kg, capacity_m3 
                  FROM locations 
                  WHERE type = 'warehouse' 
                  ORDER BY location_id";
$result_warehouse = mysqli_query($conn, $sql_warehouse);

if ($result_warehouse) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Code</th><th>Name</th><th>Type</th><th>Capacity KG</th><th>Capacity m³</th></tr>";
    
    while ($row = mysqli_fetch_assoc($result_warehouse)) {
        echo "<tr>";
        echo "<td>" . $row['location_id'] . "</td>";
        echo "<td>" . $row['location_code'] . "</td>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td>" . $row['type'] . "</td>";
        echo "<td>" . $row['capacity_kg'] . "</td>";
        echo "<td>" . $row['capacity_m3'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . mysqli_error($conn);
}

// Check for duplicate location codes
echo "<h3>Checking for Duplicate Location Codes:</h3>";
$sql_duplicates = "SELECT location_code, COUNT(*) as count 
                   FROM locations 
                   GROUP BY location_code 
                   HAVING COUNT(*) > 1";
$result_duplicates = mysqli_query($conn, $sql_duplicates);

if ($result_duplicates && mysqli_num_rows($result_duplicates) > 0) {
    echo "<p style='color: red;'>Found duplicate location codes:</p>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Location Code</th><th>Count</th></tr>";
    
    while ($row = mysqli_fetch_assoc($result_duplicates)) {
        echo "<tr>";
        echo "<td>" . $row['location_code'] . "</td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'>No duplicate location codes found.</p>";
}

mysqli_close($conn);
?>
