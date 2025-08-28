<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to edit orders.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Edit Order";
$current_page = "orders";

$order_id_param = null;
$shipping_address = "";
$products_data = []; // Holds current products in this order
$shipping_address_err = "";
$products_err = "";

// Initialize audit trail variables for display
$created_at = $updated_at = $created_by_username = $updated_by_username = '';

// Fetch all products for the dropdown AND their prices (matching orders/create.php)
$products_options = [];
$product_prices = [];
$sql_products_for_display = "SELECT product_id, name as product_name, price_per_unit, packaging_details FROM products ORDER BY name ASC";
if ($result_products = mysqli_query($conn, $sql_products_for_display)) {
    while ($row = mysqli_fetch_assoc($result_products)) {
        $products_options[] = $row;
        $product_prices[$row['product_id']] = $row['price_per_unit'];
    }
    mysqli_free_result($result_products);
}


// Fetch existing order data if ID is provided in GET request
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $order_id_param = trim($_GET["id"]);

    // Fetch order details including audit trail
    $sql_fetch_order = "SELECT o.order_id, o.shipping_address, o.total_amount, o.status, o.order_date,
                         u.username AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
                         o.created_at, o.updated_at, o.created_by, o.updated_by
                  FROM orders o
                  JOIN users u ON o.customer_id = u.user_id
                  LEFT JOIN users uc ON o.created_by = uc.user_id
                  LEFT JOIN users uu ON o.updated_by = uu.user_id
                  WHERE o.order_id = ?";

    if ($stmt_fetch = mysqli_prepare($conn, $sql_fetch_order)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $order_id_param);
        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);
            if (mysqli_num_rows($result_fetch) == 1) {
                $order = mysqli_fetch_assoc($result_fetch);
                $shipping_address = $order['shipping_address']; // Pre-fill address
                // Capture audit data for display
                $created_at = $order["created_at"];
                $updated_at = $order["updated_at"];
                $created_by_id = $order["created_by"];
                $updated_by_id = $order["updated_by"];

                // Fetch usernames for display
                if ($created_by_id) {
                    $user_sql = "SELECT username FROM users WHERE user_id = ?";
                    if($user_stmt = mysqli_prepare($conn, $user_sql)) {
                        mysqli_stmt_bind_param($user_stmt, "i", $created_by_id);
                        mysqli_stmt_execute($user_stmt);
                        $user_result = mysqli_stmt_get_result($user_stmt);
                        if($user_row = mysqli_fetch_assoc($user_result)) $created_by_username = $user_row['username'];
                        mysqli_stmt_close($user_stmt);
                    }
                }
                if ($updated_by_id) {
                    $user_sql = "SELECT username FROM users WHERE user_id = ?";
                    if($user_stmt = mysqli_prepare($conn, $user_sql)) {
                        mysqli_stmt_bind_param($user_stmt, "i", $updated_by_id);
                        mysqli_stmt_execute($user_stmt);
                        $user_result = mysqli_stmt_get_result($user_stmt);
                        if($user_row = mysqli_fetch_assoc($user_result)) $updated_by_username = $user_row['username'];
                        mysqli_stmt_close($user_stmt);
                    }
                }

            } else {
                $_SESSION['error_message'] = "Order not found.";
                header("location: " . BASE_URL . "orders/index.php");
                exit();
            }
        }
        mysqli_stmt_close($stmt_fetch);
    } else {
        $_SESSION['error_message'] = "Error preparing order fetch statement.";
        error_log("Error preparing order fetch statement: " . mysqli_error($conn));
        header("location: " . BASE_URL . "orders/index.php");
        exit();
    }

    // Fetch products currently in this order
    $sql_products_in_order = "SELECT op.product_id, op.quantity_kg FROM order_products op WHERE op.order_id = ?";
    if ($stmt_products_in_order = mysqli_prepare($conn, $sql_products_in_order)) {
        mysqli_stmt_bind_param($stmt_products_in_order, "i", $order_id_param);
        if (mysqli_stmt_execute($stmt_products_in_order)) {
            $result_products_in_order = mysqli_stmt_get_result($stmt_products_in_order);
            while ($row = mysqli_fetch_assoc($result_products_in_order)) {
                $products_data[] = $row;
            }
        } else {
            $_SESSION['error_message'] = "Error fetching products for this order.";
            error_log("Error fetching products in order: " . mysqli_error($conn));
            header("location: " . BASE_URL . "orders/index.php");
            exit();
        }
        mysqli_stmt_close($stmt_products_in_order);
    } else {
        $_SESSION['error_message'] = "Error preparing products in order query.";
        error_log("Error preparing products in order query: " . mysqli_error($conn));
        header("location: " . BASE_URL . "orders/index.php");
        exit();
    }

} else if ($_SERVER["REQUEST_METHOD"] != "POST") { // Redirect if no ID provided in GET, and not a POST request
    $_SESSION['error_message'] = "Invalid request. No order ID provided.";
    header("location: " . BASE_URL . "orders/index.php");
    exit();
}


// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $order_id = $_POST["order_id"]; // Get order_id from hidden input

    $shipping_address = trim($_POST['shipping_address']);
    if (empty($shipping_address)) {
        $shipping_address_err = "Please enter a shipping address.";
    }

    $new_products_data = [];
    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        $products_err = "Please add at least one product to the order.";
    } else {
        foreach ($_POST['products'] as $product_entry) {
            $prod_id = trim($product_entry['product_id']);
            $qty = trim($product_entry['quantity']);
            $unit = trim($product_entry['unit']);
            if (empty($prod_id) || empty($qty) || !is_numeric($qty) || $qty <= 0 || empty($unit)) {
                $products_err = "All product entries must have a selected product and valid positive quantity in kg.";
                break;
            }
                            $new_products_data[] = ['product_id' => $prod_id, 'quantity_kg' => $qty];
        }
        $products_data = $new_products_data; // Update for display if there's an error
    }

    if (empty($shipping_address_err) && empty($products_err)) {
        mysqli_begin_transaction($conn);
        $logged_in_user_id = $_SESSION['user_id'];

        try {
            // Re-fetch product prices securely for calculations
            $secure_product_prices = [];
            $product_ids_in_order = array_column($new_products_data, 'product_id');
            if (!empty($product_ids_in_order)) {
                $sql_secure_prices = "SELECT product_id, price_per_unit FROM products WHERE product_id IN (" . implode(',', array_map('intval', $product_ids_in_order)) . ")";
                if ($result_secure_prices = mysqli_query($conn, $sql_secure_prices)) {
                    while($row = mysqli_fetch_assoc($result_secure_prices)) {
                        $secure_product_prices[$row['product_id']] = $row['price_per_unit'];
                    }
                } else {
                    throw new Exception("Error fetching secure product prices for update: " . mysqli_error($conn));
                }
            }
            
            // Fetch customer type for discount
            $customer_type = 'direct'; // Default if not found
            $discount_rate = 0.0;
            $sql_get_customer_type = "SELECT u.customer_type FROM users u JOIN orders o ON u.user_id = o.customer_id WHERE o.order_id = ?";
            if($stmt_get_type = mysqli_prepare($conn, $sql_get_customer_type)) {
                mysqli_stmt_bind_param($stmt_get_type, "i", $order_id);
                mysqli_stmt_execute($stmt_get_type);
                $result_get_type = mysqli_stmt_get_result($stmt_get_type);
                if($row_get_type = mysqli_fetch_assoc($result_get_type)) {
                    $customer_type = $row_get_type['customer_type'];
                    if ($customer_type == 'retailer') {
                        $discount_rate = 0.30;
                    }
                }
                mysqli_stmt_close($stmt_get_type);
            } else {
                error_log("Error preparing customer type fetch for order update: " . mysqli_error($conn));
            }


            $total_amount = 0;
            $order_products_to_save = [];
            foreach ($new_products_data as $product_entry) {
                $product_id = $product_entry['product_id'];
                $quantity = $product_entry['quantity'];
                $unit_price = $secure_product_prices[$product_id] ?? 0;
                $final_price_per_unit_after_discount = $unit_price * (1 - $discount_rate);
                $total_amount += $final_price_per_unit_after_discount * $quantity;
                $order_products_to_save[] = [
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'unit' => $product_entry['unit'],
                    'price_at_order' => $unit_price // Store the original base price per unit
                ];
            }

            // Update orders table
            $sql_update_order = "UPDATE orders SET shipping_address = ?, total_amount = ?, updated_by = ? WHERE order_id = ?";
            if ($stmt_update = mysqli_prepare($conn, $sql_update_order)) {
                mysqli_stmt_bind_param($stmt_update, "dsii", $shipping_address, $total_amount, $logged_in_user_id, $order_id);
                if (!mysqli_stmt_execute($stmt_update)) {
                    throw new Exception("Error updating order: " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt_update);
            } else {
                throw new Exception("Error preparing order update statement: " . mysqli_error($conn));
            }

            // Update order_products: Delete existing and re-insert new ones
            $sql_delete_products = "DELETE FROM order_products WHERE order_id = ?";
            if ($stmt_delete = mysqli_prepare($conn, $sql_delete_products)) {
                mysqli_stmt_bind_param($stmt_delete, "i", $order_id);
                if (!mysqli_stmt_execute($stmt_delete)) {
                    throw new Exception("Error deleting old order products: " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt_delete);
            } else {
                throw new Exception("Error preparing delete old order products statement: " . mysqli_error($conn));
            }

            $sql_insert_products = "INSERT INTO order_products (order_id, product_id, quantity_kg, price_at_order) VALUES (?, ?, ?, ?)";
            if ($stmt_insert_products = mysqli_prepare($conn, $sql_insert_products)) {
                foreach ($order_products_to_save as $product_entry) {
                    mysqli_stmt_bind_param($stmt_insert_products, "iids", $order_id, $product_entry['product_id'], $product_entry['quantity_kg'], $product_entry['price_at_order']);
                    if (!mysqli_stmt_execute($stmt_insert_products)) {
                        throw new Exception("Error re-inserting product into order: " . mysqli_error($conn));
                    }
                }
                mysqli_stmt_close($stmt_insert_products);
            } else {
                throw new Exception("Error preparing re-insert order products statement: " . mysqli_error($conn));
            }

            mysqli_commit($conn);
            $_SESSION['success_message'] = "Order #" . $order_id . " updated successfully! New total: ৳" . number_format($total_amount, 2);
            header("location: " . BASE_URL . "orders/index.php");
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error_message'] = "Order update failed: " . $e->getMessage();
            error_log("Order update failed: " . $e->getMessage());
        }
    }
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Edit Order (ID: <?php echo htmlspecialchars($order_id_param); ?>)</h2>
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
        ?>

        <div class="card p-4 shadow-sm">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id_param); ?>">

                <div class="mb-3">
                    <label for="shipping_address" class="form-label">Shipping Address <span class="text-danger">*</span></label>
                    <textarea name="shipping_address" id="shipping_address" class="form-control <?php echo (!empty($shipping_address_err)) ? 'is-invalid' : ''; ?>" rows="3"><?php echo htmlspecialchars($shipping_address); ?></textarea>
                    <div class="invalid-feedback"><?php echo $shipping_address_err; ?></div>
                </div>

                <hr class="my-4">
                <h4>Products in Order <span class="text-danger">*</span></h4>
                <?php if (!empty($products_err)): ?>
                    <div class="alert alert-danger"><?php echo $products_err; ?></div>
                <?php endif; ?>

                <div id="product-list" class="mb-3">
                    <?php
                    // Pre-populate existing product rows
                    foreach ($products_data as $index => $product_entry):
                    ?>
                    <div class="row product-row mb-2 align-items-end" data-index="<?php echo $index; ?>">
                        <div class="col-md-5">
                            <label for="product_id_<?php echo $index; ?>" class="form-label d-md-none">Product</label>
                            <select name="products[<?php echo $index; ?>][product_id]" id="product_id_<?php echo $index; ?>" class="form-select product-select">
                                <option value="">Select Product</option>
                                <?php foreach ($products_options as $product): ?>
                                    <option value="<?php echo htmlspecialchars($product['product_id']); ?>"
                                        <?php echo ($product_entry['product_id'] == $product['product_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['product_name']); ?> (৳<?php echo htmlspecialchars(number_format($product['price_per_unit'] ?? 0, 2)); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="quantity_<?php echo $index; ?>" class="form-label d-md-none">Quantity</label>
                            <input type="number" name="products[<?php echo $index; ?>][quantity]" id="quantity_<?php echo $index; ?>" class="form-control product-quantity" value="<?php echo htmlspecialchars($product_entry['quantity']); ?>" step="0.01" placeholder="Quantity">
                        </div>
                        <div class="col-md-3">
                            <label for="unit_<?php echo $index; ?>" class="form-label d-md-none">Unit</label>
                            <input type="text" name="products[<?php echo $index; ?>][unit]" id="unit_<?php echo $index; ?>" class="form-control product-unit" value="<?php echo htmlspecialchars($product_entry['unit']); ?>" placeholder="e.g., kg, units">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-danger btn-sm remove-product-btn"><i class="fas fa-minus-circle"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="add-product-btn" class="btn btn-info btn-sm mb-3"><i class="fas fa-plus-circle"></i> Add Another Product</button>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-sync-alt"></i> Update Order</button>
                </div>
            </form>
            <?php if (isset($created_at) || isset($updated_at)): ?>
            <div class="mt-3 border-top pt-3 text-muted small">
                Created: <?php echo htmlspecialchars($created_at); ?> by <?php echo htmlspecialchars($created_by_username ?: 'N/A'); ?><br>
                Last Updated: <?php echo htmlspecialchars($updated_at); ?> by <?php echo htmlspecialchars($updated_by_username ?: 'N/A'); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const productList = document.getElementById('product-list');
        const addProductBtn = document.getElementById('add-product-btn');
        let productIndex = productList.children.length; // Start index after pre-populated items

        // NEW: Get product prices from PHP into JS for dynamic display
        const jsProductPrices = {};
        <?php foreach ($products_options as $product): ?>
            jsProductPrices[<?php echo $product['product_id']; ?>] = {
                name: "<?php echo htmlspecialchars($product['product_name']); ?>",
                price_per_unit: <?php echo htmlspecialchars($product['price_per_unit'] ?? 0); ?>,
            };
        <?php endforeach; ?>

        function createProductRow(productId = '', quantity_kg = '') {
            const newRow = document.createElement('div');
            newRow.classList.add('row', 'product-row', 'mb-2', 'align-items-end');
            newRow.setAttribute('data-index', productIndex);
            
            let productsOptionsHtml = '<option value="">Select Product</option>';
            for (const prodId in jsProductPrices) {
                const product = jsProductPrices[prodId];
                const selected = (productId == prodId) ? 'selected' : '';
                productsOptionsHtml += `<option value="${prodId}" ${selected}>${product.name} (৳${product.price_per_unit.toFixed(2)})</option>`;
            }

            newRow.innerHTML = `
                <div class="col-md-5">
                    <label for="product_id_${productIndex}" class="form-label d-md-none">Product</label>
                    <select name="products[${productIndex}][product_id]" id="product_id_${productIndex}" class="form-select product-select">
                        ${productsOptionsHtml}
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="quantity_kg_${productIndex}" class="form-label d-md-none">Quantity (kg)</label>
                    <input type="number" name="products[${productIndex}][quantity_kg]" id="quantity_kg_${productIndex}" class="form-control product-quantity" value="${quantity_kg}" step="0.01" placeholder="Quantity in kg">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-product-btn"><i class="fas fa-minus-circle"></i></button>
                </div>
            `;

            productList.appendChild(newRow);
            if (productId) {
                newRow.querySelector('.product-select').value = productId;
            }
            productIndex++;
        }

        addProductBtn.addEventListener('click', function() {
            createProductRow();
        });

        productList.addEventListener('click', function(event) {
            if (event.target.classList.contains('remove-product-btn') || event.target.closest('.remove-product-btn')) {
                if (productList.children.length > 1) {
                    const rowToRemove = event.target.closest('.product-row');
                    if (rowToRemove) {
                        rowToRemove.remove();
                        reindexProductRows();
                    }
                } else {
                    alert("An order must contain at least one product.");
                }
            }
        });

        function reindexProductRows() {
            const rows = productList.querySelectorAll('.product-row');
            rows.forEach((row, index) => {
                row.setAttribute('data-index', index);
                row.querySelectorAll('[name^="products["]').forEach(input => {
                    input.name = input.name.replace(/products\[\d+\]/, `products[${index}]`);
                    input.id = input.id.replace(/_\d+/, `_${index}`);
                });
                row.querySelectorAll('label[for^="product_id_"], label[for^="quantity_kg_"]').forEach(label => {
                    label.htmlFor = label.htmlFor.replace(/_\d+/, `_${index}`);
                });
            });
            productIndex = rows.length;
        }

        if (productList.children.length === 0) {
            createProductRow();
        }
    });
</script>