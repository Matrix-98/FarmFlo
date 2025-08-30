<?php
require_once 'config/db.php';
require_once 'utils/id_generator.php';

echo "<h2>Simulating Farm Production Create Logic</h2>";

// Simulate the exact same logic as the create page
$max_retries = 5;
$production_code = null;

for ($i = 0; $i < $max_retries; $i++) {
    $production_code = generateUniqueFarmProductionId();
    
    echo "Attempt $i: Generated code: <strong>$production_code</strong><br>";
    
    // Check if this code already exists
    $check_sql = "SELECT COUNT(*) as count FROM farm_production WHERE production_code = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $production_code);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $check_row = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_stmt);
    
    echo "Code $production_code exists: " . ($check_row['count'] > 0 ? 'YES' : 'NO') . "<br>";
    
    if ($check_row['count'] == 0) {
        echo "<span style='color: green;'>✅ Using unique code: $production_code</span><br>";
        break; // Code is unique, proceed
    }
    
    // If we're on the last retry, generate a timestamp-based code
    if ($i == $max_retries - 1) {
        $timestamp = time();
        $random = mt_rand(100, 999);
        $production_code = 'FP' . date('y') . substr($timestamp, -3) . $random;
        echo "<span style='color: orange;'>⚠️ Using fallback code: $production_code</span><br>";
    }
}

echo "<hr>";
echo "<h3>Final code to be used: <strong>$production_code</strong></h3>";

mysqli_close($conn);
?>
