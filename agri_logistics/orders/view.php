<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Access control: All customer-facing roles, plus admin/logistics can view this page.
if (!in_array($_SESSION["role"], ['admin', 'logistics_manager', 'customer'])) {
    $_SESSION['error_message'] = "You do not have permission to view order details.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Order Details";
$current_page = "orders";

$order = null;
$order_products = [];
$logged_in_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$error_message_local = '';

if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $order_id = trim($_GET['id']);
    
    // Check if the order belongs to the customer if the user role is 'customer'
    if ($user_role == 'customer') {
        $sql_check_ownership = "SELECT customer_id FROM orders WHERE order_id = ?";
        if ($stmt_check = mysqli_prepare($conn, $sql_check_ownership)) {
            mysqli_stmt_bind_param($stmt_check, "i", $order_id);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);
            $row_check = mysqli_fetch_assoc($result_check);
            mysqli_stmt_close($stmt_check);
            if ($row_check['customer_id'] != $logged_in_user_id) {
                $_SESSION['error_message'] = "You do not have permission to view this order.";
                header("location: " . BASE_URL . "orders/index.php");
                exit;
            }
        }
    }

    // Fetch order details including audit trail
    $sql_order = "SELECT o.order_id, o.shipping_address, o.total_amount, o.status, o.order_date,
                         u.username AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
                         o.created_at, o.updated_at,
                         uc.username AS created_by_username, uu.username AS updated_by_username
                  FROM orders o
                  JOIN users u ON o.customer_id = u.user_id
                  LEFT JOIN users uc ON o.created_by = uc.user_id
                  LEFT JOIN users uu ON o.updated_by = uu.user_id
                  WHERE o.order_id = ?";

    if ($stmt_order = mysqli_prepare($conn, $sql_order)) {
        mysqli_stmt_bind_param($stmt_order, "i", $order_id);
        if (mysqli_stmt_execute($stmt_order)) {
            $result_order = mysqli_stmt_get_result($stmt_order);
            if (mysqli_num_rows($result_order) == 1) {
                $order = mysqli_fetch_assoc($result_order);
            } else {
                $error_message_local = "Order not found.";
            }
        }
        mysqli_stmt_close($stmt_order);
    } else {
        $error_message_local = "Error preparing order query.";
        error_log("Error preparing order fetch statement: " . mysqli_error($conn));
    }

    if ($order) {
        // FIX: Fetch products with the batch ID
        $sql_products = "SELECT op.quantity, op.unit, p.product_name, p.packaging_details, p.batch_id, op.price_at_order
                         FROM order_products op
                         JOIN products p ON op.product_id = p.product_id
                         WHERE op.order_id = ?";
        if ($stmt_products = mysqli_prepare($conn, $sql_products)) {
            mysqli_stmt_bind_param($stmt_products, "i", $order_id);
            if (mysqli_stmt_execute($stmt_products)) {
                $result_products = mysqli_stmt_get_result($stmt_products);
                while ($row = mysqli_fetch_assoc($result_products)) {
                    $order_products[] = $row;
                }
            } else {
                $error_message_local .= " Error fetching products for this order.";
                error_log("Error fetching order products: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_products);
        } else {
            $error_message_local .= " Error preparing products query.";
            error_log("Error preparing order products query: " . mysqli_error($conn));
        }
    }


} else {
    $error_message_local = "No order ID provided.";
    header("location: " . BASE_URL . "orders/index.php");
    exit;
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Order Details (ID: <?php echo htmlspecialchars($order['order_id'] ?? 'N/A'); ?>)</h2>
        <a href="<?php echo BASE_URL; ?>orders/index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Order List</a>

        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
            unset($_SESSION['success_message']);
        }
        if (!empty($error_message_local)) {
            echo '<div class="alert alert-danger">' . $error_message_local . '</div>';
        }
        ?>

        <?php if ($order): ?>
        <div class="card p-4 shadow-sm mb-4">
            <h4 class="card-title mb-3">Order Information</h4>
            <div class="row">
                <div class="col-md-6 mb-2"><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></div>
                <div class="col-md-6 mb-2"><strong>Order Date:</strong> <?php echo htmlspecialchars($order['order_date']); ?></div>
                <div class="col-md-6 mb-2"><strong>Total Amount:</strong> ৳<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></div>
                <div class="col-md-6 mb-2"><strong>Status:</strong> 
                    <span class="badge bg-<?php
                        switch ($order['status']) {
                            case 'pending': echo 'secondary'; break;
                            case 'processing': echo 'primary'; break;
                            case 'shipped': echo 'info'; break;
                            case 'delivered': echo 'success'; break;
                            case 'cancelled': echo 'dark'; break;
                            default: echo 'light text-dark';
                        }
                    ?>"><?php echo htmlspecialchars(ucwords($order['status'])); ?></span>
                </div>
                <div class="col-12 mb-2"><strong>Shipping Address:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></div>
            </div>
            
            <?php if (isset($order['created_at']) || isset($order['updated_at'])): ?>
            <div class="mt-3 border-top pt-3 text-muted small">
                Created: <?php echo htmlspecialchars($order['created_at']); ?> by <?php echo htmlspecialchars($order['created_by_username'] ?: 'N/A'); ?><br>
                Last Updated: <?php echo htmlspecialchars($order['updated_at']); ?> by <?php echo htmlspecialchars($order['updated_by_username'] ?: 'N/A'); ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card p-4 shadow-sm mb-4">
            <h4 class="card-title mb-3">Products in Order</h4>
            <?php if (!empty($order_products)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Product Name</th>
                                <th>Batch ID</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Packaging</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['batch_id'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($product['quantity'] . ' ' . $product['unit']); ?></td>
                                    <td>৳ <?php echo htmlspecialchars(number_format($product['price_at_order'] ?? 0, 2)); ?></td>
                                    <td><?php echo htmlspecialchars($product['packaging_details'] ?: 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No products associated with this order.</p>
            <?php endif; ?>
        </div>
        
        <div class="mt-4 text-center">
             <?php if ($user_role == 'admin' || $user_role == 'logistics_manager'): ?>
                <a href="<?php echo BASE_URL; ?>orders/update.php?id=<?php echo htmlspecialchars($order['order_id']); ?>" class="btn btn-warning me-2"><i class="fas fa-clipboard-check"></i> Update Order Status</a>
                <a href="<?php echo BASE_URL; ?>orders/delete.php?id=<?php echo htmlspecialchars($order['order_id']); ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this order?');"><i class="fas fa-trash-alt"></i> Delete Order</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>