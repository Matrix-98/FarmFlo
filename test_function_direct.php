<?php
require_once 'config/db.php';
require_once 'utils/id_generator.php';

echo "<h2>Direct Function Test</h2>";

// Test the function directly
echo "<h3>Testing generateUniqueFarmProductionId:</h3>";
$new_code = generateUniqueFarmProductionId();
echo "Generated code: <strong>$new_code</strong><br>";

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

// Show all existing codes
echo "<h3>All existing codes:</h3>";
$sql = "SELECT production_code FROM farm_production WHERE production_code LIKE 'FP25%' ORDER BY production_code";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo $row['production_code'] . "<br>";
    }
}

mysqli_close($conn);
?>
