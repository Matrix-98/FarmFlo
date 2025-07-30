<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'farm_manager' && $_SESSION["role"] != 'warehouse_manager') {
    $_SESSION['error_message'] = "You do not have permission to edit inventory records.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Edit Inventory Record";
$current_page = "inventory";

$inventory_id = $product_id = $location_id = $quantity = $unit = $stage = "";
$product_id_err = $location_id_err = $quantity_err = $unit_err = $stage_err = "";

// Initialize audit trail variables for display
$created_at = $updated_at = $created_by_username = $updated_by_username = '';

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

// Fetch existing record data
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $inventory_id = trim($_GET["id"]);

    // FIX: Include created_at, updated_at (renamed from last_updated), created_by, updated_by in the SELECT query
    $sql_fetch_record = "SELECT product_id, location_id, quantity, unit, stage, created_at, updated_at, created_by, updated_by FROM inventory WHERE inventory_id = ?";
    if ($stmt_fetch = mysqli_prepare($conn, $sql_fetch_record)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $param_id);
        $param_id = $inventory_id;

        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);

            if (mysqli_num_rows($result_fetch) == 1) {
                $row = mysqli_fetch_assoc($result_fetch);
                $product_id = $row["product_id"];
                $location_id = $row["location_id"];
                $quantity = $row["quantity"];
                $unit = $row["unit"];
                $stage = $row["stage"];
                // FIX: Capture audit data for display
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
                $_SESSION['error_message'] = "Inventory record not found.";
                header("location: " . BASE_URL . "inventory/index.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "Oops! Something went wrong fetching inventory data. Please try again later.";
            error_log("Error executing inventory fetch: " . mysqli_error($conn));
            header("location: " . BASE_URL . "inventory/index.php");
            exit();
        }
        mysqli_stmt_close($stmt_fetch);
    } else {
        $_SESSION['error_message'] = "Error preparing inventory fetch statement. Please try again later.";
        error_log("Error preparing inventory fetch statement: " . mysqli_error($conn));
        header("location: " . BASE_URL . "inventory/index.php");
        exit();
    }
} else if ($_SERVER["REQUEST_METHOD"] != "POST") { // Redirect if no ID provided in GET, and not a POST request
    $_SESSION['error_message'] = "Invalid request. No inventory ID provided.";
    header("location: " . BASE_URL . "inventory/index.php");
    exit();
}


// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $inventory_id = $_POST["inventory_id"];

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

    if (empty(trim($_POST["quantity"])) || !is_numeric(trim($_POST["quantity"])) || $_POST["quantity"] < 0) { // Allow 0 for now to clear stock
        $quantity_err = "Please enter a valid non-negative quantity.";
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
        $logged_in_user_id = $_SESSION['user_id']; // Capture logged-in user's ID
        // FIX: Update 'updated_at' and 'updated_by'
        $sql = "UPDATE inventory SET product_id = ?, location_id = ?, quantity = ?, unit = ?, stage = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ? WHERE inventory_id = ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "iidssii", $param_product_id, $param_location_id, $param_quantity, $param_unit, $param_stage, $param_updated_by, $param_inventory_id);

            $param_product_id = $product_id;
            $param_location_id = $location_id;
            $param_quantity = $quantity;
            $param_unit = $unit;
            $param_stage = $stage;
            $param_updated_by = $logged_in_user_id; // Capture logged-in user's ID
            $param_inventory_id = $inventory_id;

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Inventory record updated successfully!";
                header("location: " . BASE_URL . "inventory/index.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error: Could not update inventory record. " . mysqli_error($conn);
                error_log("Error updating inventory: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Error preparing update statement: " . mysqli_error($conn);
            error_log("Error preparing inventory update statement: " . mysqli_error($conn));
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
        <h2 class="mb-4">Edit Inventory Record</h2>
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
                <input type="hidden" name="inventory_id" value="<?php echo htmlspecialchars($inventory_id); ?>">

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
                    <div class="col-md-6 mb-3">
                        <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="quantity" class="form-control <?php echo (!empty($quantity_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($quantity); ?>" step="0.01">
                        <div class="invalid-feedback"><?php echo $quantity_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="unit" class="form-label">Unit <span class="text-danger">*</span></label>
                        <input type="text" name="unit" id="unit" class="form-control <?php echo (!empty($unit_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($unit); ?>" placeholder="e.g., kg, units, crates">
                        <div class="invalid-feedback"><?php echo $unit_err; ?></div>
                    </div>
                </div>

                <div class="mb-3">
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

                <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Update Inventory</button>
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