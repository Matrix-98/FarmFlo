<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'farm_manager' && $_SESSION["role"] != 'warehouse_manager') {
    $_SESSION['error_message'] = "You do not have permission to access Inventory Management.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Inventory Management";
$current_page = "inventory";

$inventory_records = [];
// FIX: Join with users table to get created_by and updated_by usernames
// FIX: Select i.created_at and i.updated_at (which was renamed from last_updated)
$sql = "SELECT i.inventory_id, p.product_name, l.name AS location_name, i.quantity, i.unit, i.stage, i.created_at, i.updated_at,
               uc.username AS created_by_username, uu.username AS updated_by_username
        FROM inventory i
        JOIN products p ON i.product_id = p.product_id
        JOIN locations l ON i.location_id = l.location_id
        LEFT JOIN users uc ON i.created_by = uc.user_id
        LEFT JOIN users uu ON i.updated_by = uu.user_id
        ORDER BY p.product_name, l.name ASC";

if ($result = mysqli_query($conn, $sql)) {
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $inventory_records[] = $row;
        }
        mysqli_free_result($result);
    }
} else {
    error_log("Inventory list query failed: " . mysqli_error($conn));
    echo '<div class="alert alert-danger">ERROR: Could not retrieve inventory list. Please try again later.</div>';
    echo '<div class="alert alert-warning">Database Error Details (for admin): ' . mysqli_error($conn) . '</div>'; // Display error for debugging
}
// mysqli_close($conn) is in footer.php
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Current Stock Levels</h2>
            <a href="<?php echo BASE_URL; ?>inventory/create.php" class="btn btn-success"><i class="fas fa-plus"></i> Add/Update Stock</a>
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

        <?php if (!empty($inventory_records)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Product Name</th>
                            <th>Location</th>
                            <th>Stage</th>
                            <th>Quantity</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Last Updated By</th>
                            <th>Last Updated At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory_records as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['location_name']); ?></td>
                                <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $record['stage']))); ?></td>
                                <td><?php echo htmlspecialchars($record['quantity'] . ' ' . $record['unit']); ?></td>
                                <td><?php echo htmlspecialchars($record['created_by_username'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($record['created_at'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($record['updated_by_username'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($record['updated_at'] ?: 'N/A'); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>inventory/edit.php?id=<?php echo $record['inventory_id']; ?>" class="btn btn-sm btn-primary me-2" title="Update Stock"><i class="fas fa-sync-alt"></i> Update</a>
                                    <a href="<?php echo BASE_URL; ?>inventory/delete.php?id=<?php echo $record['inventory_id']; ?>" class="btn btn-sm btn-danger" title="Remove Record" onclick="return confirm('Are you sure you want to remove this inventory record?');"><i class="fas fa-trash-alt"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No inventory records found. Add/Update stock to get started.</div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>