<?php
require_once '../config/db.php';
require_once '../utils/code_helpers.php';

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
        // Fetch products for this order
        $sql_products = "SELECT op.quantity_kg, op.product_id, p.name as product_name, op.price_at_order
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
        <h2 class="mb-4">Order Details (<?php echo htmlspecialchars(getOrderCode($order['order_id'] ?? null)); ?>)</h2>
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
                <div class="col-md-6 mb-2"><strong>Order Code:</strong> <?php echo htmlspecialchars(getOrderCode($order['order_id'])); ?></div>
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
                                <th>Product Code</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars(getProductCode($product['product_id'])); ?></td>
                                    <td><?php echo htmlspecialchars($product['quantity_kg'] . ' kg'); ?></td>
                                    <td>৳ <?php echo htmlspecialchars(number_format($product['price_at_order'] ?? 0, 2)); ?></td>
                                    </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No products associated with this order.</p>
            <?php endif; ?>
        </div>
        
        <div class="mt-4 text-center d-flex flex-wrap gap-2 justify-content-center">
            <button type="button" class="btn btn-success" onclick="printPODInline()"><i class="fas fa-print"></i> Print POD</button>
            <button type="button" class="btn btn-secondary" onclick="downloadPODPdfInline()"><i class="fas fa-file-pdf"></i> Download PDF</button>
            <?php if ($user_role == 'admin' || $user_role == 'logistics_manager'): ?>
                <a href="<?php echo BASE_URL; ?>orders/update.php?id=<?php echo htmlspecialchars($order['order_id']); ?>" class="btn btn-warning"><i class="fas fa-clipboard-check"></i> Update Order Status</a>
                <a href="<?php echo BASE_URL; ?>orders/delete.php?id=<?php echo htmlspecialchars($order['order_id']); ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this order?');"><i class="fas fa-trash-alt"></i> Delete Order</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
async function buildPODNode(){
    const container = document.createElement('div');
    container.className = 'invoice p-4';
    container.style.background = '#fff';
    container.style.color = '#000';
    container.style.maxWidth = '900px';
    container.style.margin = '0 auto';
    container.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div style="font-size:24px;font-weight:700;color:#2d6a4f">Farm Flo</div>
                <div style="font-size:18px;font-weight:600;">INVOICE</div>
            </div>
            <div class="text-end">
                <small>Invoice No:</small>
                <div class="fw-bold"><?php echo htmlspecialchars(getOrderCode($order['order_id'] ?? null)); ?></div>
                <small>Date:</small>
                <div class="fw-bold"><?php echo htmlspecialchars(date('d/m/y', strtotime($order['order_date'] ?? date('Y-m-d')))); ?></div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="text-muted">Billing To</h6>
                <div class="fw-bold"><?php echo htmlspecialchars($order['customer_name'] ?? ''); ?></div>
                <div>Phone: <?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?></div>
                <div>Address: <?php echo htmlspecialchars($order['shipping_address'] ?? ''); ?></div>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Sent From</h6>
                <div class="fw-bold">FarmFlow</div>
                <div>Phone: 01676225090</div>
                <div>Address: Uttara, Dhaka</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th style="width:10%">ID</th>
                        <th>Product</th>
                        <th style="width:15%" class="text-end">Price</th>
                        <th style="width:15%" class="text-end">Quantity</th>
                        <th style="width:15%" class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($order_products as $product):
                    $qty=(float)$product['quantity_kg'];
                    $price=(float)($product['price_at_order'] ?? 0);
                    $tot=$qty*$price; ?>
                    <tr>
                        <td><?php echo htmlspecialchars(getProductCode($product['product_id'])); ?></td>
                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                        <td class="text-end">৳<?php echo number_format($price,2); ?></td>
                        <td class="text-end"><?php echo number_format($qty,2); ?></td>
                        <td class="text-end">৳<?php echo number_format($tot,2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end">
            <table class="table w-auto">
                <tr>
                    <th class="text-end">Total:</th>
                    <td class="text-end">৳<?php echo number_format($order['total_amount'] ?? 0, 2); ?></td>
                </tr>
            </table>
        </div>
    `;
    return container;
}

function openPrintWindow(content){
    const w = window.open('', '_blank');
    if (!w) return alert('Popup blocked');
    w.document.write('<html><head><title>Print POD</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>'+content.outerHTML+'<script>window.onload=function(){setTimeout(function(){window.print();window.close();},300)}<\\/script></body></html>');
    w.document.close();
}

async function printPODInline(){
    const node = await buildPODNode();
    // Attach to DOM, set hidden off-screen for screen, visible for print
    node.style.position = 'fixed';
    node.style.left = '-10000px';
    node.style.top = '0';
    node.style.zIndex = '99999';
    document.body.appendChild(node);

    const css = document.createElement('style');
    css.setAttribute('data-print-style', '1');
    css.textContent = `@media print {
        body * { visibility: hidden !important; }
        .invoice, .invoice * { visibility: visible !important; }
        .invoice { position: fixed !important; left: 0 !important; top: 0 !important; width: 100% !important; margin: 0 !important; z-index: 99999 !important; }
    }`;
    document.head.appendChild(css);

    const cleanup = () => {
        try { document.head.removeChild(css); } catch (e) {}
        try { document.body.removeChild(node); } catch (e) {}
        window.removeEventListener('afterprint', cleanup);
    };
    window.addEventListener('afterprint', cleanup, { once: true });

    // Wait for layout/paint before printing
    await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
    setTimeout(() => {
        try { window.print(); } catch (e) { cleanup(); }
        // Fallback cleanup in case afterprint doesn't fire
        setTimeout(cleanup, 2000);
    }, 100);
}

async function downloadPODPdfInline(){
    try {
        const node = await buildPODNode();
        // Attach to DOM to ensure correct layout
        node.style.position = 'fixed';
        node.style.left = '-10000px';
        node.style.top = '0';
        document.body.appendChild(node);

        const jsPdfNS = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf : (window.jsPDF ? { jsPDF: window.jsPDF } : null);
        if (!jsPdfNS) { alert('PDF library failed to load. Please check your internet connection.'); document.body.removeChild(node); return; }
        const { jsPDF } = jsPdfNS;

        const canvas = await html2canvas(node, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
        document.body.removeChild(node);

        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF('p', 'pt', 'a4');
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const maxWidth = pageWidth - 40; // margins
        const maxHeight = pageHeight - 40;
        let imgWidth = maxWidth;
        let imgHeight = canvas.height * imgWidth / canvas.width;
        if (imgHeight > maxHeight) {
            imgHeight = maxHeight;
            imgWidth = canvas.width * imgHeight / canvas.height;
        }
        pdf.addImage(imgData, 'PNG', (pageWidth - imgWidth)/2, 20, imgWidth, imgHeight);
        pdf.save('POD-<?php echo htmlspecialchars(getOrderCode($order['order_id'] ?? null)); ?>.pdf');
    } catch (e) {
        console.error(e);
        alert('Failed to generate PDF.');
    }
}
</script>