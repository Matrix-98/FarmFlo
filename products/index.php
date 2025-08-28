<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'farm_manager') {
    $_SESSION['error_message'] = "You do not have permission to access Product Management.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Product Management";
$current_page = "products";

$products = [];
// FIX: Removed is_active from the SELECT query
$sql = "SELECT p.product_id, p.product_code, p.name, p.item_type, p.batch_id, p.price_per_unit, p.packaging_details, p.created_at, p.updated_at,
               uc.username AS created_by_username, uu.username AS updated_by_username
        FROM products p
        LEFT JOIN users uc ON p.created_by = uc.user_id
        LEFT JOIN users uu ON p.updated_by = uu.user_id
        ORDER BY p.name ASC";
if ($result = mysqli_query($conn, $sql)) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
        mysqli_free_result($result);
    }
} else {
    error_log("Product list query failed: " . mysqli_error($conn));
    echo '<div class="alert alert-danger">ERROR: Could not retrieve product list. Please try again later.</div>';
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
            <h2>Product Management</h2>
            <a href="<?php echo BASE_URL; ?>products/create.php" class="btn btn-success"><i class="fas fa-plus"></i> Add New Product</a>
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

        <!-- Product List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-seedling me-2"></i>Product List</h5>
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
                <?php if (!empty($products)): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="productsTable">
                        <thead>
                            <tr>
                                <th>Product Code</th>
                                <th>Product</th>
                                <th>Item Type</th>
                                <th>Batch ID</th>
                                <th>Price</th>
                                <th>Packaging</th>
                                <th>Created By</th>
                                <th>Created At</th>
                                <th>Updated By</th>
                                <th>Updated At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($product['product_code']); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                            <i class="fas fa-seedling text-success" style="font-size: 0.8rem;"></i>
                                        </div>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($product['name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($product['item_type']); ?></span>
                                </td>
                                <td>
                                    <?php if ($product['batch_id']): ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($product['batch_id']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-semibold text-success">
                                        ৳<?php echo number_format($product['price_per_unit'], 2); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($product['packaging_details']); ?></td>
                                <td><?php echo htmlspecialchars($product['created_by_username']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($product['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($product['updated_by_username']); ?></td>
                                <td><?php echo $product['updated_at'] ? date('M d, Y', strtotime($product['updated_at'])) : 'N/A'; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="<?php echo BASE_URL; ?>products/edit.php?id=<?php echo $product['product_id']; ?>" class="btn btn-outline-primary" title="Edit Product">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" onclick="deleteProduct(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')" title="Delete Product">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-seedling text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No Products Found</h5>
                    <p class="text-muted">Start by adding your first product.</p>
                    <a href="<?php echo BASE_URL; ?>products/create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add First Product
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function deleteProduct(productId, productName) {
    if (confirm('Are you sure you want to delete product "' + productName + '"? This action cannot be undone.')) {
        window.location.href = '<?php echo BASE_URL; ?>products/delete.php?id=' + productId;
    }
}

function exportToCSV() {
    const table = document.getElementById('productsTable');
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
    link.setAttribute('download', 'products_export.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../includes/footer.php'; ?>