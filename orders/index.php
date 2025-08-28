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
$sql = "SELECT o.order_id, o.order_code, o.order_date, o.total_amount, o.status, 
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
    }
    mysqli_stmt_close($stmt);
} else {
    error_log("Order list query prepare failed: " . mysqli_error($conn));
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
                <h2><?php echo ($user_role == 'customer') ? 'My Orders' : 'All Orders'; ?></h2>
                <p class="text-muted mb-0">Manage and track order information.</p>
            </div>
            <?php if (in_array($user_role, ['customer', 'admin', 'logistics_manager'])): ?>
                <a href="<?php echo BASE_URL; ?>orders/create.php" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i>Place New Order
                </a>
            <?php endif; ?>
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

        <!-- Orders List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Orders List</h5>
                <div class="d-flex gap-2">
                    <button onclick="exportToCSV()" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download me-1"></i>Export CSV
                    </button>
                    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($orders_list)): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="ordersTable">
                        <thead>
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
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                            <i class="fas fa-shopping-cart text-primary" style="font-size: 0.8rem;"></i>
                                        </div>
                                        <span class="fw-semibold">#<?php echo htmlspecialchars($order['order_code']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                            <i class="fas fa-user text-success" style="font-size: 0.8rem;"></i>
                                        </div>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo ($order['customer_type'] == 'retailer') ? 'primary' : 'secondary'; ?>">
                                        <?php echo htmlspecialchars(ucwords($order['customer_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-success">
                                        ৳<?php echo number_format($order['total_amount'], 2); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php
                                        switch ($order['status']) {
                                            case 'pending': echo 'secondary'; break;
                                            case 'confirmed': echo 'info'; break;
                                            case 'completed': echo 'success'; break;
                                            case 'cancelled': echo 'danger'; break;
                                            default: echo 'light text-dark';
                                        }
                                    ?>"><?php echo ucwords($order['status']); ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="<?php echo BASE_URL; ?>orders/view.php?id=<?php echo $order['order_id']; ?>" class="btn btn-outline-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($user_role == 'admin' || $user_role == 'logistics_manager'): ?>
                                            <a href="<?php echo BASE_URL; ?>orders/update.php?id=<?php echo $order['order_id']; ?>" class="btn btn-outline-warning" title="Update Status">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" onclick="deleteOrder(<?php echo $order['order_id']; ?>)" title="Delete Order">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No Orders Found</h5>
                    <p class="text-muted">Start by placing your first order.</p>
                    <?php if (in_array($user_role, ['customer', 'admin', 'logistics_manager'])): ?>
                        <a href="<?php echo BASE_URL; ?>orders/create.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Place First Order
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function deleteOrder(orderId) {
    if (confirm('Are you sure you want to delete order #' + orderId + '? This action cannot be undone.')) {
        window.location.href = '<?php echo BASE_URL; ?>orders/delete.php?id=' + orderId;
    }
}

function exportToCSV() {
    const table = document.getElementById('ordersTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const cols = row.querySelectorAll('td, th');
        let csvRow = [];
        
        for (let j = 0; j < cols.length - 1; j++) { // Exclude the Actions column
            let text = cols[j].innerText.replace(/"/g, '""');
            csvRow.push('"' + text + '"');
        }
        
        csv.push(csvRow.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'orders_export.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../includes/footer.php'; ?>