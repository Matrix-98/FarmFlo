<?php
require_once '../config/db.php';
require_once '../utils/inventory_helpers.php';
require_once '../utils/id_generator.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if (!in_array($_SESSION["role"], ['customer', 'admin', 'logistics_manager'])) {
    $_SESSION['error_message'] = "You do not have permission to place orders.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Place New Order";
$current_page = "orders";

$products_data = []; // Holds selected products from form
$products_options = []; // All products for the dropdown
$products_err = "";
$shipping_address = "";
$shipping_address_err = "";
$order_placement_message = '';
$logged_in_user_id = $_SESSION['user_id'];
$customer_type = 'direct';
$discount_rate = 0.0;
$product_prices = []; // Array to store prices fetched from the database

// Fetch customer details to get their type and apply discount
$sql_customer = "SELECT customer_type FROM users WHERE user_id = ?";
if($stmt_customer = mysqli_prepare($conn, $sql_customer)) {
    mysqli_stmt_bind_param($stmt_customer, "i", $logged_in_user_id);
    mysqli_stmt_execute($stmt_customer);
    $result_customer = mysqli_stmt_get_result($stmt_customer);
    if($row_customer = mysqli_fetch_assoc($result_customer)) {
        $customer_type = $row_customer['customer_type'];
        if ($customer_type == 'retailer') {
            $discount_rate = 0.30; // 30% discount
        }
    }
    mysqli_stmt_close($stmt_customer);
}

// Fetch all products for the dropdown AND their prices
$sql_products = "SELECT product_id, name as product_name, price_per_unit FROM products ORDER BY name ASC";
if ($result_products = mysqli_query($conn, $sql_products)) {
    while ($row = mysqli_fetch_assoc($result_products)) {
        $products_options[] = $row;
        $product_prices[$row['product_id']] = $row['price_per_unit'];
    }
    mysqli_free_result($result_products);
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $shipping_address = trim($_POST['shipping_address']);
    // FIX: Added minimum length validation for shipping address
    if (empty($shipping_address)) {
        $shipping_address_err = "Please enter a shipping address.";
    } elseif (strlen($shipping_address) < 10) {
        $shipping_address_err = "Shipping address must be at least 10 characters long.";
    }


    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        $products_err = "Please add at least one product to the order.";
    } else {
                    foreach ($_POST['products'] as $product_entry) {
                $prod_id = trim($product_entry['product_id']);
                $qty = trim($product_entry['quantity_kg']);
                if (empty($prod_id) || empty($qty) || !is_numeric($qty) || $qty <= 0) {
                    $products_err = "All product entries must have a selected product and valid positive quantity in kg.";
                    break;
                }
                $products_data[] = ['product_id' => $prod_id, 'quantity_kg' => $qty];
            }
    }
    
    if (empty($shipping_address_err) && empty($products_err)) {
        mysqli_begin_transaction($conn);
        try {
            $total_amount = 0;
            $order_products_with_price = [];
            
            // Re-fetch product prices securely to prevent tampering from client-side JavaScript
            $secure_product_prices = [];
            $product_ids_in_order = array_column($products_data, 'product_id');
            if (!empty($products_data) && $result_secure_prices = mysqli_query($conn, "SELECT product_id, price_per_unit FROM products WHERE product_id IN (" . implode(',', array_map('intval', $product_ids_in_order)) . ")")) {
                while($row = mysqli_fetch_assoc($result_secure_prices)) {
                    $secure_product_prices[$row['product_id']] = $row['price_per_unit'];
                }
            } else if (!empty($products_data)) {
                 throw new Exception("Error fetching secure product prices: " . mysqli_error($conn));
            }


            // Check inventory availability and calculate total amount
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

            // Insert into orders table
            $order_code = generateOrderId();
            $sql_order = "INSERT INTO orders (order_code, customer_id, total_amount, shipping_address, created_by) VALUES (?, ?, ?, ?, ?)";
            if($stmt_order = mysqli_prepare($conn, $sql_order)) {
                mysqli_stmt_bind_param($stmt_order, "sidsi", $order_code, $logged_in_user_id, $total_amount, $shipping_address, $logged_in_user_id);
                if (!mysqli_stmt_execute($stmt_order)) {
                    throw new Exception("Error creating order: " . mysqli_error($conn));
                }
                $order_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt_order);
            } else {
                throw new Exception("Error preparing order insert statement: " . mysqli_error($conn));
            }
            
            // Insert into order_products table with the new price_at_order column
            $sql_order_products = "INSERT INTO order_products (order_id, product_id, quantity_kg, price_at_order) VALUES (?, ?, ?, ?)";
            if($stmt_order_products = mysqli_prepare($conn, $sql_order_products)) {
                foreach($order_products_with_price as $product_entry) {
                    mysqli_stmt_bind_param($stmt_order_products, "iids", $order_id, $product_entry['product_id'], $product_entry['quantity_kg'], $product_entry['price_at_order']);
                    if (!mysqli_stmt_execute($stmt_order_products)) {
                        throw new Exception("Error adding product to order: " . mysqli_error($conn));
                    }
                }
                mysqli_stmt_close($stmt_order_products);
            } else {
                throw new Exception("Error preparing order products insert statement: " . mysqli_error($conn));
            }
            
            // Create unique inventory reservations for the order
            foreach ($order_products_with_price as $product_entry) {
                createUniqueInventoryReservation($conn, $product_entry['product_id'], $product_entry['quantity_kg'], $order_id, $logged_in_user_id);
            }

            mysqli_commit($conn);
            $_SESSION['success_message'] = "Order #" . $order_code . " placed successfully! Total amount: ৳" . number_format($total_amount, 2);
            header("location: " . BASE_URL . "orders/view.php?id=" . $order_id);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error_message'] = "Order placement failed: " . $e->getMessage();
            error_log("Order placement failed: " . $e->getMessage());
        }
    }
} else {
    $products_data[] = ['product_id' => '', 'quantity_kg' => ''];
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Place New Order</h2>
        <div class="d-flex justify-content-end mb-3">
             <a href="<?php echo BASE_URL; ?>orders/index.php" class="btn btn-secondary"><i class="fas fa-list"></i> View My Orders</a>
        </div>
        
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
            <h4 class="mb-3">Order Details</h4>
            <div class="alert alert-info">
                You are a **<?php echo htmlspecialchars(ucwords($customer_type)); ?> Customer**. 
                <?php if ($customer_type == 'retailer'): ?>
                    A **30% discount** will be automatically applied.
                <?php endif; ?>
            </div>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-3">
                    <label for="shipping_address" class="form-label">Shipping Address <span class="text-danger">*</span></label>
                    <textarea name="shipping_address" id="shipping_address" class="form-control <?php echo (!empty($shipping_address_err)) ? 'is-invalid' : ''; ?>" rows="3" minlength="10" placeholder="House, Road, Area, Upazila, Jela, Postcode" required><?php echo htmlspecialchars($shipping_address); ?></textarea>
                    <div class="invalid-feedback"><?php echo $shipping_address_err; ?></div>
                </div>
                
                <hr class="my-4">
                <h4>Products for Order <span class="text-danger">*</span></h4>
                <?php if (!empty($products_err)): ?>
                    <div class="alert alert-danger"><?php echo $products_err; ?></div>
                <?php endif; ?>

                <div id="product-list" class="mb-3">
                    <?php foreach ($products_data as $index => $product_entry): ?>
                    <div class="row product-row mb-2 align-items-end" data-index="<?php echo $index; ?>">
                        <div class="col-md-5">
                            <label for="product_id_<?php echo $index; ?>" class="form-label d-md-none">Product</label>
                            <select name="products[<?php echo $index; ?>][product_id]" id="product_id_<?php echo $index; ?>" class="form-select product-select" required>
                                <option value="">Select Product</option>
                                <?php foreach ($products_options as $product): ?>
                                    <option value="<?php echo htmlspecialchars($product['product_id']); ?>" <?php echo ($product_entry['product_id'] == $product['product_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['product_name']); ?> (৳<?php echo htmlspecialchars(number_format($product['price_per_unit'] ?? 0, 2)); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="quantity_kg_<?php echo $index; ?>" class="form-label d-md-none">Quantity (kg)</label>
                            <input type="number" name="products[<?php echo $index; ?>][quantity_kg]" id="quantity_kg_<?php echo $index; ?>" class="form-control product-quantity" value="<?php echo htmlspecialchars($product_entry['quantity_kg']); ?>" step="0.01" placeholder="Quantity in kg" min="0.01" required>
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
                    <button type="submit" id="place-order-btn" class="btn btn-success mt-3" disabled><i class="fas fa-shopping-cart"></i> Place Order</button>
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
        let productIndex = <?php echo count($products_data); ?>;

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
             
             // Add validation listeners to new row
             newSelect.addEventListener('change', validateForm);
             newQuantity.addEventListener('input', validateForm);
            
            productIndex++;
        }

                 addProductBtn.addEventListener('click', function() {
             createProductRow();
             validateForm(); // Validate after adding product
         });

        productList.addEventListener('click', function(event) {
            if (event.target.classList.contains('remove-product-btn') || event.target.closest('.remove-product-btn')) {
                if (productList.children.length > 1) {
                    const rowToRemove = event.target.closest('.product-row');
                                         if (rowToRemove) {
                         rowToRemove.remove();
                         reindexProductRows();
                         updatePriceCalculation(); // Recalculate after removal
                         validateForm(); // Validate after removing product
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

                 // Initial check: if no rows are present (e.g., first load and no POST error), add one empty row
         if (productList.children.length === 0) {
             createProductRow();
         }

         // Form validation function
         function validateForm() {
             const placeOrderBtn = document.getElementById('place-order-btn');
             const shippingAddress = document.getElementById('shipping_address');
             const productRows = document.querySelectorAll('.product-row');
             
             let isValid = true;
             let errorMessages = [];

             // Check shipping address
             if (!shippingAddress.value.trim()) {
                 isValid = false;
                 errorMessages.push('Shipping address is required');
             } else if (shippingAddress.value.trim().length < 10) {
                 isValid = false;
                 errorMessages.push('Shipping address must be at least 10 characters');
             }

             // Check if at least one product is selected with valid quantity
             let hasValidProduct = false;
             productRows.forEach(row => {
                 const productSelect = row.querySelector('.product-select');
                 const quantityInput = row.querySelector('.product-quantity');
                 
                 if (productSelect.value && quantityInput.value) {
                     const quantity = parseFloat(quantityInput.value);
                     if (!isNaN(quantity) && quantity > 0) {
                         hasValidProduct = true;
                     }
                 }
             });

             if (!hasValidProduct) {
                 isValid = false;
                 errorMessages.push('At least one product with valid quantity is required');
             }

             // Update button state
             if (isValid) {
                 placeOrderBtn.disabled = false;
                 placeOrderBtn.classList.remove('btn-secondary');
                 placeOrderBtn.classList.add('btn-success');
                 placeOrderBtn.title = 'Click to place order';
             } else {
                 placeOrderBtn.disabled = true;
                 placeOrderBtn.classList.remove('btn-success');
                 placeOrderBtn.classList.add('btn-secondary');
                 placeOrderBtn.title = 'Please fill all required fields: ' + errorMessages.join(', ');
             }

             return isValid;
         }

         // Add validation listeners
         function addValidationListeners() {
             const shippingAddress = document.getElementById('shipping_address');
             const productList = document.getElementById('product-list');

             // Listen for shipping address changes
             shippingAddress.addEventListener('input', validateForm);
             shippingAddress.addEventListener('blur', validateForm);

             // Listen for product changes
             productList.addEventListener('change', function(event) {
                 if (event.target.classList.contains('product-select') || event.target.classList.contains('product-quantity')) {
                     validateForm();
                 }
             });

             productList.addEventListener('input', function(event) {
                 if (event.target.classList.contains('product-quantity')) {
                     validateForm();
                 }
             });
         }

         // Initialize validation
         addValidationListeners();
         validateForm(); // Initial validation
     });
</script>