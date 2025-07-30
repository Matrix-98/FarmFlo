<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Access control: Only Admin and Logistics Manager can update order status
if (!in_array($_SESSION["role"], ['admin', 'logistics_manager'])) {
    $_SESSION['error_message'] = "You do not have permission to update order status.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Update Order Status";
$current_page = "orders";

$order_id = null;
$status = '';
$status_err = '';

// Fetch current order status
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $order_id = trim($_GET["id"]);
    $sql = "SELECT status FROM orders WHERE order_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if (mysqli_num_rows($result) == 1) {
                $row = mysqli_fetch_assoc($result);
                $status = $row["status"];
            } else {
                $_SESSION['error_message'] = "Order not found.";
                header("location: " . BASE_URL . "orders/index.php");
                exit;
            }
        }
        mysqli_stmt_close($stmt);
    }
} else if ($_SERVER["REQUEST_METHOD"] != "POST") {
    $_SESSION['error_message'] = "Invalid request. No order ID provided.";
    header("location: " . BASE_URL . "orders/index.php");
    exit;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $order_id = $_POST["order_id"];
    if (empty(trim($_POST["status"]))) {
        $status_err = "Please select a status.";
    } else {
        $status = trim($_POST["status"]);
    }
    
    if (empty($status_err)) {
        $logged_in_user_id = $_SESSION['user_id'];
        $sql = "UPDATE orders SET status = ?, updated_by = ? WHERE order_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sii", $status, $logged_in_user_id, $order_id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Order #" . $order_id . " status updated to " . ucwords($status) . ".";
                header("location: " . BASE_URL . "orders/index.php");
                exit;
            } else {
                $_SESSION['error_message'] = "Error updating order status: " . mysqli_error($conn);
                error_log("Error updating order status: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <h2 class="mb-4">Update Order Status</h2>
        <a href="<?php echo BASE_URL; ?>orders/view.php?id=<?php echo htmlspecialchars($order_id); ?>" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Order Details</a>

        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
            unset($_SESSION['success_message']);
        }
        ?>

        <div class="card p-4 shadow-sm">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">

                <div class="mb-3">
                    <label for="status" class="form-label">Order Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select <?php echo (!empty($status_err)) ? 'is-invalid' : ''; ?>">
                        <option value="pending" <?php echo ($status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?php echo ($status == 'processing') ? 'selected' : ''; ?>>Processing</option>
                        <option value="shipped" <?php echo ($status == 'shipped') ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo ($status == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo ($status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <div class="invalid-feedback"><?php echo $status_err; ?></div>
                </div>

                <button type="submit" class="btn btn-warning"><i class="fas fa-sync-alt"></i> Update Status</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>