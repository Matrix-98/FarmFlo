<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Access control: All customer-facing roles, plus admin/logistics can view this list.
if (!in_array($_SESSION["role"], ['admin', 'logistics_manager', 'customer'])) {
    $_SESSION['error_message'] = "You do not have permission to view orders.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Order List";
$current_page = "orders";

$orders_list = [];
$logged_in_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Base query to fetch order list
$sql = "SELECT o.order_id, o.order_date, o.total_amount, o.status, 
               u.username AS customer_name, u.customer_type
        FROM orders o
        JOIN users u ON o.customer_id = u.user_id";

// Restrict the query for 'customer' role
if ($user_role == 'customer') {
    $sql .= " WHERE o.customer_id = ?";
}

$sql .= " ORDER BY o.order_date DESC";

// Prepare and execute the query
if ($stmt = mysqli_prepare($conn, $sql)) {
    if ($user_role == 'customer') {
        mysqli_stmt_bind_param($stmt, "i", $logged_in_user_id);
    }

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $orders_list[] = $row;
        }
        mysqli_free_result($result);
    } else {
        error_log("Order list query failed: " . mysqli_error($conn));
        echo '<div class="alert alert-danger">ERROR: Could not retrieve order list. Please try again later.</div>';
    }
    mysqli_stmt_close($stmt);
} else {
    error_log("Order list query prepare failed: " . mysqli_error($conn));
    echo '<div class="alert alert-danger">ERROR: Could not prepare order list query. Please try again later.</div>';
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><?php echo ($user_role == 'customer') ? 'My Orders' : 'All Orders'; ?></h2>
            <?php if (in_array($user_role, ['customer', 'admin'])): ?>
                <a href="<?php echo BASE_URL; ?>orders/create.php" class="btn btn-success"><i class="fas fa-plus"></i> Place New Order</a>
            <?php endif; ?>
        </div>

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

        <?php if (!empty($orders_list)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Customer Type</th>
                            <th>Total Amount</th>
                            <th>Order Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders_list as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><span class="badge bg-<?php echo ($order['customer_type'] == 'retailer') ? 'primary' : 'secondary'; ?>"><?php echo htmlspecialchars(ucwords($order['customer_type'])); ?></span></td>
                                <td>৳<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                                <td><span class="badge bg-<?php
                                    switch ($order['status']) {
                                        case 'pending': echo 'secondary'; break;
                                        case 'processing': echo 'primary'; break;
                                        case 'shipped': echo 'info'; break;
                                        case 'delivered': echo 'success'; break;
                                        case 'cancelled': echo 'dark'; break;
                                        default: echo 'light text-dark';
                                    }
                                ?>"><?php echo htmlspecialchars(ucwords($order['status'])); ?></span></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>orders/view.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-info me-2" title="View Details"><i class="fas fa-eye"></i></a>
                                    <?php if ($user_role == 'admin' || $user_role == 'logistics_manager'): ?>
                                        <a href="<?php echo BASE_URL; ?>orders/update.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-warning mb-1" title="Update Status"><i class="fas fa-clipboard-check"></i> Status</a>
                                        <a href="<?php echo BASE_URL; ?>orders/delete.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-danger mb-1" title="Delete" onclick="return confirm('Are you sure you want to delete this order?');"><i class="fas fa-trash-alt"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No orders found.</div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>