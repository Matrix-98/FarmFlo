<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to create shipments.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Create Shipment";
$current_page = "shipments";

$origin_location_id = $destination_location_id = $vehicle_id = $driver_id = $order_id = "";
$planned_departure = $planned_arrival = $total_weight_kg = $total_volume_m3 = $notes = "";
$products_data = [];

$origin_location_id_err = $destination_location_id_err = $planned_departure_err = $planned_arrival_err = $products_err = "";

$locations_options = [];
$sql_locations = "SELECT location_id, name, type FROM locations ORDER BY name ASC";
if ($result_locations = mysqli_query($conn, $sql_locations)) {
    while ($row = mysqli_fetch_assoc($result_locations)) {
        $locations_options[] = $row;
    }
    mysqli_free_result($result_locations);
}

$vehicles_options = [];
$sql_vehicles = "SELECT vehicle_id, license_plate, type FROM vehicles WHERE status = 'available' ORDER BY license_plate ASC";
if ($result_vehicles = mysqli_query($conn, $sql_vehicles)) {
    while ($row = mysqli_fetch_assoc($result_vehicles)) {
        $vehicles_options[] = $row;
    }
    mysqli_free_result($result_vehicles);
}

$drivers_options = [];
$sql_drivers = "SELECT driver_id, first_name, last_name FROM drivers WHERE status = 'active' ORDER BY first_name ASC";
if ($result_drivers = mysqli_query($conn, $sql_drivers)) {
    while ($row = mysqli_fetch_assoc($result_drivers)) {
        $drivers_options[] = $row;
    }
    mysqli_free_result($result_drivers);
}

$products_options = [];
$sql_products = "SELECT product_id, product_name, packaging_details FROM products ORDER BY product_name ASC";
if ($result_products = mysqli_query($conn, $sql_products)) {
    while ($row = mysqli_fetch_assoc($result_products)) {
        $products_options[] = $row;
    }
    mysqli_free_result($result_products);
}

$orders_options = [];
$sql_orders = "SELECT o.order_id, u.username FROM orders o LEFT JOIN shipments s ON o.order_id = s.order_id JOIN users u ON o.customer_id = u.user_id WHERE s.order_id IS NULL ORDER BY o.order_id DESC";
if ($result_orders = mysqli_query($conn, $sql_orders)) {
    while ($row = mysqli_fetch_assoc($result_orders)) {
        $orders_options[] = $row;
    }
    mysqli_free_result($result_orders);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (empty(trim($_POST["origin_location_id"]))) {
        $origin_location_id_err = "Please select an origin location.";
    } else {
        $origin_location_id = trim($_POST["origin_location_id"]);
    }

    if (empty(trim($_POST["destination_location_id"]))) {
        $destination_location_id_err = "Please select a destination location.";
    } else {
        $destination_location_id = trim($_POST["destination_location_id"]);
    }

    if ($origin_location_id == $destination_location_id && !empty($origin_location_id) && !empty($destination_location_id)) {
        $destination_location_id_err = "Origin and Destination cannot be the same.";
    }

    if (empty(trim($_POST["planned_departure"]))) {
        $planned_departure_err = "Please enter a planned departure date/time.";
    } else {
        $planned_departure = trim($_POST["planned_departure"]);
    }

    if (empty(trim($_POST["planned_arrival"]))) {
        $planned_arrival_err = "Please enter a planned arrival date/time.";
    } else {
        $planned_arrival = trim($_POST["planned_arrival"]);
        if (!empty($planned_departure) && strtotime($planned_arrival) <= strtotime($planned_departure)) {
            $planned_arrival_err = "Planned arrival must be after planned departure.";
        }
    }

    $vehicle_id = !empty($_POST["vehicle_id"]) ? $_POST["vehicle_id"] : NULL;
    $driver_id = !empty($_POST["driver_id"]) ? $_POST["driver_id"] : NULL;
    $order_id = !empty($_POST["order_id"]) ? $_POST["order_id"] : NULL;
    $total_weight_kg = !empty($_POST["total_weight_kg"]) && is_numeric($_POST["total_weight_kg"]) ? $_POST["total_weight_kg"] : NULL;
    $total_volume_m3 = !empty($_POST["total_volume_m3"]) && is_numeric($_POST["total_volume_m3"]) ? $_POST["total_volume_m3"] : NULL;
    $notes = trim($_POST["notes"]);

    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        $products_err = "Please add at least one product to the shipment.";
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

    if (empty($origin_location_id_err) && empty($destination_location_id_err) && empty($planned_departure_err) && empty($planned_arrival_err) && empty($products_err)) {

        mysqli_begin_transaction($conn);
        $logged_in_user_id = $_SESSION['user_id'];
        $param_status = 'pending';

        try {
            $sql_shipment = "INSERT INTO shipments (origin_location_id, destination_location_id, vehicle_id, driver_id, order_id, planned_departure, planned_arrival, total_weight_kg, total_volume_m3, notes, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_shipment = mysqli_prepare($conn, $sql_shipment)) {
                
                // NEW: Use a dynamic array for binding parameters
                $bind_params_shipment = [
                    $origin_location_id,
                    $destination_location_id,
                    $vehicle_id,
                    $driver_id,
                    $order_id,
                    $planned_departure,
                    $planned_arrival,
                    $total_weight_kg,
                    $total_volume_m3,
                    $notes,
                    $param_status,
                    $logged_in_user_id
                ];

                // NEW: Use a dynamic type string based on the parameters
                $bind_types_shipment = "";
                foreach ($bind_params_shipment as $param) {
                    if (is_int($param)) {
                        $bind_types_shipment .= 'i';
                    } elseif (is_double($param)) {
                        $bind_types_shipment .= 'd';
                    } elseif (is_string($param)) {
                        $bind_types_shipment .= 's';
                    } else {
                        // Default to string for NULLs or other types
                        $bind_types_shipment .= 's';
                    }
                }
                
                // Final bind call using dynamic arrays
                if (mysqli_stmt_bind_param($stmt_shipment, $bind_types_shipment, ...$bind_params_shipment)) {
                    if (!mysqli_stmt_execute($stmt_shipment)) {
                        throw new Exception("Error creating shipment: " . mysqli_error($conn));
                    }
                    $shipment_id = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt_shipment);
                } else {
                    throw new Exception("Error preparing shipment bind parameters: " . mysqli_error($conn));
                }
                
            } else {
                throw new Exception("Error preparing shipment insert statement: " . mysqli_error($conn));
            }

            $sql_shipment_products = "INSERT INTO shipment_products (shipment_id, product_id, quantity, unit) VALUES (?, ?, ?, ?)";
            if ($stmt_shipment_products = mysqli_prepare($conn, $sql_shipment_products)) {
                foreach ($products_data as $product_entry) {
                    mysqli_stmt_bind_param($stmt_shipment_products, "iids", $shipment_id, $product_entry['product_id'], $product_entry['quantity'], $product_entry['unit']);
                    if (!mysqli_stmt_execute($stmt_shipment_products)) {
                        throw new Exception("Error adding product to shipment: " . mysqli_error($conn));
                    }
                }
                mysqli_stmt_close($stmt_shipment_products);
            } else {
                throw new Exception("Error preparing shipment products insert statement: " . mysqli_error($conn));
            }

            if ($order_id) {
                $sql_update_order = "UPDATE orders SET status = 'shipped', updated_by = ? WHERE order_id = ?";
                if ($stmt_update_order = mysqli_prepare($conn, $sql_update_order)) {
                    mysqli_stmt_bind_param($stmt_update_order, "ii", $logged_in_user_id, $order_id);
                    if (!mysqli_stmt_execute($stmt_update_order)) {
                         error_log("Error updating order status after shipment creation: " . mysqli_error($conn));
                    }
                    mysqli_stmt_close($stmt_update_order);
                } else {
                     error_log("Error preparing order status update after shipment creation: " . mysqli_error($conn));
                }
            }
            
            mysqli_commit($conn);
            $_SESSION['success_message'] = "Shipment created successfully for Order ID: " . ($order_id ?: 'N/A') . " with Shipment ID: " . $shipment_id;
            header("location: " . BASE_URL . "shipments/index.php");
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error_message'] = "Transaction failed: " . $e->getMessage();
            error_log("Shipment creation failed: " . $e->getMessage());
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
        <h2 class="mb-4">Create New Shipment</h2>
        <a href="<?php echo BASE_URL; ?>shipments/index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Shipment List</a>

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
                <div class="mb-3">
                    <label for="order_id" class="form-label">Link to Order (Optional)</label>
                    <select name="order_id" id="order_id" class="form-select">
                        <option value="">Select an Unassigned Order</option>
                        <?php foreach ($orders_options as $order): ?>
                            <option value="<?php echo htmlspecialchars($order['order_id']); ?>" <?php echo ($order_id == $order['order_id']) ? 'selected' : ''; ?>>
                                Order #<?php echo htmlspecialchars($order['order_id']); ?> (Customer: <?php echo htmlspecialchars($order['username']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">A shipment will automatically be created in 'pending' status for the selected order. You can optionally add more products below.</small>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="origin_location_id" class="form-label">Origin Location <span class="text-danger">*</span></label>
                        <select name="origin_location_id" id="origin_location_id" class="form-select <?php echo (!empty($origin_location_id_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Origin</option>
                            <?php foreach ($locations_options as $location): ?>
                                <option value="<?php echo htmlspecialchars($location['location_id']); ?>" <?php echo ($origin_location_id == $location['location_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location['name']); ?> (<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $location['type']))); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $origin_location_id_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="destination_location_id" class="form-label">Destination Location <span class="text-danger">*</span></label>
                        <select name="destination_location_id" id="destination_location_id" class="form-select <?php echo (!empty($destination_location_id_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Destination</option>
                            <?php foreach ($locations_options as $location): ?>
                                <option value="<?php echo htmlspecialchars($location['location_id']); ?>" <?php echo ($destination_location_id == $location['location_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location['name']); ?> (<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $location['type']))); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $destination_location_id_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="planned_departure" class="form-label">Planned Departure <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="planned_departure" id="planned_departure" class="form-control <?php echo (!empty($planned_departure_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($planned_departure); ?>">
                        <div class="invalid-feedback"><?php echo $planned_departure_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="planned_arrival" class="form-label">Planned Arrival <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="planned_arrival" id="planned_arrival" class="form-control <?php echo (!empty($planned_arrival_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($planned_arrival); ?>">
                        <div class="invalid-feedback"><?php echo $planned_arrival_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="vehicle_id" class="form-label">Assign Vehicle</label>
                        <select name="vehicle_id" id="vehicle_id" class="form-select">
                            <option value="">Select Vehicle (Optional)</option>
                            <?php foreach ($vehicles_options as $vehicle): ?>
                                <option value="<?php echo htmlspecialchars($vehicle['vehicle_id']); ?>" <?php echo ($vehicle_id == $vehicle['vehicle_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vehicle['license_plate']); ?> (<?php echo htmlspecialchars($vehicle['type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="driver_id" class="form-label">Assign Driver</label>
                        <select name="driver_id" id="driver_id" class="form-select">
                            <option value="">Select Driver (Optional)</option>
                            <?php foreach ($drivers_options as $driver): ?>
                                <option value="<?php echo htmlspecialchars($driver['driver_id']); ?>" <?php echo ($driver_id == $driver['driver_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="total_weight_kg" class="form-label">Total Estimated Weight (kg)</label>
                        <input type="number" name="total_weight_kg" id="total_weight_kg" class="form-control" value="<?php echo htmlspecialchars($total_weight_kg); ?>" step="0.01">
                        <small class="form-text text-muted">Leave empty or set to 0 if unknown/not applicable.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="total_volume_m3" class="form-label">Total Estimated Volume (m³)</label>
                        <input type="number" name="total_volume_m3" id="total_volume_m3" class="form-control" value="<?php echo htmlspecialchars($total_volume_m3); ?>" step="0.01">
                        <small class="form-text text-muted">Leave empty or set to 0 if unknown/not applicable.</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3"><?php echo htmlspecialchars($notes); ?></textarea>
                </div>

                <hr class="my-4">
                <h4>Products for Shipment <span class="text-danger">*</span></h4>
                <?php if (!empty($products_err)): ?>
                    <div class="alert alert-danger"><?php echo $products_err; ?></div>
                <?php endif; ?>

                <div id="product-list" class="mb-3">
                    <?php
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
                                        <?php echo htmlspecialchars($product['product_name']); ?> (<?php echo htmlspecialchars($product['packaging_details']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="quantity_<?php echo $index; ?>" class="form-label d-md-none">Quantity</label>
                            <input type="number" name="products[<?php echo $index; ?>][quantity]" id="quantity_<?php echo $index; ?>" class="form-control product-quantity" value="<?php echo htmlspecialchars($product_entry['quantity']); ?>" step="0.01">
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
                    <button type="submit" class="btn btn-success mt-3"><i class="fas fa-truck-loading"></i> Create Shipment</button>
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
                    <input type="number" name="products[${productIndex}][quantity]" id="quantity_${productIndex}" class="form-control product-quantity" value="${quantity}" step="0.01">
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
                    alert("A shipment must contain at least one product.");
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