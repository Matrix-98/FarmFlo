<?php
require_once 'config/db.php';
require_once 'utils/id_generator.php';

echo "<h2>Testing Actual Farm Production Creation</h2>";

// Generate a code
$production_code = generateUniqueFarmProductionId();
echo "Generated code: <strong>$production_code</strong><br>";

// Check if it exists
$check_sql = "SELECT COUNT(*) as count FROM farm_production WHERE production_code = ?";
$stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($stmt, "s", $production_code);
mysqli_stmt_execute($stmt);
$check_result = mysqli_stmt_get_result($stmt);
$check_row = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($stmt);

if ($check_row['count'] > 0) {
    echo "<span style='color: red;'>❌ Code already exists!</span><br>";
} else {
    echo "<span style='color: green;'>✅ Code is unique!</span><br>";
    
    // Try to insert a test record
    $sql = "INSERT INTO farm_production (production_code, farm_manager_id, product_id, seed_amount_kg, sowing_date, field_name, status, created_by) VALUES (?, ?, ?, ?, ?, ?, 'planted', ?)";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        $farm_manager_id = 1; // Test user ID
        $product_id = 1; // Test product ID
        $seed_amount_kg = 10.00;
        $sowing_date = date('Y-m-d');
        $field_name = 'Test Field';
        $created_by = 1;
        
        mysqli_stmt_bind_param($stmt, "siidssi", $production_code, $farm_manager_id, $product_id, $seed_amount_kg, $sowing_date, $field_name, $created_by);
        
        if (mysqli_stmt_execute($stmt)) {
            echo "<span style='color: green;'>✅ Record created successfully!</span><br>";
            
            // Clean up - delete the test record
            $delete_sql = "DELETE FROM farm_production WHERE production_code = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            mysqli_stmt_bind_param($delete_stmt, "s", $production_code);
            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);
            
            echo "<span style='color: blue;'>🧹 Test record cleaned up</span><br>";
        } else {
            $error_msg = mysqli_error($conn);
            echo "<span style='color: red;'>❌ Error creating record: $error_msg</span><br>";
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo "<span style='color: red;'>❌ Error preparing statement</span><br>";
    }
}

mysqli_close($conn);
?>
