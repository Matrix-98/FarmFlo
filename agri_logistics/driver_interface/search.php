<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if (!in_array($_SESSION["role"], ['admin', 'logistics_manager', 'driver'])) {
    $_SESSION['error_message'] = "You do not have permission to access the driver search page.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Search Shipment by Order ID";
$current_page = "driver_search";

$order_id = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
$shipment_details = null;
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "GET" && !empty($order_id)) {
    // Fetch shipment details linked to the order, along with customer and destination info
    $sql = "SELECT s.shipment_id, s.status, s.planned_arrival,
                   dl.address AS destination_address,
                   u.username AS customer_name, u.phone AS customer_phone
            FROM shipments s
            JOIN orders o ON s.order_id = o.order_id
            JOIN locations dl ON s.destination_location_id = dl.location_id
            JOIN users u ON o.customer_id = u.user_id
            WHERE o.order_id = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $shipment_details = mysqli_fetch_assoc($result);
        } else {
            $error_message = "No shipment found for Order ID #" . htmlspecialchars($order_id) . ".";
        }
        mysqli_stmt_close($stmt);
    } else {
        $error_message = "Database error: Could not prepare query.";
        error_log("Driver search query prepare failed: " . mysqli_error($conn));
    }
}
// mysqli_close($conn) is handled by includes/footer.php
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <h2 class="mb-4">Driver: Search Shipment Details</h2>
        <p>Use the form below to search for a shipment by its associated order ID.</p>
        
        <div class="card p-4 shadow-sm mb-4">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="row g-3 align-items-center">
                <div class="col-md-9">
                    <label for="order_id_input" class="visually-hidden">Order ID</label>
                    <input type="text" class="form-control" id="order_id_input" name="order_id" placeholder="Enter Order ID" value="<?php echo htmlspecialchars($order_id); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button>
                </div>
            </form>
        </div>

        <?php
        if (!empty($error_message)) {
            echo '<div class="alert alert-danger">' . $error_message . '</div>';
        } elseif ($shipment_details) {
            echo '<div class="alert alert-success">Shipment found for Order ID #' . htmlspecialchars($order_id) . '.</div>';
            echo '<div class="card p-4 shadow-sm">';
            echo '    <h4>Shipment Details</h4>';
            echo '    <div class="row">';
            echo '        <div class="col-md-6 mb-2"><strong>Shipment ID:</strong> ' . htmlspecialchars($shipment_details['shipment_id']) . '</div>';
            echo '        <div class="col-md-6 mb-2"><strong>Current Status:</strong> <span class="badge bg-info">' . htmlspecialchars(ucwords($shipment_details['status'])) . '</span></div>';
            echo '        <div class="col-md-6 mb-2"><strong>Customer Name:</strong> ' . htmlspecialchars($shipment_details['customer_name']) . '</div>';
            echo '        <div class="col-md-6 mb-2"><strong>Customer Phone:</strong> ' . htmlspecialchars($shipment_details['customer_phone']) . '</div>';
            echo '        <div class="col-12 mb-2"><strong>Delivery Address:</strong> ' . htmlspecialchars($shipment_details['destination_address']) . '</div>';
            echo '    </div>';
            echo '</div>';
        } elseif (!empty($order_id)) {
            echo '<div class="alert alert-warning">No results found for Order ID #' . htmlspecialchars($order_id) . '.</div>';
        }
        ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>