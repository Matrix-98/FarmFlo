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

$page_title = "Edit Farm Production";
$current_page = "farm_production";

// Get production ID from URL
$production_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($production_id <= 0) {
    $_SESSION['error_message'] = "Invalid production ID.";
    header("location: " . BASE_URL . "farm_production/");
    exit;
}

// Initialize variables
$farm_manager_id = $product_id = $seed_amount_kg = $sowing_date = $field_name = "";
$expected_harvest_date = $actual_harvest_date = $harvested_amount_kg = $status = $notes = "";
$farm_manager_id_err = $product_id_err = $seed_amount_kg_err = $sowing_date_err = $field_name_err = "";
$expected_harvest_date_err = $actual_harvest_date_err = $harvested_amount_kg_err = $status_err = "";

// Get existing production data
$production = null;
$sql = "SELECT * FROM farm_production WHERE production_id = ?";

// Add farm manager filter if user is a farm manager
if ($_SESSION['role'] == 'farm_manager') {
    $sql .= " AND farm_manager_id = ?";
}

if ($stmt = mysqli_prepare($conn, $sql)) {
    if ($_SESSION['role'] == 'farm_manager') {
        mysqli_stmt_bind_param($stmt, "ii", $production_id, $_SESSION['user_id']);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $production_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $production = mysqli_fetch_assoc($result);

        // Populate form fields with existing data
        $farm_manager_id = $production['farm_manager_id'];
        $product_id = $production['product_id'];
        $seed_amount_kg = $production['seed_amount_kg'];
        $sowing_date = $production['sowing_date'];
        $field_name = $production['field_name'];
        $expected_harvest_date = $production['expected_harvest_date'];
        $actual_harvest_date = $production['actual_harvest_date'];
        $harvested_amount_kg = $production['harvested_amount_kg'];
        $status = $production['status'];
        $notes = $production['notes'];
    } else {
        $_SESSION['error_message'] = "Production record not found or you don't have permission to edit it.";
        header("location: " . BASE_URL . "farm_production/");
        exit;
    }
    mysqli_stmt_close($stmt);
} else {
    $_SESSION['error_message'] = "Error retrieving production details.";
    header("location: " . BASE_URL . "farm_production/");
    exit;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate Farm Manager
    if (empty(trim($_POST["farm_manager_id"]))) {
        $farm_manager_id_err = "Please select a farm manager.";
    } else {
        $farm_manager_id = trim($_POST["farm_manager_id"]);
    }

    // Validate Product
    if (empty(trim($_POST["product_id"]))) {
        $product_id_err = "Please select a product.";
    } else {
        $product_id = trim($_POST["product_id"]);
    }

    // Validate Seed Amount
    if (empty(trim($_POST["seed_amount_kg"]))) {
        $seed_amount_kg_err = "Please enter seed amount.";
    } elseif (!is_numeric($_POST["seed_amount_kg"]) || $_POST["seed_amount_kg"] <= 0) {
        $seed_amount_kg_err = "Please enter a valid seed amount.";
    } else {
        $seed_amount_kg = trim($_POST["seed_amount_kg"]);
    }

    // Validate Sowing Date
    if (empty(trim($_POST["sowing_date"]))) {
        $sowing_date_err = "Please enter sowing date.";
    } else {
        $sowing_date = trim($_POST["sowing_date"]);
    }

    // Validate Field Name
    if (empty(trim($_POST["field_name"]))) {
        $field_name_err = "Please enter field name.";
    } else {
        $field_name = trim($_POST["field_name"]);
    }

    // Validate Expected Harvest Date (optional)
    if (!empty(trim($_POST["expected_harvest_date"]))) {
        $expected_harvest_date = trim($_POST["expected_harvest_date"]);
        if (strtotime($expected_harvest_date) <= strtotime($sowing_date)) {
            $expected_harvest_date_err = "Expected harvest date must be after sowing date.";
        }
    } else {
        $expected_harvest_date = null;
    }

    // Validate Actual Harvest Date (optional)
    if (!empty(trim($_POST["actual_harvest_date"]))) {
        $actual_harvest_date = trim($_POST["actual_harvest_date"]);
        if (strtotime($actual_harvest_date) < strtotime($sowing_date)) {
            $actual_harvest_date_err = "Actual harvest date must be after sowing date.";
        }
    } else {
        $actual_harvest_date = null;
    }

    // Validate Harvested Amount (optional)
    if (!empty(trim($_POST["harvested_amount_kg"]))) {
        if (!is_numeric($_POST["harvested_amount_kg"]) || $_POST["harvested_amount_kg"] < 0) {
            $harvested_amount_kg_err = "Please enter a valid harvested amount.";
        } else {
            $harvested_amount_kg = trim($_POST["harvested_amount_kg"]);
        }
    } else {
        $harvested_amount_kg = null;
    }

    // Validate Status
    $allowed_statuses = ['planted', 'growing', 'ready_for_harvest', 'harvested', 'completed'];
    if (empty(trim($_POST["status"])) || !in_array(trim($_POST["status"]), $allowed_statuses)) {
        $status_err = "Please select a valid status.";
    } else {
        $status = trim($_POST["status"]);
    }

    // Validate Notes (optional)
    $notes = trim($_POST["notes"]);

    // If no errors, update the record
    if (empty($farm_manager_id_err) && empty($product_id_err) && empty($seed_amount_kg_err) &&
            empty($sowing_date_err) && empty($field_name_err) && empty($expected_harvest_date_err) &&
            empty($actual_harvest_date_err) && empty($harvested_amount_kg_err) && empty($status_err)) {

        $sql = "UPDATE farm_production SET 
                farm_manager_id = ?, product_id = ?, seed_amount_kg = ?, sowing_date = ?, 
                field_name = ?, expected_harvest_date = ?, actual_harvest_date = ?, 
                harvested_amount_kg = ?, status = ?, notes = ?, updated_by = ?
                WHERE production_id = ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "iidssssdssii",
                    $farm_manager_id, $product_id, $seed_amount_kg, $sowing_date,
                    $field_name, $expected_harvest_date, $actual_harvest_date,
                    $harvested_amount_kg, $status, $notes, $_SESSION['user_id'], $production_id);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Farm production record updated successfully.";
                header("location: " . BASE_URL . "farm_production/view.php?id=" . $production_id);
                exit;
            } else {
                $_SESSION['error_message'] = "Error updating production record: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Error preparing update statement.";
        }
    }
}

// Get farm managers for dropdown
$farm_managers = [];
$sql_farm_managers = "SELECT user_id, username, email FROM users WHERE role = 'farm_manager' ORDER BY username";
$result_farm_managers = mysqli_query($conn, $sql_farm_managers);
if ($result_farm_managers) {
    while ($row = mysqli_fetch_assoc($result_farm_managers)) {
        $farm_managers[] = $row;
    }
}

// Get products for dropdown
$products = [];
$sql_products = "SELECT product_id, name as product_name, item_type as crop_type FROM products ORDER BY name";
$result_products = mysqli_query($conn, $sql_products);
if ($result_products) {
    while ($row = mysqli_fetch_assoc($result_products)) {
        $products[] = $row;
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
            <h2><i class="fas fa-edit me-2"></i>Edit Farm Production</h2>
            <div>
                <a href="<?php echo BASE_URL; ?>farm_production/" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i>Back to List
                </a>
                <a href="view.php?id=<?php echo $production_id; ?>" class="btn btn-info">
                    <i class="fas fa-eye me-1"></i>View Details
                </a>
            </div>
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
        ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Production Record #<?php echo $production_id; ?></h5>
            </div>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $production_id); ?>" method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <?php if ($_SESSION['role'] != 'farm_manager'): ?>
                                <div class="mb-3">
                                    <label for="farm_manager_id" class="form-label">Farm Manager <span class="text-danger">*</span></label>
                                    <select name="farm_manager_id" id="farm_manager_id" class="form-select <?php echo (!empty($farm_manager_id_err)) ? 'is-invalid' : ''; ?>">
                                        <option value="">Select Farm Manager</option>
                                        <?php foreach ($farm_managers as $manager): ?>
                                            <option value="<?php echo $manager['user_id']; ?>"
                                                    <?php echo ($farm_manager_id == $manager['user_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($manager['username']); ?>
                                                (<?php echo htmlspecialchars($manager['email']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback"><?php echo $farm_manager_id_err; ?></div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="farm_manager_id" value="<?php echo $_SESSION['user_id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                                <select name="product_id" id="product_id" class="form-select <?php echo (!empty($product_id_err)) ? 'is-invalid' : ''; ?>">
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['product_id']; ?>"
                                                <?php echo ($product_id == $product['product_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                            (<?php echo htmlspecialchars($product['crop_type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback"><?php echo $product_id_err; ?></div>
                            </div>

                            <div class="mb-3">
                                <label for="seed_amount_kg" class="form-label">Seed Amount (kg) <span class="text-danger">*</span></label>
                                <input type="number" name="seed_amount_kg" id="seed_amount_kg" step="0.01" min="0.01"
                                       class="form-control <?php echo (!empty($seed_amount_kg_err)) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo $seed_amount_kg; ?>">
                                <div class="invalid-feedback"><?php echo $seed_amount_kg_err; ?></div>
                            </div>

                            <div class="mb-3">
                                <label for="sowing_date" class="form-label">Sowing Date <span class="text-danger">*</span></label>
                                <input type="date" name="sowing_date" id="sowing_date"
                                       class="form-control <?php echo (!empty($sowing_date_err)) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo $sowing_date; ?>">
                                <div class="invalid-feedback"><?php echo $sowing_date_err; ?></div>
                            </div>

                            <div class="mb-3">
                                <label for="field_name" class="form-label">Field Name <span class="text-danger">*</span></label>
                                <input type="text" name="field_name" id="field_name"
                                       class="form-control <?php echo (!empty($field_name_err)) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($field_name); ?>">
                                <div class="invalid-feedback"><?php echo $field_name_err; ?></div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="expected_harvest_date" class="form-label">Expected Harvest Date</label>
                                <input type="date" name="expected_harvest_date" id="expected_harvest_date"
                                       class="form-control <?php echo (!empty($expected_harvest_date_err)) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo $expected_harvest_date; ?>">
                                <div class="invalid-feedback"><?php echo $expected_harvest_date_err; ?></div>
                            </div>

                            <div class="mb-3">
                                <label for="actual_harvest_date" class="form-label">Actual Harvest Date</label>
                                <input type="date" name="actual_harvest_date" id="actual_harvest_date"
                                       class="form-control <?php echo (!empty($actual_harvest_date_err)) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo $actual_harvest_date; ?>">
                                <div class="invalid-feedback"><?php echo $actual_harvest_date_err; ?></div>
                            </div>

                            <div class="mb-3">
                                <label for="harvested_amount_kg" class="form-label">Harvested Amount (kg)</label>
                                <input type="number" name="harvested_amount_kg" id="harvested_amount_kg" step="0.01" min="0"
                                       class="form-control <?php echo (!empty($harvested_amount_kg_err)) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo $harvested_amount_kg; ?>">
                                <div class="invalid-feedback"><?php echo $harvested_amount_kg_err; ?></div>
                            </div>

                            <div class="mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" id="status" class="form-select <?php echo (!empty($status_err)) ? 'is-invalid' : ''; ?>">
                                    <option value="">Select Status</option>
                                    <option value="planted" <?php echo ($status == 'planted') ? 'selected' : ''; ?>>Planted</option>
                                    <option value="growing" <?php echo ($status == 'growing') ? 'selected' : ''; ?>>Growing</option>
                                    <option value="ready_for_harvest" <?php echo ($status == 'ready_for_harvest') ? 'selected' : ''; ?>>Ready for Harvest</option>
                                    <option value="harvested" <?php echo ($status == 'harvested') ? 'selected' : ''; ?>>Harvested</option>
                                    <option value="completed" <?php echo ($status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                </select>
                                <div class="invalid-feedback"><?php echo $status_err; ?></div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea name="notes" id="notes" rows="4" class="form-control"><?php echo htmlspecialchars($notes); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?php echo BASE_URL; ?>farm_production/" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update Production
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Add validation for harvest dates
    document.getElementById('sowing_date').addEventListener('change', function() {
        const sowingDate = this.value;
        const expectedHarvestDate = document.getElementById('expected_harvest_date');
        const actualHarvestDate = document.getElementById('actual_harvest_date');

        if (expectedHarvestDate.value && sowingDate && expectedHarvestDate.value <= sowingDate) {
            expectedHarvestDate.setCustomValidity('Expected harvest date must be after sowing date');
        } else {
            expectedHarvestDate.setCustomValidity('');
        }

        if (actualHarvestDate.value && sowingDate && actualHarvestDate.value < sowingDate) {
            actualHarvestDate.setCustomValidity('Actual harvest date must be after sowing date');
        } else {
            actualHarvestDate.setCustomValidity('');
        }
    });

    document.getElementById('expected_harvest_date').addEventListener('change', function() {
        const sowingDate = document.getElementById('sowing_date').value;
        if (sowingDate && this.value <= sowingDate) {
            this.setCustomValidity('Expected harvest date must be after sowing date');
        } else {
            this.setCustomValidity('');
        }
    });

    document.getElementById('actual_harvest_date').addEventListener('change', function() {
        const sowingDate = document.getElementById('sowing_date').value;
        if (sowingDate && this.value < sowingDate) {
            this.setCustomValidity('Actual harvest date must be after sowing date');
        } else {
            this.setCustomValidity('');
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
