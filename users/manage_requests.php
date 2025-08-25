<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Access control: Only Admin can manage requests
if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to manage customer requests.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Manage Customer Requests";
$current_page = "users";

$requests = [];
$sql = "SELECT request_id, username, customer_type, email, phone, request_date FROM registration_requests ORDER BY request_date ASC";
if ($result = mysqli_query($conn, $sql)) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $requests[] = $row;
        }
        mysqli_free_result($result);
    }
} else {
    error_log("Registration requests query failed: " . mysqli_error($conn));
}

include '../includes/head.php';
?>

<!-- Sidebar -->
<?php include '../includes/sidebar.php'; ?>

<!-- Main Content -->
<div class="content">
    <!-- Navbar -->
    <?php include '../includes/navbar.php'; ?>

    <!-- Page Content -->
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Customer Request Management</h2>
                <p class="text-muted mb-0">Review and manage pending customer registration requests.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>users/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to User List
            </a>
        </div>

        <!-- Success/Error Messages -->
        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>
