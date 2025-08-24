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

// Test 2: Check if records exist
echo "<h3>Test 2: User Records Check</h3>";
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM user_dashboard_visits");
$row = mysqli_fetch_assoc($result);
echo "Found {$row['count']} user dashboard visit records<br>";

// Test 3: Test activity detection for admin
echo "<h3>Test 3: Admin Activity Detection</h3>";
$admin_id = 1; // Assuming admin has user_id = 1
$has_activities = hasNewActivities($admin_id, 'admin');
echo "Admin has new activities: " . ($has_activities ? "Yes" : "No") . "<br>";

// Test 4: Test activity detection for customer
echo "<h3>Test 4: Customer Activity Detection</h3>";
$customer_id = 9; // Assuming customer has user_id = 9
$has_activities = hasNewActivities($customer_id, 'customer');
echo "Customer has new activities: " . ($has_activities ? "Yes" : "No") . "<br>";

// Test 5: Test dashboard visit update
echo "<h3>Test 5: Dashboard Visit Update</h3>";
$old_visit = getUserLastDashboardVisit($admin_id, 'admin');
echo "Admin last visit before update: " . $old_visit . "<br>";
