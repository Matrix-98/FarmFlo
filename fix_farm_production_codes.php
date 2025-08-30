<?php
require_once 'config/db.php';

echo "<h2>Checking Farm Production Codes</h2>";

// Check for duplicate production codes
$sql_duplicates = "SELECT production_code, COUNT(*) as count 
                   FROM farm_production 
                   GROUP BY production_code 
                   HAVING COUNT(*) > 1";
$result_duplicates = mysqli_query($conn, $sql_duplicates);

if ($result_duplicates && mysqli_num_rows($result_duplicates) > 0) {
    echo "<h3>Found Duplicate Production Codes:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Production Code</th><th>Count</th></tr>";
    
    while ($row = mysqli_fetch_assoc($result_duplicates)) {
        echo "<tr>";
        echo "<td>" . $row['production_code'] . "</td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'>No duplicate production codes found.</p>";
}

// Check all farm production records
echo "<h3>All Farm Production Records:</h3>";
$sql_all = "SELECT production_id, production_code, product_name, quantity_kg, created_at 
            FROM farm_production 
            ORDER BY production_code";
$result_all = mysqli_query($conn, $sql_all);

if ($result_all) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Production Code</th><th>Product Name</th><th>Quantity</th><th>Created</th></tr>";
    
    while ($row = mysqli_fetch_assoc($result_all)) {
        echo "<tr>";
        echo "<td>" . $row['production_id'] . "</td>";
        echo "<td>" . $row['production_code'] . "</td>";
        echo "<td>" . $row['product_name'] . "</td>";
        echo "<td>" . $row['quantity_kg'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . mysqli_error($conn);
}

// Test the generateFarmProductionId function
echo "<h3>Testing generateFarmProductionId Function:</h3>";
require_once 'utils/id_generator.php';

for ($i = 1; $i <= 5; $i++) {
    $new_code = generateFarmProductionId();
    echo "Generated code $i: $new_code<br>";
}

mysqli_close($conn);
?>
