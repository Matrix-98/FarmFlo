<?php
require_once 'config/db.php';
require_once 'utils/activity_notifications.php';

echo "<h2>Activity Notification System Test</h2>";

// Test 1: Check if the table exists
echo "<h3>Test 1: Database Table Check</h3>";
$result = mysqli_query($conn, "SHOW TABLES LIKE 'user_dashboard_visits'");
if (mysqli_num_rows($result) > 0) {
    echo "✅ user_dashboard_visits table exists<br>";
} else {
    echo "❌ user_dashboard_visits table does not exist<br>";
}
