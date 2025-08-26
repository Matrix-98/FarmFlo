<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'farm_manager') {
    $_SESSION['error_message'] = "You do not have permission to edit products.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Edit Product";
$current_page = "products";

$product_id = $product_code = $name = $item_type = $batch_id = $packaging_details = $description = $price_per_unit = "";
$name_err = $item_type_err = $price_per_unit_err = "";

// Initialize audit trail variables for display
$created_at = $updated_at = $created_by_username = $updated_by_username = '';

// Fetch existing product data if ID is provided in GET request
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $product_id = trim($_GET["id"]);

    $sql_fetch_product = "SELECT product_code, name, item_type, batch_id, packaging_details, description, price_per_unit, created_at, updated_at, created_by, updated_by FROM products WHERE product_id = ?";
    if ($stmt_fetch = mysqli_prepare($conn, $sql_fetch_product)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $param_id);
        $param_id = $product_id;

        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);

            if (mysqli_num_rows($result_fetch) == 1) {
                $row = mysqli_fetch_assoc($result_fetch);
                $product_code = $row["product_code"];
                $name = $row["name"];
                $item_type = $row["item_type"];
                $batch_id = $row["batch_id"];
                $packaging_details = $row["packaging_details"];
                $description = $row["description"];
                $price_per_unit = $row["price_per_unit"];
                
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
                $_SESSION['error_message'] = "Product not found.";
                header("location: " . BASE_URL . "products/index.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "Oops! Something went wrong fetching product data. Please try again later.";
            error_log("Error executing product fetch: " . mysqli_error($conn));
            header("location: " . BASE_URL . "products/index.php");
            exit();
        }
        mysqli_stmt_close($stmt_fetch);
    } else {
        $_SESSION['error_message'] = "Error preparing product fetch statement. Please try again later.";
        error_log("Error preparing product fetch statement: " . mysqli_error($conn));
        header("location: " . BASE_URL . "products/index.php");
        exit();
    }
} else if ($_SERVER["REQUEST_METHOD"] != "POST") { // Redirect if no ID provided in GET, and not a POST request
    $_SESSION['error_message'] = "Invalid request. No product ID provided.";
    header("location: " . BASE_URL . "products/index.php");
    exit();
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $product_id = $_POST["product_id"]; // Get ID from hidden field

    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter the product name.";
    } else {
        $name = trim($_POST["name"]);
    }

    if (empty(trim($_POST["item_type"]))) {
        $item_type_err = "Please enter the item type.";
    } else {
        $item_type = trim($_POST["item_type"]);
    }

    if (empty(trim($_POST["price_per_unit"]))) {
        $price_per_unit_err = "Please enter the price per unit.";
    } elseif (!is_numeric(trim($_POST["price_per_unit"])) || trim($_POST["price_per_unit"]) < 0) {
        $price_per_unit_err = "Price must be a valid non-negative number.";
    } else {
        $price_per_unit = trim($_POST["price_per_unit"]);
    }

    $batch_id = trim($_POST["batch_id"]);
    $packaging_details = trim($_POST["packaging_details"]);
    $description = trim($_POST["description"]);

    if (empty($name_err) && empty($item_type_err) && empty($price_per_unit_err)) {
        $sql = "UPDATE products SET name = ?, item_type = ?, batch_id = ?, packaging_details = ?, description = ?, price_per_unit = ?, updated_by = ? WHERE product_id = ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssdii", $param_name, $param_item_type, $param_batch_id, $param_packaging_details, $param_description, $param_price_per_unit, $param_updated_by, $param_id);

            $param_name = $name;
            $param_item_type = $item_type;
            $param_batch_id = $batch_id;
            $param_packaging_details = $packaging_details;
            $param_description = $description;
            $param_price_per_unit = $price_per_unit;
            $param_updated_by = $_SESSION['user_id'];
            $param_id = $product_id;

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Product updated successfully!";
                header("location: " . BASE_URL . "products/index.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error: Could not update product. " . mysqli_error($conn);
                error_log("Error updating product: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Error preparing update statement: " . mysqli_error($conn);
            error_log("Error preparing product update statement: " . mysqli_error($conn));
        }
    }
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Edit Product</h2>
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
                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id); ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="product_code" class="form-label">Product Code</label>
                        <input type="text" name="product_code" id="product_code" class="form-control" value="<?php echo htmlspecialchars($product_code); ?>" readonly>
                        <small class="form-text text-muted">Product code cannot be changed</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($name); ?>">
                        <div class="invalid-feedback"><?php echo $name_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="item_type" class="form-label">Item Type <span class="text-danger">*</span></label>
                        <input type="text" name="item_type" id="item_type" class="form-control <?php echo (!empty($item_type_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($item_type); ?>" placeholder="e.g., Vegetables, Fruits, Grains">
                        <div class="invalid-feedback"><?php echo $item_type_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="price_per_unit" class="form-label">Price per Unit (৳) <span class="text-danger">*</span></label>
                        <input type="number" name="price_per_unit" id="price_per_unit" class="form-control <?php echo (!empty($price_per_unit_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($price_per_unit); ?>" step="0.01">
                        <div class="invalid-feedback"><?php echo $price_per_unit_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="batch_id" class="form-label">Batch ID</label>
                        <input type="text" name="batch_id" id="batch_id" class="form-control" value="<?php echo htmlspecialchars($batch_id); ?>" placeholder="e.g., BATCH001, HARVEST2025">
                        <small class="form-text text-muted">Optional batch identifier</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="packaging_details" class="form-label">Packaging Details</label>
                        <input type="text" name="packaging_details" id="packaging_details" class="form-control" value="<?php echo htmlspecialchars($packaging_details); ?>">
                        <small class="form-text text-muted">e.g., "5kg crates", "20kg bags"</small>
                    </div>
                </div>



                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea name="description" id="description" class="form-control" rows="4"><?php echo htmlspecialchars($description); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Update Product</button>
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