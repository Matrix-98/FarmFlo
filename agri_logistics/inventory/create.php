<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'farm_manager' && $_SESSION["role"] != 'warehouse_manager') {
    $_SESSION['error_message'] = "You do not have permission to add/update inventory.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Add/Update Stock";
$current_page = "inventory";

$product_id = $location_id = $quantity = $unit = $stage = "";
$product_id_err = $location_id_err = $quantity_err = $unit_err = $stage_err = "";

// Fetch products for dropdown
$products_options = [];
$sql_products = "SELECT product_id, product_name, packaging_details FROM products ORDER BY product_name ASC";
if ($result_products = mysqli_query($conn, $sql_products)) {
    while ($row = mysqli_fetch_assoc($result_products)) {
        $products_options[] = $row;
    }
    mysqli_free_result($result_products);
}

// Fetch locations for dropdown
$locations_options = [];
$sql_locations = "SELECT location_id, name FROM locations ORDER BY name ASC";
if ($result_locations = mysqli_query($conn, $sql_locations)) {
    while ($row = mysqli_fetch_assoc($result_locations)) {
        $locations_options[] = $row;
    }
    mysqli_free_result($result_locations);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (empty(trim($_POST["product_id"]))) {
        $product_id_err = "Please select a product.";
    } else {
        $product_id = trim($_POST["product_id"]);
    }

    if (empty(trim($_POST["location_id"]))) {
        $location_id_err = "Please select a location.";
    } else {
        $location_id = trim($_POST["location_id"]);
    }

    if (empty(trim($_POST["quantity"])) || !is_numeric(trim($_POST["quantity"])) || $_POST["quantity"] <= 0) {
        $quantity_err = "Please enter a valid positive quantity.";
    } else {
        $quantity = trim($_POST["quantity"]);
    }

    if (empty(trim($_POST["unit"]))) {
        $unit_err = "Please enter the unit.";
    } else {
        $unit = trim($_POST["unit"]);
    }

    if (empty(trim($_POST["stage"]))) {
        $stage_err = "Please select a stage.";
    } else {
        $stage = trim($_POST["stage"]);
    }

    if (empty($product_id_err) && empty($location_id_err) && empty($quantity_err) && empty($unit_err) && empty($stage_err)) {
        $operation = $_POST['operation']; // 'add' or 'remove'
        $logged_in_user_id = $_SESSION['user_id']; // Capture logged-in user's ID

        // Check if inventory record already exists for this product, location, and stage
        $sql_check = "SELECT inventory_id, quantity FROM inventory WHERE product_id = ? AND location_id = ? AND stage = ?";
        if ($stmt_check = mysqli_prepare($conn, $sql_check)) {
            mysqli_stmt_bind_param($stmt_check, "iis", $param_product_id, $param_location_id, $param_stage);
            $param_product_id = $product_id;
            $param_location_id = $location_id;
            $param_stage = $stage;

            if (mysqli_stmt_execute($stmt_check)) {
                $result_check = mysqli_stmt_get_result($stmt_check);
                if (mysqli_num_rows($result_check) > 0) {
                    $row_check = mysqli_fetch_assoc($result_check);
                    $existing_inventory_id = $row_check['inventory_id'];
                    $current_quantity = $row_check['quantity'];

                    // Update existing record
                    if ($operation == 'add') {
                        $new_quantity = $current_quantity + $quantity;
                    } else { // 'remove'
                        $new_quantity = $current_quantity - $quantity;
                        if ($new_quantity < 0) {
                            $quantity_err = "Quantity to remove (" . $quantity . ") exceeds current stock (" . $current_quantity . ").";
                        }
                    }

                    if (empty($quantity_err)) {
                        // FIX: Update 'updated_at' and 'updated_by'
                        $sql_update = "UPDATE inventory SET quantity = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ? WHERE inventory_id = ?";
                        if ($stmt_update = mysqli_prepare($conn, $sql_update)) {
                            mysqli_stmt_bind_param($stmt_update, "dii", $param_new_quantity, $param_updated_by, $param_inventory_id);
                            $param_new_quantity = $new_quantity;
                            $param_updated_by = $logged_in_user_id; // Capture logged-in user's ID
                            $param_inventory_id = $existing_inventory_id;

                            if (mysqli_stmt_execute($stmt_update)) {
                                $_SESSION['success_message'] = "Stock updated successfully!";
                                header("location: " . BASE_URL . "inventory/index.php");
                                exit();
                            } else {
                                $_SESSION['error_message'] = "Error updating stock: " . mysqli_error($conn);
                                error_log("Error updating stock: " . mysqli_error($conn));
                            }
                            mysqli_stmt_close($stmt_update);
                        } else {
                            $_SESSION['error_message'] = "Error preparing update statement: " . mysqli_error($conn);
                            error_log("Error preparing stock update statement: " . mysqli_error($conn));
                        }
                    }

                } else {
                    // Insert new record if not found (only if operation is 'add')
                    if ($operation == 'add') {
                        // FIX: Add 'created_at' and 'created_by' to the INSERT statement
                        $sql_insert = "INSERT INTO inventory (product_id, location_id, quantity, unit, stage, created_at, created_by) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)";
                        if ($stmt_insert = mysqli_prepare($conn, $sql_insert)) {
                            // FIX: Add 'i' for created_by and type 'i' for user_id
                            mysqli_stmt_bind_param($stmt_insert, "iidssi", $param_product_id, $param_location_id, $param_quantity, $param_unit, $param_stage, $param_created_by);
                            $param_product_id = $product_id;
                            $param_location_id = $location_id;
                            $param_quantity = $quantity;
                            $param_unit = $unit;
                            $param_stage = $stage;
                            $param_created_by = $logged_in_user_id; // Capture logged-in user's ID

                            if (mysqli_stmt_execute($stmt_insert)) {
                                $_SESSION['success_message'] = "New inventory record added successfully!";
                                header("location: " . BASE_URL . "inventory/index.php");
                                exit();
                            } else {
                                $_SESSION['error_message'] = "Error adding new inventory: " . mysqli_error($conn);
                                error_log("Error adding new inventory: " . mysqli_error($conn));
                            }
                            mysqli_stmt_close($stmt_insert);
                        } else {
                            $_SESSION['error_message'] = "Error preparing insert statement: " . mysqli_error($conn);
                            error_log("Error preparing new inventory insert statement: " . mysqli_error($conn));
                        }
                    } else { // Trying to remove from non-existent stock
                        $quantity_err = "Cannot remove stock for a product, location, and stage that does not exist. Use 'Add to Stock' first.";
                    }
                }
            } else {
                $_SESSION['error_message'] = "Error checking existing inventory: " . mysqli_error($conn);
                error_log("Error checking inventory: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_check);
        } else {
            $_SESSION['error_message'] = "Error preparing inventory check statement: " . mysqli_error($conn);
            error_log("Error preparing inventory check statement: " . mysqli_error($conn));
        }
    }
    // mysqli_close($conn) is in footer.php
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Add/Update Stock</h2>
        <a href="<?php echo BASE_URL; ?>inventory/index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Inventory List</a>

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
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="product_id" class="form-select <?php echo (!empty($product_id_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Product</option>
                            <?php foreach ($products_options as $product): ?>
                                <option value="<?php echo htmlspecialchars($product['product_id']); ?>" <?php echo ($product_id == $product['product_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['product_name']); ?> (<?php echo htmlspecialchars($product['packaging_details']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $product_id_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="location_id" class="form-label">Location <span class="text-danger">*</span></label>
                        <select name="location_id" id="location_id" class="form-select <?php echo (!empty($location_id_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Location</option>
                            <?php foreach ($locations_options as $location): ?>
                                <option value="<?php echo htmlspecialchars($location['location_id']); ?>" <?php echo ($location_id == $location['location_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $location_id_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="quantity" class="form-control <?php echo (!empty($quantity_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($quantity); ?>" step="0.01">
                        <div class="invalid-feedback"><?php echo $quantity_err; ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="unit" class="form-label">Unit <span class="text-danger">*</span></label>
                        <input type="text" name="unit" id="unit" class="form-control <?php echo (!empty($unit_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($unit); ?>" placeholder="e.g., kg, units, crates">
                        <div class="invalid-feedback"><?php echo $unit_err; ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="stage" class="form-label">Stage <span class="text-danger">*</span></label>
                        <select name="stage" id="stage" class="form-select <?php echo (!empty($stage_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Stage</option>
                            <option value="post-harvest" <?php echo ($stage == 'post-harvest') ? 'selected' : ''; ?>>Post-Harvest</option>
                            <option value="processing" <?php echo ($stage == 'processing') ? 'selected' : ''; ?>>Processing</option>
                            <option value="storage" <?php echo ($stage == 'storage') ? 'selected' : ''; ?>>Storage</option>
                            <option value="in-transit" <?php echo ($stage == 'in-transit') ? 'selected' : ''; ?>>In-Transit</option>
                            <option value="damaged" <?php echo ($stage == 'damaged') ? 'selected' : ''; ?>>Damaged</option>
                            <option value="sold" <?php echo ($stage == 'sold') ? 'selected' : ''; ?>>Sold</option>
                        </select>
                        <div class="invalid-feedback"><?php echo $stage_err; ?></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Operation Type:</label><br>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="operation" id="operationAdd" value="add" checked>
                        <label class="form-check-label" for="operationAdd">Add to Stock</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="operation" id="operationRemove" value="remove">
                        <label class="form-check-label" for="operationRemove">Remove from Stock</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Inventory</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>