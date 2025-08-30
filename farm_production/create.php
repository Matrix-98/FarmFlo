<?php
require_once '../config/db.php';
require_once '../utils/id_generator.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check user role for access control
if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'farm_manager') {
    $_SESSION['error_message'] = "You do not have permission to access Farm Production Management.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Add New Farm Production";
$current_page = "farm_production";

// Initialize variables
$product_id = $seed_amount_kg = $sowing_date = $field_name = $expected_harvest_date = $notes = "";
$product_id_err = $seed_amount_kg_err = $sowing_date_err = $field_name_err = "";

// Get available products
$products = [];
    $sql_products = "SELECT product_id, name as product_name, item_type as crop_type FROM products ORDER BY name";
if ($result = mysqli_query($conn, $sql_products)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
    mysqli_free_result($result);
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate product
    if (empty(trim($_POST["product_id"]))) {
        $product_id_err = "Please select a product.";
    } else {
        $product_id = trim($_POST["product_id"]);
    }
    
    // Validate seed amount
    if (empty(trim($_POST["seed_amount_kg"]))) {
        $seed_amount_kg_err = "Please enter seed amount.";
    } elseif (!is_numeric(trim($_POST["seed_amount_kg"])) || trim($_POST["seed_amount_kg"]) <= 0) {
        $seed_amount_kg_err = "Please enter a valid positive number.";
    } else {
        $seed_amount_kg = trim($_POST["seed_amount_kg"]);
    }
    
    // Validate sowing date
    if (empty(trim($_POST["sowing_date"]))) {
        $sowing_date_err = "Please enter sowing date.";
    } else {
        $sowing_date = trim($_POST["sowing_date"]);
    }
    
    // Validate field name
    if (empty(trim($_POST["field_name"]))) {
        $field_name_err = "Please enter field name.";
    } else {
        $field_name = trim($_POST["field_name"]);
    }
    
    // Optional fields
    $expected_harvest_date = !empty(trim($_POST["expected_harvest_date"])) ? trim($_POST["expected_harvest_date"]) : null;
    $notes = !empty(trim($_POST["notes"])) ? trim($_POST["notes"]) : null;
    
    // Check for errors before inserting
    if (empty($product_id_err) && empty($seed_amount_kg_err) && empty($sowing_date_err) && empty($field_name_err)) {
        
        $sql = "INSERT INTO farm_production (production_code, farm_manager_id, product_id, seed_amount_kg, sowing_date, field_name, expected_harvest_date, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'planted', ?, ?)";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Generate unique production code
            $production_code = generateUniqueFarmProductionId();
            
            mysqli_stmt_bind_param($stmt, "siidssssi", $param_production_code, $param_farm_manager_id, $param_product_id, $param_seed_amount_kg, $param_sowing_date, $param_field_name, $param_expected_harvest_date, $param_notes, $param_created_by);
            
            $param_production_code = $production_code;
            $param_farm_manager_id = $_SESSION['user_id'];
            $param_product_id = $product_id;
            $param_seed_amount_kg = $seed_amount_kg;
            $param_sowing_date = $sowing_date;
            $param_field_name = $field_name;
            $param_expected_harvest_date = $expected_harvest_date;
            $param_notes = $notes;
            $param_created_by = $_SESSION['user_id'];
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Farm production record created successfully!";
                header("location: " . BASE_URL . "farm_production/index.php");
                exit;
            } else {
                $error_msg = mysqli_error($conn);
                $_SESSION['error_message'] = "Error creating farm production: " . $error_msg;
            }
            
            mysqli_stmt_close($stmt);
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
                <h2>Add New Farm Production</h2>
                <p class="text-muted mb-0">Create a new farm production record to track agricultural activities.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>farm_production/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>

        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Production Details</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                                    <select name="product_id" id="product_id" class="form-select <?php echo (!empty($product_id_err)) ? 'is-invalid' : ''; ?>">
                                        <option value="">Select Product</option>
                                        <?php foreach ($products as $product): ?>
                                            <option value="<?php echo $product['product_id']; ?>" <?php echo ($product_id == $product['product_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($product['product_name']); ?> (<?php echo htmlspecialchars($product['crop_type']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback"><?php echo $product_id_err; ?></div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="seed_amount_kg" class="form-label">Seed Amount (kg) <span class="text-danger">*</span></label>
                                    <input type="number" name="seed_amount_kg" id="seed_amount_kg" class="form-control <?php echo (!empty($seed_amount_kg_err)) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo $seed_amount_kg; ?>" step="0.01" min="0.01" placeholder="Enter seed amount">
                                    <div class="invalid-feedback"><?php echo $seed_amount_kg_err; ?></div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="sowing_date" class="form-label">Sowing Date <span class="text-danger">*</span></label>
                                    <input type="date" name="sowing_date" id="sowing_date" class="form-control <?php echo (!empty($sowing_date_err)) ? 'is-invalid' : ''; ?>" 
                                           value="<?php echo $sowing_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                                    <div class="invalid-feedback"><?php echo $sowing_date_err; ?></div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="expected_harvest_date" class="form-label">Expected Harvest Date</label>
                                    <input type="date" name="expected_harvest_date" id="expected_harvest_date" class="form-control" 
                                           value="<?php echo $expected_harvest_date; ?>" min="<?php echo date('Y-m-d'); ?>">
                                    <small class="form-text text-muted">Optional: Set expected harvest date</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="field_name" class="form-label">Field Name <span class="text-danger">*</span></label>
                                <input type="text" name="field_name" id="field_name" class="form-control <?php echo (!empty($field_name_err)) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo $field_name; ?>" placeholder="Enter field name or location">
                                <div class="invalid-feedback"><?php echo $field_name_err; ?></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Additional notes about this production..."><?php echo $notes; ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="<?php echo BASE_URL; ?>farm_production/index.php" class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Create Production Record
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Production Guidelines</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb me-2"></i>Tips for Better Production Tracking:</h6>
                            <ul class="mb-0 mt-2">
                                <li>Set realistic expected harvest dates</li>
                                <li>Use descriptive field names for easy identification</li>
                                <li>Record detailed notes for future reference</li>
                                <li>Monitor production status regularly</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notes:</h6>
                            <ul class="mb-0 mt-2">
                                <li>Sowing date cannot be in the future</li>
                                <li>Expected harvest date should be after sowing date</li>
                                <li>Seed amount must be a positive number</li>
                                <li>Production status will start as 'Planted'</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-calculate expected harvest date based on product type
document.getElementById('product_id').addEventListener('change', function() {
    const productId = this.value;
    const sowingDate = document.getElementById('sowing_date').value;
    
    if (productId && sowingDate) {
        // You can add logic here to calculate expected harvest date based on crop type
        // For now, we'll set a default of 90 days from sowing
        const sowing = new Date(sowingDate);
        const expectedHarvest = new Date(sowing.getTime() + (90 * 24 * 60 * 60 * 1000));
        document.getElementById('expected_harvest_date').value = expectedHarvest.toISOString().split('T')[0];
    }
});

// Validate sowing date
document.getElementById('sowing_date').addEventListener('change', function() {
    const sowingDate = this.value;
    const today = new Date().toISOString().split('T')[0];
    
    if (sowingDate > today) {
        alert('Sowing date cannot be in the future!');
        this.value = today;
    }
});
</script>

<?php include '../includes/footer.php'; ?>