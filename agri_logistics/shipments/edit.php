<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to edit shipments.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Edit Shipment";
$current_page = "shipments";

$shipment_id = $origin_location_id = $destination_location_id = $vehicle_id = $driver_id = $order_id = ""; // Added $order_id
$planned_departure = $planned_arrival = $total_weight_kg = $total_volume_m3 = $notes = "";
$products_data = []; // Array to hold dynamically added product data

$origin_location_id_err = $destination_location_id_err = $planned_departure_err = $planned_arrival_err = $products_err = "";

// Initialize audit trail variables for display
$created_at = $updated_at = $created_by_username = $updated_by_username = '';


// Fetch data for dropdowns
$locations_options = [];
$sql_locations = "SELECT location_id, name, type FROM locations ORDER BY name ASC";
if ($result_locations = mysqli_query($conn, $sql_locations)) {
    while ($row = mysqli_fetch_assoc($result_locations)) {
        $locations_options[] = $row;
    }
    mysqli_free_result($result_locations);
}

$vehicles_options = [];
// Include vehicles in-use if they are currently assigned to this shipment
$sql_vehicles = "SELECT vehicle_id, license_plate, type FROM vehicles ORDER BY license_plate ASC";
if ($result_vehicles = mysqli_query($conn, $sql_vehicles)) {
    while ($row = mysqli_fetch_assoc($result_vehicles)) {
        $vehicles_options[] = $row;
    }
    mysqli_free_result($result_vehicles);
}

$drivers_options = [];
// Include drivers on_leave if they are currently assigned to this shipment
$sql_drivers = "SELECT driver_id, first_name, last_name FROM drivers ORDER BY first_name ASC";
if ($result_drivers = mysqli_query($conn, $sql_drivers)) {
    while ($row = mysqli_fetch_assoc($result_drivers)) {
        $drivers_options[] = $row;
    }
    mysqli_free_result($result_drivers);
}

// Products for the dynamic product selection (FIXED query as per your request)
$products_options = []; 
$sql_products = "SELECT product_id, product_name, packaging_details FROM products ORDER BY product_name ASC"; 
if ($result_products = mysqli_query($conn, $sql_products)) {
    while ($row = mysqli_fetch_assoc($result_products)) {
        $products_options[] = $row;
    }
    mysqli_free_result($result_products);
}

// NEW: Fetch all orders for the dropdown (including the one assigned to this shipment)
$orders_options = [];
$sql_orders = "SELECT o.order_id, u.username FROM orders o JOIN users u ON o.customer_id = u.user_id ORDER BY o.order_id DESC";
if ($result_orders = mysqli_query($conn, $sql_orders)) {
    while ($row = mysqli_fetch_assoc($result_orders)) {
        $orders_options[] = $row;
    }
    mysqli_free_result($result_orders);
}


// Fetch existing shipment data if ID is provided
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $shipment_id = trim($_GET["id"]);

    // Fetch shipment details including audit trail and order ID
    $sql_shipment = "SELECT origin_location_id, destination_location_id, vehicle_id, driver_id, order_id, planned_departure, planned_arrival, total_weight_kg, total_volume_m3, notes, created_at, updated_at, created_by, updated_by FROM shipments WHERE shipment_id = ?";
    if ($stmt_shipment = mysqli_prepare($conn, $sql_shipment)) {
        mysqli_stmt_bind_param($stmt_shipment, "i", $param_id);
        $param_id = $shipment_id;
        if (mysqli_stmt_execute($stmt_shipment)) {
            $result_shipment = mysqli_stmt_get_result($stmt_shipment);
            if (mysqli_num_rows($result_shipment) == 1) {
                $row = mysqli_fetch_assoc($result_shipment);
                $origin_location_id = $row['origin_location_id'];
                $destination_location_id = $row['destination_location_id'];
                $vehicle_id = $row['vehicle_id'];
                $driver_id = $row['driver_id'];
                $order_id = $row['order_id']; // NEW: Fetch order_id
                // Format datetime for datetime-local input
                $planned_departure = date('Y-m-d\TH:i', strtotime($row['planned_departure']));
                $planned_arrival = date('Y-m-d\TH:i', strtotime($row['planned_arrival']));
                $total_weight_kg = $row['total_weight_kg'];
                $total_volume_m3 = $row['total_volume_m3'];
                $notes = $row['notes'];
                // Capture audit data for display
                $created_at = $row["created_at"];
                $updated_at = $row["updated_at"];
                $created_by_id = $row["created_by"];
                $updated_by_id = $row["updated_by"];
                
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
                $_SESSION['error_message'] = "Shipment not found.";
                header("location: " . BASE_URL . "shipments/index.php"); // Use BASE_URL
                exit();
            }
        } else {
            $_SESSION['error_message'] = "Error fetching shipment details: " . mysqli_error($conn);
            error_log("Error executing shipment fetch: " . mysqli_error($conn));
            header("location: " . BASE_URL . "shipments/index.php"); // Use BASE_URL
            exit();
        }
        mysqli_stmt_close($stmt_shipment);
    } else {
        $_SESSION['error_message'] = "Error preparing shipment query.";
        error_log("Error preparing shipment fetch statement: " . mysqli_error($conn));
        header("location: " . BASE_URL . "shipments/index.php"); // Use BASE_URL
        exit();
    }

    // Fetch products associated with this shipment
    $sql_products_in_shipment = "SELECT product_id, quantity, unit FROM shipment_products WHERE shipment_id = ?";
    if ($stmt_products_in_shipment = mysqli_prepare($conn, $sql_products_in_shipment)) {
        mysqli_stmt_bind_param($stmt_products_in_shipment, "i", $param_id);
        $param_id = $shipment_id;
        if (mysqli_stmt_execute($stmt_products_in_shipment)) {
            $result_products_in_shipment = mysqli_stmt_get_result($stmt_products_in_shipment);
            while ($row = mysqli_fetch_assoc($result_products_in_shipment)) {
                $products_data[] = $row;
            }
        } else {
            $_SESSION['error_message'] = "Error fetching products in shipment: " . mysqli_error($conn);
            error_log("Error fetching products in shipment: " . mysqli_error($conn));
            header("location: " . BASE_URL . "shipments/index.php"); // Use BASE_URL
            exit();
        }
        mysqli_stmt_close($stmt_products_in_shipment);
    } else {
        $_SESSION['error_message'] = "Error preparing products in shipment query.";
        error_log("Error preparing products in shipment query: " . mysqli_error($conn));
        header("location: " . BASE_URL . "shipments/index.php"); // Use BASE_URL
        exit();
    }

} else if ($_SERVER["REQUEST_METHOD"] != "POST") { // If not POST and no ID, redirect
    $_SESSION['error_message'] = "Invalid request. No shipment ID provided.";
    header("location: " . BASE_URL . "shipments/index.php"); // Use BASE_URL
    exit();
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $shipment_id = $_POST["shipment_id"]; // Get ID from hidden field

    // Validate main shipment details
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

    // Optional fields
    $vehicle_id = !empty($_POST["vehicle_id"]) ? $_POST["vehicle_id"] : NULL;
    $driver_id = !empty($_POST["driver_id"]) ? $_POST["driver_id"] : NULL;
    $order_id = !empty($_POST["order_id"]) ? $_POST["order_id"] : NULL; // NEW
    $total_weight_kg = !empty($_POST["total_weight_kg"]) && is_numeric($_POST["total_weight_kg"]) ? $_POST["total_weight_kg"] : NULL;
    $total_volume_m3 = !empty($_POST["total_volume_m3"]) && is_numeric($_POST["total_volume_m3"]) ? $_POST["total_volume_m3"] : NULL;
    $notes = trim($_POST["notes"]);

    // Validate products in shipment
    $new_products_data = [];
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
            $new_products_data[] = ['product_id' => $prod_id, 'quantity' => $qty, 'unit' => $unit];
        }
        $products_data = $new_products_data; // Update for display if there's an error
    }

    // Check for any errors before updating
    if (empty($origin_location_id_err) && empty($destination_location_id_err) && empty($planned_departure_err) && empty($planned_arrival_err) && empty($products_err)) {

        mysqli_begin_transaction($conn);
        $logged_in_user_id = $_SESSION['user_id'];

        try {
            // Update shipments table
            // FIX: Add 'updated_by' and 'order_id' to the UPDATE statement
            $sql_shipment = "UPDATE shipments SET origin_location_id = ?, destination_location_id = ?, vehicle_id = ?, driver_id = ?, order_id = ?, planned_departure = ?, planned_arrival = ?, total_weight_kg = ?, total_volume_m3 = ?, notes = ?, updated_by = ? WHERE shipment_id = ?";
            if ($stmt_shipment = mysqli_prepare($conn, $sql_shipment)) {
                // FIX: Add 'i' for updated_by and the $param_updated_by variable
               mysqli_stmt_bind_param($stmt_shipment, "iiisssddsssi", $param_origin, $param_destination, $param_vehicle, $param_driver, $param_order_id, $param_planned_dep, $param_planned_arr, $param_weight, $param_volume, $param_notes, $param_status, $param_created_by);


                $param_origin = $origin_location_id;
                $param_destination = $destination_location_id;
                $param_vehicle = $vehicle_id;
                $param_driver = $driver_id;
                $param_order_id = $order_id; // NEW
                $param_planned_dep = $planned_departure;
                $param_planned_arr = $planned_arrival;
                $param_weight = $total_weight_kg;
                $param_volume = $total_volume_m3;
                $param_notes = $notes;
                $param_updated_by = $logged_in_user_id; // Capture logged-in user's ID
                $param_shipment_id = $shipment_id;

                if (!mysqli_stmt_execute($stmt_shipment)) {
                    throw new Exception("Error updating shipment: " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt_shipment);
            } else {
                throw new Exception("Error preparing shipment update statement: " . mysqli_error($conn));
            }

            // Update shipment_products: Delete existing and re-insert new ones
            $sql_delete_products = "DELETE FROM shipment_products WHERE shipment_id = ?";
            if ($stmt_delete = mysqli_prepare($conn, $sql_delete_products)) {
                mysqli_stmt_bind_param($stmt_delete, "i", $param_shipment_id);
                $param_shipment_id = $shipment_id;
                if (!mysqli_stmt_execute($stmt_delete)) {
                    throw new Exception("Error deleting old shipment products: " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt_delete);
            } else {
                throw new Exception("Error preparing delete old shipment products statement: " . mysqli_error($conn));
            }

            $sql_insert_products = "INSERT INTO shipment_products (shipment_id, product_id, quantity, unit) VALUES (?, ?, ?, ?)";
            if ($stmt_insert_products = mysqli_prepare($conn, $sql_insert_products)) {
                foreach ($new_products_data as $product_entry) {
                    mysqli_stmt_bind_param($stmt_insert_products, "iids", $param_shipment_id, $param_product_id, $param_quantity, $param_unit);

                    $param_shipment_id = $shipment_id;
                    $param_product_id = $product_entry['product_id'];
                    $param_quantity = $product_entry['quantity'];
                    $param_unit = $product_entry['unit'];

                    if (!mysqli_stmt_execute($stmt_insert_products)) {
                        throw new Exception("Error re-inserting product into shipment: " . mysqli_error($conn));
                    }
                }
                mysqli_stmt_close($stmt_insert_products);
            } else {
                throw new Exception("Error preparing re-insert shipment products statement: " . mysqli_error($conn));
            }

            mysqli_commit($conn);
            $_SESSION['success_message'] = "Shipment ID: " . $shipment_id . " updated successfully!";
            header("location: " . BASE_URL . "shipments/index.php"); // Use BASE_URL for redirect
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error_message'] = "Transaction failed: " . $e->getMessage();
            error_log("Shipment update failed: " . $e->getMessage());
        } // The finally block is removed as connection is closed in footer.php
    }
}
// mysqli_close($conn) is in footer.php
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Edit Shipment (ID: <?php echo htmlspecialchars($shipment_id); ?>)</h2>
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
                <input type="hidden" name="shipment_id" value="<?php echo htmlspecialchars($shipment_id); ?>">

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
                    <small class="form-text text-muted">A shipment will automatically be created in 'pending' status for the selected order.</small>
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
                    <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-sync-alt"></i> Update Shipment</button>
                </div>
            </form>
            <?php if (isset($created_at) || isset($updated_at)): ?>
            <div class="mt-3 border-top pt-3 text-muted small">
                <?php if (isset($created_at)): ?>
                    Created: <?php echo htmlspecialchars($created_at); ?> by <?php echo htmlspecialchars($created_by_username ?: 'N/A'); ?><br>
                <?php endif; ?>
                <?php if (isset($updated_at)): ?>
                    Last Updated: <?php echo htmlspecialchars($updated_at); ?> by <?php echo htmlspecialchars($updated_by_username ?: 'N/A'); ?>
                <?php endif; ?>
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
        let productIndex = <?php echo count($products_data); ?>; // Start index after pre-populated items

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

            // Set selected value if pre-populating
            if (productId) {
                newRow.querySelector('.product-select').value = productId;
            }

            productIndex++;
        }

        // Add Product button event listener
        addProductBtn.addEventListener('click', function() {
            createProductRow();
        });

        // Remove Product button event listener (delegated)
        productList.addEventListener('click', function(event) {
            if (event.target.classList.contains('remove-product-btn') || event.target.closest('.remove-product-btn')) {
                // Ensure at least one row remains
                if (productList.children.length > 1) {
                    const rowToRemove = event.target.closest('.product-row');
                    if (rowToRemove) {
                        rowToRemove.remove();
                        reindexProductRows(); // Reindex rows after removal
                    }
                } else {
                    alert("A shipment must contain at least one product.");
                }
            }
        });

        // Function to re-index product rows after removal
        function reindexProductRows() {
            const rows = productList.querySelectorAll('.product-row');
            rows.forEach((row, index) => {
                row.setAttribute('data-index', index);
                row.querySelectorAll('[name^="products["]').forEach(input => {
                    const oldName = input.name;
                    // Replace the numerical index in the name attribute
                    input.name = oldName.replace(/products\[\d+\]/, `products[${index}]`);
                    // Update ID attribute
                    input.id = input.id.replace(/_\d+/, `_${index}`);
                });
                row.querySelectorAll('label[for^="product_id_"], label[for^="quantity_"], label[for^="unit_"]').forEach(label => {
                    const oldFor = label.htmlFor;
                    label.htmlFor = oldFor.replace(/_\d+/, `_${index}`);
                });
            });
            productIndex = rows.length; // Update the global index counter
        }

        // Initial check: if no rows are present (e.g., first load and no POST error), add one
        if (productList.children.length === 0) {
            createProductRow();
        }
    });
</script>