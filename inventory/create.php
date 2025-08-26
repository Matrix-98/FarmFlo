<?php
require_once '../config/db.php';
require_once '../utils/inventory_helpers.php';
require_once '../utils/id_generator.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if (!in_array($_SESSION["role"], ['admin', 'farm_manager', 'warehouse_manager'])) {
    $_SESSION['error_message'] = "You do not have permission to access Inventory Management.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Add Inventory";
$current_page = "inventory";

// Initialize variables
$product_id = $location_id = $quantity_kg = $stage = $expiry_date = "";
$product_id_err = $location_id_err = $quantity_err = $stage_err = $expiry_date_err = "";

// Fetch products for dropdown
$products_options = [];
$sql_products = "SELECT product_id, name as product_name, packaging_details FROM products ORDER BY name ASC";
$result_products = mysqli_query($conn, $sql_products);
if ($result_products) {
    while ($row = mysqli_fetch_assoc($result_products)) {
        $products_options[] = $row;
    }
} else {
    error_log("Products query failed: " . mysqli_error($conn));
}

// Fetch locations for dropdown (filtered by user role)
$locations_options = [];
$logged_in_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

if ($user_role == 'warehouse_manager') {
    // Fetch only locations assigned to this warehouse manager
    $sql_locations = "SELECT l.location_id, l.name, l.type, l.capacity_kg, l.capacity_m3
                      FROM locations l
                      JOIN user_assigned_locations ual ON l.location_id = ual.location_id
                      WHERE ual.user_id = ? AND l.type = 'warehouse'
                      ORDER BY l.name ASC";
    if ($stmt_locations = mysqli_prepare($conn, $sql_locations)) {
        mysqli_stmt_bind_param($stmt_locations, "i", $logged_in_user_id);
        mysqli_stmt_execute($stmt_locations);
        $result_locations = mysqli_stmt_get_result($stmt_locations);
        while ($row = mysqli_fetch_assoc($result_locations)) {
            $locations_options[] = $row;
        }
        mysqli_stmt_close($stmt_locations);
    }
} else {
    // Admin and farm managers can see all locations
    $sql_locations = "SELECT location_id, name, type, capacity_kg, capacity_m3 FROM locations ORDER BY name ASC";
    $result_locations = mysqli_query($conn, $sql_locations);
    if ($result_locations) {
        while ($row = mysqli_fetch_assoc($result_locations)) {
            $locations_options[] = $row;
        }
    } else {
        error_log("Locations query failed: " . mysqli_error($conn));
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate product_id
    if (empty(trim($_POST["product_id"]))) {
        $product_id_err = "Please select a product.";
    } else {
        $product_id = trim($_POST["product_id"]);
    }

    // Validate location_id
    if (empty(trim($_POST["location_id"]))) {
        $location_id_err = "Please select a location.";
    } else {
        $location_id = trim($_POST["location_id"]);
    }

    // Validate quantity
    if (empty(trim($_POST["quantity_kg"]))) {
        $quantity_err = "Please enter quantity in kg.";
    } elseif (!is_numeric(trim($_POST["quantity_kg"])) || trim($_POST["quantity_kg"]) <= 0) {
        $quantity_err = "Please enter a valid positive number.";
    } else {
        $quantity_kg = trim($_POST["quantity_kg"]);
    }

    // Validate stage
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

    // Check input errors before inserting
    if (empty($product_id_err) && empty($location_id_err) && empty($quantity_err) && empty($stage_err) && empty($expiry_date_err)) {
        // Check capacity before inserting
        $capacity_check = checkLocationCapacity($location_id, $quantity_kg);

        if (!$capacity_check['has_capacity']) {
            $_SESSION['error_message'] = "Location capacity exceeded. Available: " . number_format($capacity_check['available_weight'], 2) . " kg";
        } else {
            // Generate inventory code
            $inventory_code = generateInventoryId();

            // Insert new inventory record
            $sql = "INSERT INTO inventory (inventory_code, product_id, location_id, quantity_kg, stage, expiry_date, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "siidssi", $inventory_code, $product_id, $location_id, $quantity_kg, $stage, $expiry_date, $_SESSION["user_id"]);

                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "Inventory added successfully with code: " . $inventory_code;
                    header("location: " . BASE_URL . "inventory/index.php");
                    exit;
                } else {
                    $_SESSION['error_message'] = "Something went wrong. Please try again later.";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

include '../includes/head.php';
?>

    <!-- Sidebar -->
<?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="content">
        <!-- Navbar -->
        <?php include '../includes/navbar.php'; ?>

        <!-- Page Content -->
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2>Add New Inventory</h2>
                    <p class="text-muted mb-0">Add new inventory records to your agricultural storage system.</p>
                </div>
                <a href="<?php echo BASE_URL; ?>inventory/" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Inventory
                </a>
            </div>

            <!-- Success/Error Messages -->
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

            <!-- Add Inventory Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add Inventory Record</h5>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="inventoryForm">
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
                                            <?php echo htmlspecialchars($location['name']); ?> (<?php echo ucfirst($location['type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback"><?php echo $location_id_err; ?></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="quantity_kg" class="form-label">Quantity (kg) <span class="text-danger">*</span></label>
                                <input type="number" name="quantity_kg" id="quantity_kg" class="form-control <?php echo (!empty($quantity_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($quantity_kg); ?>" step="0.01" min="0.01" required>
                                <div class="invalid-feedback"><?php echo $quantity_err; ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="stage" class="form-label">Stage <span class="text-danger">*</span></label>
                                <select name="stage" id="stage" class="form-select <?php echo (!empty($stage_err)) ? 'is-invalid' : ''; ?>" required>
                                    <option value="">Select Stage</option>
                                    <option value="available" <?php echo ($stage == 'available') ? 'selected' : ''; ?>>Available</option>
                                    <option value="sold" <?php echo ($stage == 'sold') ? 'selected' : ''; ?>>Sold</option>
                                    <option value="lost" <?php echo ($stage == 'lost') ? 'selected' : ''; ?>>Lost</option>
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

                        <!-- Capacity Information -->
                        <div id="capacityInfo" class="alert alert-info" style="display: none;">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle me-2"></i>
                                <div>
                                    <strong>Capacity Information:</strong>
                                    <div id="capacityDetails"></div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="<?php echo BASE_URL; ?>inventory/" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Add Inventory
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Real-time Capacity Validation JavaScript -->
    <script>
        // Store product and location data for calculations
        const productsData = <?php echo json_encode($products_options); ?>;
        const locationsData = <?php echo json_encode($locations_options); ?>;

        // Function to check capacity in real-time
        function checkCapacity() {
            const productId = document.getElementById('product_id').value;
            const locationId = document.getElementById('location_id').value;
            const quantity = parseFloat(document.getElementById('quantity_kg').value) || 0;

            if (productId && locationId && quantity > 0) {
                // Find location data
                const location = locationsData.find(loc => loc.location_id == locationId);

                if (location && (location.capacity_kg > 0 || location.capacity_m3 > 0)) {
                    // Calculate estimated volume (1kg ≈ 0.001 m³)
                    const estimatedVolume = quantity * 0.001;

                    // Show capacity info
                    const capacityInfo = document.getElementById('capacityInfo');
                    const capacityDetails = document.getElementById('capacityDetails');

                    let details = '';
                    if (location.capacity_kg > 0) {
                        const weightPercent = (quantity / location.capacity_kg) * 100;
                        details += `<div>Weight: ${quantity.toFixed(2)} kg / ${location.capacity_kg} kg (${weightPercent.toFixed(1)}%)</div>`;
                    }
                    if (location.capacity_m3 > 0) {
                        const volumePercent = (estimatedVolume / location.capacity_m3) * 100;
                        details += `<div>Volume: ${estimatedVolume.toFixed(3)} m³ / ${location.capacity_m3} m³ (${volumePercent.toFixed(1)}%)</div>`;
                    }

                    capacityDetails.innerHTML = details;
                    capacityInfo.style.display = 'block';

                    // Change alert color based on capacity
                    if (quantity > location.capacity_kg || estimatedVolume > location.capacity_m3) {
                        capacityInfo.className = 'alert alert-danger';
                    } else if ((quantity / location.capacity_kg) > 0.8 || (estimatedVolume / location.capacity_m3) > 0.8) {
                        capacityInfo.className = 'alert alert-warning';
                    } else {
                        capacityInfo.className = 'alert alert-info';
                    }
                } else {
                    document.getElementById('capacityInfo').style.display = 'none';
                }
            } else {
                document.getElementById('capacityInfo').style.display = 'none';
            }
        }

        // Add event listeners
        document.getElementById('product_id').addEventListener('change', checkCapacity);
        document.getElementById('location_id').addEventListener('change', checkCapacity);
        document.getElementById('quantity_kg').addEventListener('input', checkCapacity);

        // Initial check
        document.addEventListener('DOMContentLoaded', function() {
            checkCapacity();
        });
    </script>

<?php include '../includes/footer.php'; ?>