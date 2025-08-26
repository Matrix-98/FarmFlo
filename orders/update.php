<?php
require_once '../config/db.php';
require_once '../utils/inventory_helpers.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Access control: Only Admin and Logistics Manager can update orders
if (!in_array($_SESSION["role"], ['admin', 'logistics_manager'])) {
    $_SESSION['error_message'] = "You do not have permission to update orders.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Update Order";
$current_page = "orders";

$order_id = null;
$order_data = [];
$order_products = [];
$products_options = [];
$product_prices = [];
$customer_type = 'direct';
$discount_rate = 0.0;

// Fetch order details
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $order_id = trim($_GET["id"]);
    
    // Fetch order information
    $sql_order = "SELECT o.*, u.username as customer_name, u.customer_type, u.phone as customer_phone 
                  FROM orders o 
                  JOIN users u ON o.customer_id = u.user_id 
                  WHERE o.order_id = ?";
    
    if ($stmt_order = mysqli_prepare($conn, $sql_order)) {
        mysqli_stmt_bind_param($stmt_order, "i", $order_id);
        if (mysqli_stmt_execute($stmt_order)) {
            $result_order = mysqli_stmt_get_result($stmt_order);
            if (mysqli_num_rows($result_order) == 1) {
                $order_data = mysqli_fetch_assoc($result_order);
                $customer_type = $order_data['customer_type'];
                if ($customer_type == 'retailer') {
                    $discount_rate = 0.30; // 30% discount
                }
            } else {
                $_SESSION['error_message'] = "Order not found.";
                header("location: " . BASE_URL . "orders/index.php");
                exit;
            }
        }
        mysqli_stmt_close($stmt_order);
    }
    
    // Fetch order products
    $sql_products = "SELECT op.*, p.name as product_name, p.price_per_unit 
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
        }
        mysqli_stmt_close($stmt_products);
    }
    
    // Store original order products for comparison
    $original_order_products = $order_products;
} else {
    $_SESSION['error_message'] = "Invalid request. No order ID provided.";
    header("location: " . BASE_URL . "orders/index.php");
    exit;
}

// Fetch all products for dropdown
$sql_all_products = "SELECT product_id, name as product_name, price_per_unit FROM products ORDER BY name ASC";
if ($result_all_products = mysqli_query($conn, $sql_all_products)) {
    while ($row = mysqli_fetch_assoc($result_all_products)) {
        $products_options[] = $row;
        $product_prices[$row['product_id']] = $row['price_per_unit'];
    }
    mysqli_free_result($result_all_products);
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $shipping_address = trim($_POST['shipping_address']);
    $status = trim($_POST['status']);
    $products_data = [];
    $errors = [];
    
    // Validate shipping address
    if (empty($shipping_address)) {
        $errors[] = "Please enter a shipping address.";
    } elseif (strlen($shipping_address) < 10) {
        $errors[] = "Shipping address must be at least 10 characters long.";
    }
    
    // Validate status
    if (empty($status)) {
        $errors[] = "Please select a status.";
    }
    
    // Validate products
    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        $errors[] = "Please add at least one product to the order.";
    } else {
        foreach ($_POST['products'] as $product_entry) {
            $prod_id = trim($product_entry['product_id']);
            $qty = trim($product_entry['quantity_kg']);
            if (empty($prod_id) || empty($qty) || !is_numeric($qty) || $qty <= 0) {
                $errors[] = "All product entries must have a selected product and valid positive quantity in kg.";
                break;
            }
            $products_data[] = ['product_id' => $prod_id, 'quantity_kg' => $qty];
        }
    }
    
    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $total_amount = 0;
            $order_products_with_price = [];
            
            // Re-fetch product prices securely
            $secure_product_prices = [];
            $product_ids_in_order = array_column($products_data, 'product_id');
            if (!empty($products_data) && $result_secure_prices = mysqli_query($conn, "SELECT product_id, price_per_unit FROM products WHERE product_id IN (" . implode(',', array_map('intval', $product_ids_in_order)) . ")")) {
                while($row = mysqli_fetch_assoc($result_secure_prices)) {
                    $secure_product_prices[$row['product_id']] = $row['price_per_unit'];
                }
            }
            
            // Calculate total amount
            foreach ($products_data as $product_entry) {
                $product_id = $product_entry['product_id'];
                $quantity_kg = $product_entry['quantity_kg'];
                $unit_price = $secure_product_prices[$product_id] ?? 0;
                $final_price_per_unit_after_discount = $unit_price * (1 - $discount_rate);
                $total_amount += $final_price_per_unit_after_discount * $quantity_kg;
                $order_products_with_price[] = [
                    'product_id' => $product_id,
                    'quantity_kg' => $quantity_kg,
                    'price_at_order' => $unit_price
                ];
            }
            
            // Update order
            $logged_in_user_id = $_SESSION['user_id'];
            $sql_update_order = "UPDATE orders SET total_amount = ?, shipping_address = ?, status = ?, updated_by = ? WHERE order_id = ?";
            if ($stmt_update_order = mysqli_prepare($conn, $sql_update_order)) {
                mysqli_stmt_bind_param($stmt_update_order, "dssii", $total_amount, $shipping_address, $status, $logged_in_user_id, $order_id);
                if (!mysqli_stmt_execute($stmt_update_order)) {
                    throw new Exception("Error updating order: " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt_update_order);
            }
            
            // Delete existing order products
            $sql_delete_products = "DELETE FROM order_products WHERE order_id = ?";
            if ($stmt_delete_products = mysqli_prepare($conn, $sql_delete_products)) {
                mysqli_stmt_bind_param($stmt_delete_products, "i", $order_id);
                if (!mysqli_stmt_execute($stmt_delete_products)) {
                    throw new Exception("Error deleting existing order products: " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt_delete_products);
            }
            
            // Insert new order products
            $sql_insert_products = "INSERT INTO order_products (order_id, product_id, quantity_kg, price_at_order) VALUES (?, ?, ?, ?)";
            if ($stmt_insert_products = mysqli_prepare($conn, $sql_insert_products)) {
                foreach ($order_products_with_price as $product_entry) {
                    mysqli_stmt_bind_param($stmt_insert_products, "iids", $order_id, $product_entry['product_id'], $product_entry['quantity_kg'], $product_entry['price_at_order']);
                    if (!mysqli_stmt_execute($stmt_insert_products)) {
                        throw new Exception("Error adding product to order: " . mysqli_error($conn));
                    }
                }
                mysqli_stmt_close($stmt_insert_products);
            }
            
            // Handle inventory changes only if products or quantities have changed
            $inventory_changed = false;
            
            // Check if products or quantities have changed
            if (count($order_products_with_price) != count($original_order_products)) {
                $inventory_changed = true;
            } else {
                foreach ($order_products_with_price as $new_product) {
                    $found = false;
                    foreach ($original_order_products as $original_product) {
                        if ($new_product['product_id'] == $original_product['product_id'] && 
                            $new_product['quantity_kg'] == $original_product['quantity_kg']) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $inventory_changed = true;
                        break;
                    }
                }
            }
            
            // Handle inventory changes only if products or quantities have changed
            if ($inventory_changed) {
                error_log("Order update: Inventory changed detected - performing inventory operations for order $order_id");
                
                // Check inventory availability before making changes
                foreach ($order_products_with_price as $product_entry) {
                    $product_id = $product_entry['product_id'];
                    $quantity_kg = $product_entry['quantity_kg'];
                    
                    // Check inventory availability
                    $sql_check_inventory = "SELECT SUM(quantity_kg) as available_quantity 
                                           FROM inventory 
                                           WHERE product_id = ? AND stage = 'available'";
                    $stmt_check = mysqli_prepare($conn, $sql_check_inventory);
                    mysqli_stmt_bind_param($stmt_check, "i", $product_id);
                    mysqli_stmt_execute($stmt_check);
                    $result_check = mysqli_stmt_get_result($stmt_check);
                    $inventory_data = mysqli_fetch_assoc($result_check);
                    mysqli_stmt_close($stmt_check);
                    
                    $available_quantity = $inventory_data['available_quantity'] ?? 0;
                    if ($available_quantity < $quantity_kg) {
                        throw new Exception("Insufficient inventory for product ID $product_id. Available: $available_quantity kg, Requested: $quantity_kg kg");
                    }
                }
                
                // Release all reserved inventory for this order
                $sql_release_inventory = "UPDATE inventory SET stage = 'available' WHERE stage = 'reserved' AND inventory_id IN (
                    SELECT inventory_id FROM (
                        SELECT i.inventory_id FROM inventory i 
                        JOIN order_products op ON i.product_id = op.product_id 
                        WHERE op.order_id = ? AND i.stage = 'reserved'
                    ) as temp
                )";
                if ($stmt_release = mysqli_prepare($conn, $sql_release_inventory)) {
                    mysqli_stmt_bind_param($stmt_release, "i", $order_id);
                    mysqli_stmt_execute($stmt_release);
                    mysqli_stmt_close($stmt_release);
                }
                
                // Reserve new inventory
                foreach ($order_products_with_price as $product_entry) {
                    reserveInventoryForOrder($conn, $product_entry['product_id'], $product_entry['quantity_kg'], $order_id, $logged_in_user_id);
                }
            } else {
                error_log("Order update: No inventory changes detected for order $order_id - skipping inventory operations");
            }
            
            // Handle order completion (move inventory to 'sold' stage)
            if ($status == 'completed' && $order_data['status'] != 'completed') {
                error_log("Order update: Order $order_id status changed to completed - moving inventory to sold stage");
                updateInventoryForOrder($order_id, 'sold');
            }
            
            mysqli_commit($conn);
            $_SESSION['success_message'] = "Order #" . ($order_data['order_code'] ?? $order_id) . " updated successfully! Total amount: ৳" . number_format($total_amount, 2);
            header("location: " . BASE_URL . "orders/view.php?id=" . $order_id);
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error_message'] = "Order update failed: " . $e->getMessage();
            error_log("Order update failed: " . $e->getMessage());
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Update Order #<?php echo htmlspecialchars($order_data['order_code'] ?? $order_id); ?></h2>
        
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
            <h4 class="mb-3">Order Information</h4>
            
            <!-- Customer Information -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-user me-2"></i>Customer Information</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Customer:</strong> <?php echo htmlspecialchars($order_data['customer_name']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($order_data['customer_phone'] ?? 'N/A'); ?></p>
                            <p><strong>Customer Type:</strong> 
                                <span class="badge bg-<?php echo $customer_type == 'retailer' ? 'success' : 'info'; ?>">
                                    <?php echo ucwords($customer_type); ?> Customer
                                </span>
                            </p>
                            <?php if ($customer_type == 'retailer'): ?>
                            <p><strong>Discount:</strong> <span class="badge bg-success">30% OFF</span></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Current Order Status</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Order Code:</strong> #<?php echo htmlspecialchars($order_data['order_code'] ?? $order_id); ?></p>
                            <p><strong>Current Status:</strong> 
                                <span class="badge bg-<?php 
                                    echo $order_data['status'] == 'completed' ? 'success' : 
                                        ($order_data['status'] == 'cancelled' ? 'danger' : 
                                        ($order_data['status'] == 'confirmed' ? 'info' : 'warning')); 
                                ?>">
                                    <?php echo ucwords($order_data['status']); ?>
                                </span>
                            </p>
                            <p><strong>Current Total:</strong> ৳<?php echo number_format($order_data['total_amount'], 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?id=<?php echo htmlspecialchars($order_id); ?>" method="post">
                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">
                
                <!-- Shipping Address -->
                <div class="mb-4">
                    <label for="shipping_address" class="form-label">Shipping Address <span class="text-danger">*</span></label>
                    <textarea name="shipping_address" id="shipping_address" class="form-control" rows="3" minlength="15" required><?php echo htmlspecialchars($order_data['shipping_address']); ?></textarea>
                </div>
                
                <!-- Order Status -->
                <div class="mb-4">
                    <label for="status" class="form-label">Order Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select">
                        <option value="pending" <?php echo ($order_data['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo ($order_data['status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo ($order_data['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo ($order_data['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <hr class="my-4">
                <h4>Order Products <span class="text-danger">*</span></h4>
                
                <div id="product-list" class="mb-3">
                    <?php foreach ($order_products as $index => $product): ?>
                    <div class="row product-row mb-2 align-items-end" data-index="<?php echo $index; ?>">
                        <div class="col-md-5">
                            <label for="product_id_<?php echo $index; ?>" class="form-label d-md-none">Product</label>
                            <select name="products[<?php echo $index; ?>][product_id]" id="product_id_<?php echo $index; ?>" class="form-select product-select" required>
                                <option value="">Select Product</option>
                                <?php foreach ($products_options as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option['product_id']); ?>" 
                                            <?php echo ($product['product_id'] == $option['product_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($option['product_name']); ?> (৳<?php echo htmlspecialchars(number_format($option['price_per_unit'] ?? 0, 2)); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="quantity_kg_<?php echo $index; ?>" class="form-label d-md-none">Quantity (kg)</label>
                            <input type="number" name="products[<?php echo $index; ?>][quantity_kg]" id="quantity_kg_<?php echo $index; ?>" 
                                   class="form-control product-quantity" value="<?php echo htmlspecialchars($product['quantity_kg']); ?>" 
                                   step="0.01" placeholder="Quantity in kg" min="0.01" required>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-danger btn-sm remove-product-btn"><i class="fas fa-minus-circle"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="add-product-btn" class="btn btn-info btn-sm mb-3"><i class="fas fa-plus-circle"></i> Add Another Product</button>

                <!-- Real-time Price Calculation Section -->
                <div class="card mt-4 mb-4 border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Real-time Price Calculation</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Customer Type:</label>
                                    <span class="badge bg-<?php echo $customer_type == 'retailer' ? 'success' : 'info'; ?> fs-6">
                                        <?php echo ucwords($customer_type); ?> Customer
                                    </span>
                                </div>
                                <?php if ($customer_type == 'retailer'): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Discount Applied:</label>
                                    <span class="badge bg-success fs-6">30% OFF</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Order Summary:</label>
                                    <div id="order-summary">
                                        <div class="d-flex justify-content-between">
                                            <span>Subtotal:</span>
                                            <span id="subtotal">৳0.00</span>
                                        </div>
                                        <?php if ($customer_type == 'retailer'): ?>
                                        <div class="d-flex justify-content-between text-success">
                                            <span>Discount (30%):</span>
                                            <span id="discount-amount">-৳0.00</span>
                                        </div>
                                        <?php endif; ?>
                                        <hr>
                                        <div class="d-flex justify-content-between fw-bold fs-5">
                                            <span>Total Amount:</span>
                                            <span id="total-amount" class="text-primary">৳0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-warning mt-3"><i class="fas fa-save"></i> Update Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const productList = document.getElementById('product-list');
        const addProductBtn = document.getElementById('add-product-btn');
        let productIndex = <?php echo count($order_products); ?>;

        // Customer type and discount rate from PHP
        const customerType = '<?php echo $customer_type; ?>';
        const discountRate = <?php echo $discount_rate; ?>;

        const jsProductPrices = {};
        <?php foreach ($products_options as $product): ?>
            jsProductPrices[<?php echo $product['product_id']; ?>] = {
                name: "<?php echo htmlspecialchars($product['product_name']); ?>",
                price_per_unit: <?php echo htmlspecialchars($product['price_per_unit'] ?? 0); ?>,
            };
        <?php endforeach; ?>

        // Function to calculate and update prices
        function updatePriceCalculation() {
            let subtotal = 0;
            const productRows = document.querySelectorAll('.product-row');
            
            productRows.forEach(row => {
                const productSelect = row.querySelector('.product-select');
                const quantityInput = row.querySelector('.product-quantity');
                
                if (productSelect.value && quantityInput.value) {
                    const productId = productSelect.value;
                    const quantity = parseFloat(quantityInput.value);
                    const unitPrice = jsProductPrices[productId]?.price_per_unit || 0;
                    
                    if (!isNaN(quantity) && quantity > 0) {
                        subtotal += unitPrice * quantity;
                    }
                }
            });

            const discountAmount = subtotal * discountRate;
            const totalAmount = subtotal - discountAmount;

            // Update display
            document.getElementById('subtotal').textContent = '৳' + subtotal.toFixed(2);
            if (customerType === 'retailer') {
                document.getElementById('discount-amount').textContent = '-৳' + discountAmount.toFixed(2);
            }
            document.getElementById('total-amount').textContent = '৳' + totalAmount.toFixed(2);
        }

        // Add event listeners for real-time calculation
        function addCalculationListeners() {
            const productSelects = document.querySelectorAll('.product-select');
            const quantityInputs = document.querySelectorAll('.product-quantity');

            productSelects.forEach(select => {
                select.addEventListener('change', updatePriceCalculation);
            });

            quantityInputs.forEach(input => {
                input.addEventListener('input', updatePriceCalculation);
            });
        }

        // Initial calculation
        addCalculationListeners();
        updatePriceCalculation();

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
                    <select name="products[${productIndex}][product_id]" id="product_id_${productIndex}" class="form-select product-select" required> ${productsOptionsHtml}
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="quantity_kg_${productIndex}" class="form-label d-md-none">Quantity (kg)</label>
                    <input type="number" name="products[${productIndex}][quantity_kg]" id="quantity_kg_${productIndex}" class="form-control product-quantity" value="${quantity_kg}" step="0.01" placeholder="Quantity in kg" min="0.01" required>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-product-btn"><i class="fas fa-minus-circle"></i></button>
                </div>
            `;

            productList.appendChild(newRow);
            if (productId) {
                newRow.querySelector('.product-select').value = productId;
            }
            
            // Add calculation listeners to new row
            const newSelect = newRow.querySelector('.product-select');
            const newQuantity = newRow.querySelector('.product-quantity');
            newSelect.addEventListener('change', updatePriceCalculation);
            newQuantity.addEventListener('input', updatePriceCalculation);
            
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
                        updatePriceCalculation(); // Recalculate after removal
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
            // If all rows are removed, add one back so the form is not empty
            if (productList.children.length === 0) {
                 createProductRow();
            }
        }

        // Initial check: if no rows are present, add one empty row
        if (productList.children.length === 0) {
            createProductRow();
        }
    });
</script>
