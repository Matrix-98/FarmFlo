<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'farm_manager') {
    $_SESSION['error_message'] = "You do not have permission to add products.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Add Product";
$current_page = "products";

$crop_type = $product_name = $planting_date = $harvest_date = $storage_requirements = $shelf_life_days = $packaging_details = $description = $price_per_unit = $batch_id = ""; // Added $batch_id
$crop_type_err = $product_name_err = $shelf_life_days_err = $price_per_unit_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (empty(trim($_POST["crop_type"]))) {
        $crop_type_err = "Please enter the crop type.";
    } else {
        $crop_type = trim($_POST["crop_type"]);
    }

    if (empty(trim($_POST["product_name"]))) {
        $product_name_err = "Please enter the product name.";
    } else {
        $product_name = trim($_POST["product_name"]);
    }

    if (empty(trim($_POST["shelf_life_days"]))) {
        $shelf_life_days_err = "Please enter the shelf life in days.";
    } elseif (!filter_var(trim($_POST["shelf_life_days"]), FILTER_VALIDATE_INT)) {
        $shelf_life_days_err = "Shelf life must be an integer.";
    } else {
        $shelf_life_days = trim($_POST["shelf_life_days"]);
    }

    if (empty(trim($_POST["price_per_unit"]))) {
        $price_per_unit_err = "Please enter the price per unit.";
    } elseif (!is_numeric(trim($_POST["price_per_unit"])) || trim($_POST["price_per_unit"]) < 0) {
        $price_per_unit_err = "Price must be a valid non-negative number.";
    } else {
        $price_per_unit = trim($_POST["price_per_unit"]);
    }

    $planting_date = !empty($_POST["planting_date"]) ? $_POST["planting_date"] : NULL;
    $harvest_date = !empty($_POST["harvest_date"]) ? $_POST["harvest_date"] : NULL;
    $storage_requirements = trim($_POST["storage_requirements"]);
    $packaging_details = trim($_POST["packaging_details"]);
    $description = trim($_POST["description"]);
    $batch_id = trim($_POST["batch_id"]); // NEW: Capture batch_id

    if (empty($crop_type_err) && empty($product_name_err) && empty($shelf_life_days_err) && empty($price_per_unit_err)) {
        $sql = "INSERT INTO products (crop_type, product_name, planting_date, harvest_date, storage_requirements, shelf_life_days, packaging_details, description, price_per_unit, batch_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssissdsi", $param_crop_type, $param_product_name, $param_planting_date, $param_harvest_date, $param_storage_requirements, $param_shelf_life_days, $param_packaging_details, $param_description, $param_price_per_unit, $param_batch_id, $param_created_by);

            $param_crop_type = $crop_type;
            $param_product_name = $product_name;
            $param_planting_date = $planting_date;
            $param_harvest_date = $harvest_date;
            $param_storage_requirements = $storage_requirements;
            $param_shelf_life_days = $shelf_life_days;
            $param_packaging_details = $packaging_details;
            $param_description = $description;
            $param_price_per_unit = $price_per_unit;
            $param_batch_id = $batch_id; // NEW
            $param_created_by = $_SESSION['user_id'];

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Product added successfully!";
                header("location: " . BASE_URL . "products/index.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error: Could not add product. " . mysqli_error($conn);
                error_log("Error adding product: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Error preparing insert statement: " . mysqli_error($conn);
            error_log("Error preparing product insert statement: " . mysqli_error($conn));
        }
    }
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Add New Product</h2>
        <a href="<?php echo BASE_URL; ?>products/index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Product List</a>

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
                        <label for="crop_type" class="form-label">Crop Type <span class="text-danger">*</span></label>
                        <input type="text" name="crop_type" id="crop_type" class="form-control <?php echo (!empty($crop_type_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($crop_type); ?>">
                        <div class="invalid-feedback"><?php echo $crop_type_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="product_name" class="form-label">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="product_name" id="product_name" class="form-control <?php echo (!empty($product_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($product_name); ?>">
                        <div class="invalid-feedback"><?php echo $product_name_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="planting_date" class="form-label">Planting Date</label>
                        <input type="date" name="planting_date" id="planting_date" class="form-control" value="<?php echo htmlspecialchars($planting_date); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="harvest_date" class="form-label">Harvest Date</label>
                        <input type="date" name="harvest_date" id="harvest_date" class="form-control" value="<?php echo htmlspecialchars($harvest_date); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="storage_requirements" class="form-label">Storage Requirements</label>
                    <textarea name="storage_requirements" id="storage_requirements" class="form-control" rows="3"><?php echo htmlspecialchars($storage_requirements); ?></textarea>
                    <small class="form-text text-muted">e.g., "Temp: 0-4C, Humidity: 90% RH"</small>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="shelf_life_days" class="form-label">Shelf Life (Days) <span class="text-danger">*</span></label>
                        <input type="number" name="shelf_life_days" id="shelf_life_days" class="form-control <?php echo (!empty($shelf_life_days_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($shelf_life_days); ?>">
                        <div class="invalid-feedback"><?php echo $shelf_life_days_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="packaging_details" class="form-label">Packaging Details</label>
                        <input type="text" name="packaging_details" id="packaging_details" class="form-control" value="<?php echo htmlspecialchars($packaging_details); ?>">
                        <small class="form-text text-muted">e.g., "5kg crates", "20kg bags"</small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="price_per_unit" class="form-label">Price per Unit (in Taka) <span class="text-danger">*</span></label>
                    <input type="number" name="price_per_unit" id="price_per_unit" class="form-control <?php echo (!empty($price_per_unit_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($price_per_unit); ?>" step="0.01">
                    <div class="invalid-feedback"><?php echo $price_per_unit_err; ?></div>
                </div>
                
                <div class="mb-3">
                    <label for="batch_id" class="form-label">Batch ID</label>
                    <input type="text" name="batch_id" id="batch_id" class="form-control" value="<?php echo htmlspecialchars($batch_id); ?>" placeholder="e.g., TOM-101-2023">
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea name="description" id="description" class="form-control" rows="4"><?php echo htmlspecialchars($description); ?></textarea>
                </div>

                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Product</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>