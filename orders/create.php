<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'customer' && $_SESSION["role"] != 'admin') {
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
$sql_products = "SELECT product_id, product_name, packaging_details, price_per_unit FROM products ORDER BY product_name ASC";
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
    if (empty($shipping_address)) {
        $shipping_address_err = "Please enter a shipping address.";
    }

    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        $products_err = "Please add at least one product to the order.";
    } else {
        foreach ($_POST['products'] as $product_entry) {
            $prod_id = trim($product_entry['product_id']);
            $qty = trim($product_entry['quantity']);
            $unit = trim($product_entry['unit']);
            if (empty($prod_id) || empty($qty) || !is_numeric($qty) || $qty <= 0 || empty($unit)) {
                $products_err = "All product entries must have a selected product, valid positive quantity, and unit.";
                break;
            }
            $products_data[] = ['product_id' => $prod_id, 'quantity' => $qty, 'unit' => $unit];
        }
    }
    
    if (empty($shipping_address_err) && empty($products_err)) {
        mysqli_begin_transaction($conn);
        try {
            $total_amount = 0;
            $order_products_with_price = [];
            // Calculate total amount and store the price per unit at the time of order
            foreach ($products_data as $product_entry) {
                $product_id = $product_entry['product_id'];
                $quantity = $product_entry['quantity'];
                $unit_price = $product_prices[$product_id] ?? 0;
                $final_price = $unit_price * (1 - $discount_rate);
                $total_amount += $final_price * $quantity;
                $order_products_with_price[] = [
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'unit' => $product_entry['unit'],
                    'price_at_order' => $unit_price
                ];
            }

            // Insert into orders table
            $sql_order = "INSERT INTO orders (customer_id, total_amount, shipping_address, created_by) VALUES (?, ?, ?, ?)";
            if($stmt_order = mysqli_prepare($conn, $sql_order)) {
                // FIX IS HERE: Changed "idis" to "idsi"
                mysqli_stmt_bind_param($stmt_order, "idsi", $logged_in_user_id, $total_amount, $shipping_address, $logged_in_user_id);
                if (!mysqli_stmt_execute($stmt_order)) {
                    throw new Exception("Error creating order: " . mysqli_error($conn));
                }
                $order_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt_order);
            } else {
                throw new Exception("Error preparing order insert statement: " . mysqli_error($conn));
            }
            
            // Insert into order_products table with the new price_at_order column
            $sql_order_products = "INSERT INTO order_products (order_id, product_id, quantity, unit, price_at_order) VALUES (?, ?, ?, ?, ?)";
            if($stmt_order_products = mysqli_prepare($conn, $sql_order_products)) {
                foreach($order_products_with_price as $product_entry) {
                    mysqli_stmt_bind_param($stmt_order_products, "iidsd", $order_id, $product_entry['product_id'], $product_entry['quantity'], $product_entry['unit'], $product_entry['price_at_order']);
                    if (!mysqli_stmt_execute($stmt_order_products)) {
                        throw new Exception("Error adding product to order: " . mysqli_error($conn));
                    }
                }
                mysqli_stmt_close($stmt_order_products);
            } else {
                throw new Exception("Error preparing order products insert statement: " . mysqli_error($conn));
            }

            mysqli_commit($conn);
            $_SESSION['success_message'] = "Order #" . $order_id . " placed successfully! Total amount: ৳" . number_format($total_amount, 2);
            header("location: " . BASE_URL . "orders/view.php?id=" . $order_id);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error_message'] = "Order placement failed: " . $e->getMessage();
            error_log("Order placement failed: " . $e->getMessage());
        }
    }
} else {
    $products_data[] = ['product_id' => '', 'quantity' => '', 'unit' => ''];
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
                    <textarea name="shipping_address" id="shipping_address" class="form-control <?php echo (!empty($shipping_address_err)) ? 'is-invalid' : ''; ?>" rows="3"><?php echo htmlspecialchars($shipping_address); ?></textarea>
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
                            <select name="products[<?php echo $index; ?>][product_id]" id="product_id_<?php echo $index; ?>" class="form-select product-select">
                                <option value="">Select Product</option>
                                <?php foreach ($products_options as $product): ?>
                                    <option value="<?php echo htmlspecialchars($product['product_id']); ?>"
                                        <?php echo ($product_entry['product_id'] == $product['product_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['product_name']); ?> (<?php echo htmlspecialchars($product['packaging_details']); ?>)
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
                    <button type="submit" class="btn btn-success mt-3"><i class="fas fa-shopping-cart"></i> Place Order</button>
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

        function createProductRow(productId = '', quantity = '', unit = '') {
            const newRow = document.createElement('div');
            newRow.classList.add('row', 'product-row', 'mb-2', 'align-items-end');
            newRow.setAttribute('data-index', productIndex);
            
            const productsOptionsHtml = `
                <option value="">Select Product</option>
                <?php foreach ($products_options as $product): ?>
                    <option value="<?php echo htmlspecialchars($product['product_id']); ?>">
                        <?php echo htmlspecialchars($product['product_name']); ?> (<?php echo htmlspecialchars($product['packaging_details']); ?>)
                    </option>
                <?php endforeach; ?>
            `;

            newRow.innerHTML = `
                <div class="col-md-5">
                    <label for="product_id_${productIndex}" class="form-label d-md-none">Product</label>
                    <select name="products[${productIndex}][product_id]" id="product_id_${productIndex}" class="form-select product-select">
                        ${productsOptionsHtml}
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="quantity_${productIndex}" class="form-label d-md-none">Quantity</label>
                    <input type="number" name="products[${productIndex}][quantity]" id="quantity_${productIndex}" class="form-control product-quantity" value="${quantity}" step="0.01" placeholder="Quantity">
                </div>
                <div class="col-md-3">
                    <label for="unit_${productIndex}" class="form-label d-md-none">Unit</label>
                    <input type="text" name="products[${productIndex}][unit]" id="unit_${productIndex}" class="form-control product-unit" value="${unit}" placeholder="e.g., kg, units">
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
                row.querySelectorAll('label[for^="product_id_"], label[for^="quantity_"], label[for^="unit_"]').forEach(label => {
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