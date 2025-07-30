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
// FIX: Include price_per_unit and batch_id in the SELECT query and the joined usernames
$sql = "SELECT p.product_id, p.crop_type, p.product_name, p.planting_date, p.harvest_date, p.shelf_life_days, p.packaging_details, p.price_per_unit, p.batch_id, p.created_at, p.updated_at,
               uc.username AS created_by_username, uu.username AS updated_by_username
        FROM products p
        LEFT JOIN users uc ON p.created_by = uc.user_id
        LEFT JOIN users uu ON p.updated_by = uu.user_id
        ORDER BY p.product_name ASC";
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
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Product List</h2>
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

        <?php if (!empty($products)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Batch ID</th>
                            <th>Price per Unit</th>
                            <th>Harvest Date</th>
                            <th>Shelf Life (Days)</th>
                            <th>Packaging</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Last Updated By</th>
                            <th>Last Updated At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($product['batch_id'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars('৳ ' . number_format($product['price_per_unit'] ?? 0, 2)); ?></td>
                                <td><?php echo htmlspecialchars($product['harvest_date']); ?></td>
                                <td><?php echo htmlspecialchars($product['shelf_life_days']); ?></td>
                                <td><?php echo htmlspecialchars($product['packaging_details']); ?></td>
                                <td><?php echo htmlspecialchars($product['created_by_username'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($product['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($product['updated_by_username'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($product['updated_at']); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>products/edit.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-primary me-2" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="<?php echo BASE_URL; ?>products/delete.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this product?');"><i class="fas fa-trash-alt"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No products found. Click "Add New Product" to get started.</div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>