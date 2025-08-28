<?php
require_once '../config/db.php';
require_once '../utils/inventory_helpers.php';

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

$inventory_id = $product_id = $location_id = $quantity_kg = $stage = $expiry_date = "";
$product_id_err = $location_id_err = $quantity_err = $stage_err = $expiry_date_err = "";

// Initialize audit trail variables for display
$created_at = $updated_at = $created_by_username = $updated_by_username = '';

// Get logged-in user details
$logged_in_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$allowed_location_ids = [];

// Fetch allowed locations for the user's role
if ($user_role == 'warehouse_manager') {
    $sql_assigned_locations = "SELECT location_id FROM user_assigned_locations WHERE user_id = ?";
    if ($stmt_assigned_loc = mysqli_prepare($conn, $sql_assigned_locations)) {
        mysqli_stmt_bind_param($stmt_assigned_loc, "i", $logged_in_user_id);
        mysqli_stmt_execute($stmt_assigned_loc);
        $result_assigned_loc = mysqli_stmt_get_result($stmt_assigned_loc);
        while ($row = mysqli_fetch_assoc($result_assigned_loc)) {
            $allowed_location_ids[] = $row['location_id'];
        }
        mysqli_stmt_close($stmt_assigned_loc);
    }
}

// Fetch products for dropdown (with weight and volume for calculations)
$products_options = [];
$sql_products = "SELECT product_id, name as product_name, packaging_details FROM products ORDER BY name ASC";
if ($result_products = mysqli_query($conn, $sql_products)) {
    while ($row = mysqli_fetch_assoc($result_products)) {
        $products_options[] = $row;
    }
    mysqli_free_result($result_products);
}

// Fetch locations for dropdown (with capacity for validation)
$locations_options = [];
$sql_locations = "SELECT location_id, name, type, capacity_kg, capacity_m3 FROM locations ORDER BY name ASC";
if ($result_locations = mysqli_query($conn, $sql_locations)) {
    while ($row = mysqli_fetch_assoc($result_locations)) {
        if ($user_role != 'warehouse_manager' || in_array($row['location_id'], $allowed_location_ids)) {
            $locations_options[] = $row;
        }
    }
    mysqli_free_result($result_locations);
}

// Fetch existing record data
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $inventory_id = trim($_GET["id"]);

    $sql_fetch_record = "SELECT product_id, location_id, quantity_kg, stage, expiry_date, created_at, updated_at, created_by, updated_by FROM inventory WHERE inventory_id = ?";
    if ($stmt_fetch = mysqli_prepare($conn, $sql_fetch_record)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $param_id);
        $param_id = $inventory_id;

        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);

            if (mysqli_num_rows($result_fetch) == 1) {
                $row = mysqli_fetch_assoc($result_fetch);
                $product_id = $row["product_id"];
                $location_id = $row["location_id"];
                $quantity_kg = $row["quantity_kg"];
                $stage = $row["stage"];
                $expiry_date = $row["expiry_date"];
                $created_at = $row["created_at"];
                $updated_at = $row["updated_at"];
                $created_by_id = $row["created_by"];
                $updated_by_id = $row["updated_by"];

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

    if (empty(trim($_POST["quantity_kg"])) || !is_numeric(trim($_POST["quantity_kg"])) || $_POST["quantity_kg"] < 0) {
        $quantity_err = "Please enter a valid non-negative quantity in kg.";
    } else {
        $quantity_kg = trim($_POST["quantity_kg"]);
    }

    if (empty(trim($_POST["stage"]))) {
        $stage_err = "Please select a stage.";
    } else {
        $stage = trim($_POST["stage"]);
    }

    // Validate expiry date (required)
    if (empty(trim($_POST["expiry_date"]))) {
        $expiry_date_err = "Please enter expiry date.";
    } else {
        $expiry_date = trim($_POST["expiry_date"]);
        // Validate date format
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $expiry_date)) {
            $expiry_date_err = "Please enter a valid date in YYYY-MM-DD format.";
        }
    }

    // NEW: Enhanced capacity validation using utility functions
    if (!empty($location_id) && !empty($product_id) && !empty($quantity_kg)) {
        $capacity_validation = validateInventoryCapacity($conn, $location_id, $product_id, $quantity_kg, 'update', $inventory_id);
        if (!$capacity_validation['valid']) {
            $quantity_err = $capacity_validation['message'];
        }
    }

    if (empty($product_id_err) && empty($location_id_err) && empty($quantity_err) && empty($stage_err) && empty($expiry_date_err)) {
        $logged_in_user_id = $_SESSION['user_id'];
        $sql = "UPDATE inventory SET product_id = ?, location_id = ?, quantity_kg = ?, stage = ?, expiry_date = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ? WHERE inventory_id = ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "iidssii", $param_product_id, $param_location_id, $param_quantity_kg, $param_stage, $param_expiry_date, $param_updated_by, $param_inventory_id);

            $param_product_id = $product_id;
            $param_location_id = $location_id;
            $param_quantity_kg = $quantity_kg;
            $param_stage = $stage;
            $param_expiry_date = $expiry_date;
            $param_updated_by = $logged_in_user_id;
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
                <input type="hidden" name="original_quantity" value="<?php echo htmlspecialchars($quantity_kg); ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="product_id" class="form-select <?php echo (!empty($product_id_err)) ? 'is-invalid' : ''; ?>" required>
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
                        <select name="location_id" id="location_id" class="form-select <?php echo (!empty($location_id_err)) ? 'is-invalid' : ''; ?>" required>
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
                        <label for="quantity_kg" class="form-label">Quantity (kg) <span class="text-danger">*</span></label>
                        <input type="number" name="quantity_kg" id="quantity_kg" class="form-control <?php echo (!empty($quantity_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($quantity_kg); ?>" step="0.01" required>
                        <div class="invalid-feedback"><?php echo $quantity_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="stage" class="form-label">Stage <span class="text-danger">*</span></label>
                        <select name="stage" id="stage" class="form-select <?php echo (!empty($stage_err)) ? 'is-invalid' : ''; ?>" required>
                            <option value="">Select Stage</option>
                            <option value="available" <?php echo ($stage == 'available') ? 'selected' : ''; ?>>Available</option>
                            <option value="in-transit" <?php echo ($stage == 'in-transit') ? 'selected' : ''; ?>>In-Transit</option>
                            <option value="sold" <?php echo ($stage == 'sold') ? 'selected' : ''; ?>>Sold</option>
                            <option value="lost" <?php echo ($stage == 'lost') ? 'selected' : ''; ?>>Lost</option>
                            <option value="damaged" <?php echo ($stage == 'damaged') ? 'selected' : ''; ?>>Damaged</option>
                        </select>
                        <div class="invalid-feedback"><?php echo $stage_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="expiry_date" class="form-label">Expiry Date <span class="text-danger">*</span></label>
                        <input type="date" name="expiry_date" id="expiry_date" class="form-control <?php echo (!empty($expiry_date_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($expiry_date); ?>" required>
                        <div class="invalid-feedback"><?php echo $expiry_date_err; ?></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Update Inventory</button>
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

<!-- NEW: Real-time Capacity Validation JavaScript -->
<script>
    // Store product and location data for calculations
    const productsData = <?php echo json_encode($products_options); ?>;
    const locationsData = <?php echo json_encode($locations_options); ?>;

    // Function to get product data by ID
    function getProductData(productId) {
        return productsData.find(product => product.product_id == productId);
    }

    // Function to get location data by ID
    function getLocationData(locationId) {
        return locationsData.find(location => location.location_id == locationId);
    }

    // Function to check capacity in real-time
    async function checkCapacity() {
        const productId = document.getElementById('product_id').value;
        const locationId = document.getElementById('location_id').value;
        const quantity = parseFloat(document.getElementById('quantity_kg').value) || 0;
        const operation = 'update'; // For edit, always use 'update' operation
        const existingInventoryId = <?php echo $inventory_id; ?>; // Current inventory ID being edited

        if (!productId || !locationId || quantity <= 0) {
            hideCapacityInfo();
            return;
        }

        const product = getProductData(productId);
        const location = getLocationData(locationId);

        if (!product || !location) {
            hideCapacityInfo();
            return;
        }

        // Only check capacity for warehouses
        if (location.type !== 'warehouse' || (!location.capacity_kg && !location.capacity_m3)) {
            hideCapacityInfo();
            return;
        }

        try {
            const response = await fetch('<?php echo BASE_URL; ?>utils/capacity_check.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    location_id: locationId,
                    product_id: productId,
                    quantity: quantity,
                    operation: operation,
                    existing_inventory_id: existingInventoryId
                })
            });

            const data = await response.json();
            showCapacityInfo(data, product, location);
        } catch (error) {
            console.error('Error checking capacity:', error);
            hideCapacityInfo();
        }
    }

    // Function to show capacity information
    function showCapacityInfo(data, product, location) {
        let capacityInfo = document.getElementById('capacity-info');
        if (!capacityInfo) {
            capacityInfo = document.createElement('div');
            capacityInfo.id = 'capacity-info';
            capacityInfo.className = 'alert mt-3';
            document.getElementById('quantity_kg').parentNode.appendChild(capacityInfo);
        }

        let html = '<h6><i class="fas fa-warehouse"></i> Warehouse Capacity Check</h6>';

        if (data.valid) {
            capacityInfo.className = 'alert alert-success mt-3';

            if (location.capacity_kg > 0) {
                const weightUsage = ((data.new_weight / location.capacity_kg) * 100).toFixed(1);
                const weightColor = weightUsage >= 90 ? 'danger' : weightUsage >= 80 ? 'warning' : 'success';
                html += `
                <div class="mb-2">
                    <small>Weight: ${data.current_weight.toFixed(2)} → ${data.new_weight.toFixed(2)} / ${location.capacity_kg} kg
                    <span class="badge bg-${weightColor}">${weightUsage}%</span></small>
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar bg-${weightColor}" style="width: ${Math.min(100, weightUsage)}%"></div>
                    </div>
                </div>
            `;
            }

            if (location.capacity_m3 > 0) {
                const volumeUsage = ((data.new_volume / location.capacity_m3) * 100).toFixed(1);
                const volumeColor = volumeUsage >= 90 ? 'danger' : volumeUsage >= 80 ? 'warning' : 'info';
                html += `
                <div class="mb-2">
                    <small>Volume: ${data.current_volume.toFixed(3)} → ${data.new_volume.toFixed(3)} / ${location.capacity_m3} m³
                    <span class="badge bg-${volumeColor}">${volumeUsage}%</span></small>
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar bg-${volumeColor}" style="width: ${Math.min(100, volumeUsage)}%"></div>
                    </div>
                </div>
            `;
            }

            html += '<small class="text-success"><i class="fas fa-check-circle"></i> Capacity check passed</small>';
        } else {
            capacityInfo.className = 'alert alert-danger mt-3';
            html += `<small class="text-danger"><i class="fas fa-exclamation-triangle"></i> ${data.message}</small>`;
        }

        capacityInfo.innerHTML = html;
    }

    // Function to hide capacity information
    function hideCapacityInfo() {
        const capacityInfo = document.getElementById('capacity-info');
        if (capacityInfo) {
            capacityInfo.remove();
        }
    }

    // Add event listeners
    document.addEventListener('DOMContentLoaded', function() {
        const productSelect = document.getElementById('product_id');
        const locationSelect = document.getElementById('location_id');
        const quantityInput = document.getElementById('quantity_kg');

        productSelect.addEventListener('change', checkCapacity);
        locationSelect.addEventListener('change', checkCapacity);
        quantityInput.addEventListener('input', checkCapacity);

        // Initial check on page load
        checkCapacity();
    });
</script>