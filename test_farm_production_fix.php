<?php
require_once 'config/db.php';
require_once 'utils/id_generator.php';

echo "<h2>Testing Farm Production ID Generation Fix</h2>";

// Test the generateFarmProductionId function multiple times
echo "<h3>Testing generateFarmProductionId Function (10 iterations):</h3>";
$generated_codes = [];

for ($i = 1; $i <= 10; $i++) {
    $new_code = generateFarmProductionId();
    $generated_codes[] = $new_code;
    echo "Generated code $i: <strong>$new_code</strong><br>";
}

// Check for duplicates in generated codes
$duplicates = array_diff_assoc($generated_codes, array_unique($generated_codes));
if (empty($duplicates)) {
    echo "<p style='color: green;'>✅ No duplicates in generated codes!</p>";
} else {
    echo "<p style='color: red;'>❌ Found duplicates: " . implode(', ', $duplicates) . "</p>";
}

// Check existing farm production codes
echo "<h3>Existing Farm Production Codes:</h3>";
$sql_existing = "SELECT production_code FROM farm_production ORDER BY production_code";
$result_existing = mysqli_query($conn, $sql_existing);

if ($result_existing) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Production Code</th></tr>";
    
    while ($row = mysqli_fetch_assoc($result_existing)) {
        echo "<tr><td>" . $row['production_code'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . mysqli_error($conn);
}

// Test concurrent simulation
echo "<h3>Simulating Concurrent Access:</h3>";
$concurrent_codes = [];

// Simulate multiple users creating records simultaneously
for ($i = 1; $i <= 5; $i++) {
    $concurrent_codes[] = generateFarmProductionId();
}

echo "Concurrent codes generated: " . implode(', ', $concurrent_codes) . "<br>";

$concurrent_duplicates = array_diff_assoc($concurrent_codes, array_unique($concurrent_codes));
if (empty($concurrent_duplicates)) {
    echo "<p style='color: green;'>✅ No duplicates in concurrent simulation!</p>";
} else {
    echo "<p style='color: red;'>❌ Found duplicates in concurrent simulation: " . implode(', ', $concurrent_duplicates) . "</p>";
}

mysqli_close($conn);
?>
