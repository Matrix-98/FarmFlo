<?php
require_once 'config/db.php';
require_once 'utils/id_generator.php';

echo "<h2>Simple Farm Production ID Test</h2>";

// Check what codes exist
echo "<h3>Existing Codes:</h3>";
$sql = "SELECT production_code FROM farm_production WHERE production_code LIKE 'FP25%' ORDER BY production_code";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo $row['production_code'] . "<br>";
    }
}

// Test the function
echo "<h3>Testing generateUniqueFarmProductionId:</h3>";
$new_code = generateUniqueFarmProductionId();
echo "Generated: <strong>$new_code</strong><br>";

// Check if it exists
$check_sql = "SELECT COUNT(*) as count FROM farm_production WHERE production_code = ?";
$stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($stmt, "s", $new_code);
mysqli_stmt_execute($stmt);
$check_result = mysqli_stmt_get_result($stmt);
$check_row = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($stmt);

if ($check_row['count'] > 0) {
    echo "<span style='color: red;'>❌ Code already exists!</span><br>";
} else {
    echo "<span style='color: green;'>✅ Code is unique!</span><br>";
}

mysqli_close($conn);
?>
