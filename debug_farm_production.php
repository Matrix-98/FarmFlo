<?php
require_once 'config/db.php';

echo "<h2>Debugging Farm Production Codes</h2>";

// First, let's check the table structure
echo "<h3>Farm Production Table Structure:</h3>";
$sql_structure = "DESCRIBE farm_production";
$result_structure = mysqli_query($conn, $sql_structure);

if ($result_structure) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    while ($row = mysqli_fetch_assoc($result_structure)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . mysqli_error($conn);
}

// Check existing farm production codes (without the problematic column)
echo "<h3>Existing Farm Production Codes:</h3>";
$sql_existing = "SELECT production_id, production_code FROM farm_production ORDER BY production_code";
$result_existing = mysqli_query($conn, $sql_existing);

if ($result_existing) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Production Code</th></tr>";
    
    while ($row = mysqli_fetch_assoc($result_existing)) {
        echo "<tr>";
        echo "<td>" . $row['production_id'] . "</td>";
        echo "<td>" . $row['production_code'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . mysqli_error($conn);
}

// Test the current year and what codes would be generated
echo "<h3>Current Year Analysis:</h3>";
$year = date('y');
echo "Current year (2 digits): $year<br>";

// Check what the next code should be (using the corrected SUBSTRING position)
$sql_max = "SELECT MAX(CAST(SUBSTRING(production_code, 5) AS UNSIGNED)) as max_num 
            FROM farm_production 
            WHERE production_code LIKE 'FP{$year}%'";
$result_max = mysqli_query($conn, $sql_max);

if ($result_max && $row = mysqli_fetch_assoc($result_max)) {
    $next_num = ($row['max_num'] ?? 0) + 1;
    echo "Next number for year $year: $next_num<br>";
    echo "Next code would be: FP" . $year . str_pad($next_num, 3, '0', STR_PAD_LEFT) . "<br>";
} else {
    echo "Error getting max number: " . mysqli_error($conn) . "<br>";
}

// Test the generateFarmProductionId function
echo "<h3>Testing generateFarmProductionId Function:</h3>";
require_once 'utils/id_generator.php';

for ($i = 1; $i <= 5; $i++) {
    $new_code = generateFarmProductionId();
    echo "Generated code $i: <strong>$new_code</strong><br>";
}

// Test the new generateUniqueFarmProductionId function
echo "<h3>Testing generateUniqueFarmProductionId Function:</h3>";
for ($i = 1; $i <= 5; $i++) {
    $new_code = generateUniqueFarmProductionId();
    echo "Generated code $i: <strong>$new_code</strong><br>";
}

mysqli_close($conn);
?>
