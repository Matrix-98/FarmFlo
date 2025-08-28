<?php
require_once '../config/db.php';
require_once '../utils/id_generator.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check if user has permission to create products
if (!in_array($_SESSION["role"], ['admin', 'farm_manager'])) {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Create Product";
$current_page = "products";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $item_type = trim($_POST["item_type"]);
    $batch_id = trim($_POST["batch_id"]);
    $price_per_unit = trim($_POST["price_per_unit"]);
    $packaging_details = trim($_POST["packaging_details"]);
    $description = trim($_POST["description"]);
    
    // Validate inputs
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Product name is required.";
    }
    
    if (empty($item_type)) {
        $errors[] = "Item type is required.";
    }
    
    if (empty($price_per_unit) || $price_per_unit <= 0) {
        $errors[] = "Price must be greater than 0.";
    }
    
    if (empty($packaging_details)) {
        $errors[] = "Packaging details are required.";
    }
    
    if (empty($errors)) {
        // Generate product code
        $product_code = generateProductId();
        
        // Insert product
        $sql = "INSERT INTO products (product_code, name, item_type, batch_id, price_per_unit, packaging_details, description, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssssdssi", $product_code, $name, $item_type, $batch_id, $price_per_unit, $packaging_details, $description, $_SESSION["user_id"]);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Product created successfully with code: " . $product_code;
                header("location: " . BASE_URL . "products/");
                exit;
            } else {
                $errors[] = "Something went wrong. Please try again later.";
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
            <h2><i class="fas fa-plus me-2"></i>Create Product</h2>
            <a href="<?php echo BASE_URL; ?>products/" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Products
            </a>
        </div>

        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
            unset($_SESSION['error_message']);
        }
        if (!empty($errors)) {
            echo '<div class="alert alert-danger"><ul class="mb-0">';
            foreach ($errors as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul></div>';
        }
        ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-seedling me-2"></i>Product Details</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Product Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="item_type" class="form-label">Item Type *</label>
                                    <input type="text" class="form-control" id="item_type" name="item_type" value="<?php echo isset($_POST['item_type']) ? htmlspecialchars($_POST['item_type']) : ''; ?>" placeholder="e.g., Vegetables, Fruits, Grains" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="batch_id" class="form-label">Batch ID</label>
                                    <input type="text" class="form-control" id="batch_id" name="batch_id" value="<?php echo isset($_POST['batch_id']) ? htmlspecialchars($_POST['batch_id']) : ''; ?>" placeholder="e.g., BATCH001, HARVEST2025">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="price_per_unit" class="form-label">Price per Unit (৳) *</label>
                                    <input type="number" step="0.01" class="form-control" id="price_per_unit" name="price_per_unit" value="<?php echo isset($_POST['price_per_unit']) ? htmlspecialchars($_POST['price_per_unit']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="packaging_details" class="form-label">Packaging Details *</label>
                                    <input type="text" class="form-control" id="packaging_details" name="packaging_details" value="<?php echo isset($_POST['packaging_details']) ? htmlspecialchars($_POST['packaging_details']) : ''; ?>" placeholder="e.g., 1kg bags, 500g containers" required>
                                </div>
                            </div>
                            

                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Create Product
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Information</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            <strong>Product Code:</strong> Will be automatically generated (e.g., P25001)<br><br>
                            <strong>Item Type Examples:</strong><br>
                            • <strong>Vegetables:</strong> Leafy greens, root vegetables<br>
                            • <strong>Fruits:</strong> Seasonal fruits, berries<br>
                            • <strong>Grains:</strong> Rice, wheat, corn<br>
                            • <strong>Legumes:</strong> Beans, lentils, peas<br>
                            • <strong>Herbs:</strong> Fresh herbs, spices<br>
                            • <strong>Other:</strong> Miscellaneous products
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>