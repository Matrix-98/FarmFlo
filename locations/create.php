<?php
require_once '../config/db.php';
require_once '../utils/id_generator.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to add locations.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Add Location";
$current_page = "locations";

$name = $address = $type = $latitude = $longitude = $capacity_kg = $capacity_m3 = "";
$name_err = $address_err = $type_err = $latitude_err = $longitude_err = $capacity_kg_err = $capacity_m3_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter a location name.";
    } else {
        $name = trim($_POST["name"]);
    }

    if (empty(trim($_POST["address"]))) {
        $address_err = "Please enter an address.";
    } else {
        $address = trim($_POST["address"]);
    }

    if (empty(trim($_POST["type"]))) {
        $type_err = "Please select a location type.";
    } else {
        $type = trim($_POST["type"]);
    }

    if (empty(trim($_POST["latitude"])) || !is_numeric(trim($_POST["latitude"]))) {
        $latitude_err = "Please enter a valid latitude.";
    } else {
        $latitude = trim($_POST["latitude"]);
    }

    if (empty(trim($_POST["longitude"])) || !is_numeric(trim($_POST["longitude"]))) {
        $longitude_err = "Please enter a valid longitude.";
    } else {
        $longitude = trim($_POST["longitude"]);
    }

    // NEW: Validate capacity only if location type is 'warehouse'
    if ($type == 'warehouse') {
        if (empty(trim($_POST["capacity_kg"])) || !is_numeric(trim($_POST["capacity_kg"])) || $_POST["capacity_kg"] < 0) {
            $capacity_kg_err = "Please enter a valid weight capacity for the warehouse.";
        } else {
            $capacity_kg = trim($_POST["capacity_kg"]);
        }

        if (empty(trim($_POST["capacity_m3"])) || !is_numeric(trim($_POST["capacity_m3"])) || $_POST["capacity_m3"] < 0) {
            $capacity_m3_err = "Please enter a valid volume capacity for the warehouse.";
        } else {
            $capacity_m3 = trim($_POST["capacity_m3"]);
        }
    }


    if (empty($name_err) && empty($address_err) && empty($type_err) && empty($latitude_err) && empty($longitude_err) && empty($capacity_kg_err) && empty($capacity_m3_err)) {
        $logged_in_user_id = $_SESSION['user_id'];

        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            // Insert location
            $sql = "INSERT INTO locations (location_code, name, address, type, latitude, longitude, capacity_kg, capacity_m3, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            if ($stmt = mysqli_prepare($conn, $sql)) {
                // Generate unique location code with retry mechanism
                $max_retries = 5;
                $location_code = null;

                for ($i = 0; $i < $max_retries; $i++) {
                    $location_code = generateLocationId();

                    // Check if this code already exists
                    $check_sql = "SELECT COUNT(*) as count FROM locations WHERE location_code = ?";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "s", $location_code);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $check_row = mysqli_fetch_assoc($check_result);
                    mysqli_stmt_close($check_stmt);

                    if ($check_row['count'] == 0) {
                        break; // Code is unique, proceed
                    }

                    // If we're on the last retry, generate a timestamp-based code
                    if ($i == $max_retries - 1) {
                        $location_code = 'L' . date('y') . str_pad(mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
                    }
                }

                mysqli_stmt_bind_param($stmt, "ssssddddi", $param_location_code, $param_name, $param_address, $param_type, $param_latitude, $param_longitude, $param_capacity_kg, $param_capacity_m3, $param_created_by);

                $param_location_code = $location_code;

                $param_name = $name;
                $param_address = $address;
                $param_type = $type;
                $param_latitude = $latitude;
                $param_longitude = $longitude;
                $param_capacity_kg = $capacity_kg;
                $param_capacity_m3 = $capacity_m3;
                $param_created_by = $logged_in_user_id;

                if (mysqli_stmt_execute($stmt)) {
                    $location_id = mysqli_insert_id($conn);

                    // If warehouse type and warehouse manager is selected, assign the manager
                    if ($type == 'warehouse' && !empty($_POST['warehouse_manager_id'])) {
                        $warehouse_manager_id = $_POST['warehouse_manager_id'];

                        $sql_assign = "INSERT INTO user_assigned_locations (user_id, location_id) VALUES (?, ?)";
                        if ($stmt_assign = mysqli_prepare($conn, $sql_assign)) {
                            mysqli_stmt_bind_param($stmt_assign, "ii", $warehouse_manager_id, $location_id);
                            mysqli_stmt_execute($stmt_assign);
                            mysqli_stmt_close($stmt_assign);
                        }
                    }

                    mysqli_commit($conn);
                    $_SESSION['success_message'] = "Location added successfully!" . ($type == 'warehouse' && !empty($_POST['warehouse_manager_id']) ? " Warehouse manager assigned." : "");
                    header("location: " . BASE_URL . "locations/index.php");
                    exit();
                } else {
                    throw new Exception("Error: Could not add location. " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt);
            } else {
                throw new Exception("Error preparing insert statement: " . mysqli_error($conn));
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error_message'] = $e->getMessage();
            error_log("Error adding location: " . $e->getMessage());
        }
    }
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Add New Location</h2>
        <a href="<?php echo BASE_URL; ?>locations/index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Location List</a>

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
                <div class="mb-3">
                    <label for="name" class="form-label">Location Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($name); ?>" required>
                    <div class="invalid-feedback"><?php echo $name_err; ?></div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                    <textarea name="address" id="address" class="form-control <?php echo (!empty($address_err)) ? 'is-invalid' : ''; ?>" rows="3" required><?php echo htmlspecialchars($address); ?></textarea>
                    <div class="invalid-feedback"><?php echo $address_err; ?></div>
                </div>

                <div class="mb-3">
                    <label for="type" class="form-label">Location Type <span class="text-danger">*</span></label>
                    <select name="type" id="type" class="form-select <?php echo (!empty($type_err)) ? 'is-invalid' : ''; ?>" required>
                        <option value="">Select Type</option>
                        <option value="farm" <?php echo ($type == 'farm') ? 'selected' : ''; ?>>Farm</option>
                        <option value="warehouse" <?php echo ($type == 'warehouse') ? 'selected' : ''; ?>>Warehouse</option>
                        <option value="processing_plant" <?php echo ($type == 'processing_plant') ? 'selected' : ''; ?>>Processing Plant</option>
                        <option value="delivery_point" <?php echo ($type == 'delivery_point') ? 'selected' : ''; ?>>Delivery Point</option>
                        <option value="other" <?php echo ($type == 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                    <div class="invalid-feedback"><?php echo $type_err; ?></div>
                </div>

                <div id="capacity_group" style="display: <?php echo ($type == 'warehouse') ? 'block' : 'none'; ?>;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="capacity_kg" class="form-label">Capacity (Weight in kg) <span class="text-danger">*</span></label>
                            <input type="number" name="capacity_kg" id="capacity_kg" class="form-control <?php echo (!empty($capacity_kg_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($capacity_kg); ?>" step="0.01">
                            <div class="invalid-feedback"><?php echo $capacity_kg_err; ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="capacity_m3" class="form-label">Capacity (Volume in m³) <span class="text-danger">*</span></label>
                            <input type="number" name="capacity_m3" id="capacity_m3" class="form-control <?php echo (!empty($capacity_m3_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($capacity_m3); ?>" step="0.01">
                            <div class="invalid-feedback"><?php echo $capacity_m3_err; ?></div>
                        </div>
                    </div>

                    <!-- Warehouse Manager Assignment -->
                    <div class="mb-3">
                        <label for="warehouse_manager_id" class="form-label">Assign Warehouse Manager</label>
                        <select name="warehouse_manager_id" id="warehouse_manager_id" class="form-select">
                            <option value="">Select Warehouse Manager (Optional)</option>
                            <?php
                            // Get available warehouse managers
                            $sql_managers = "SELECT u.user_id, u.username, u.email, u.phone 
                                           FROM users u 
                                           WHERE u.role = 'warehouse_manager' 
                                           ORDER BY u.username";
                            $result_managers = mysqli_query($conn, $sql_managers);
                            if ($result_managers) {
                                while ($manager = mysqli_fetch_assoc($result_managers)) {
                                    $selected = (isset($_POST['warehouse_manager_id']) && $_POST['warehouse_manager_id'] == $manager['user_id']) ? 'selected' : '';
                                    echo '<option value="' . $manager['user_id'] . '" ' . $selected . '>';
                                    echo htmlspecialchars($manager['username']) . ' (' . htmlspecialchars($manager['email']) . ')';
                                    echo '</option>';
                                }
                                mysqli_free_result($result_managers);
                            }
                            ?>
                        </select>
                        <small class="form-text text-muted">Select a warehouse manager to assign this warehouse to them. They will only be able to access this specific warehouse.</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="latitude" class="form-label">Latitude <span class="text-danger">*</span></label>
                        <input type="text" name="latitude" id="latitude" class="form-control <?php echo (!empty($latitude_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($latitude); ?>" required>
                        <div class="invalid-feedback"><?php echo $latitude_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="longitude" class="form-label">Longitude <span class="text-danger">*</span></label>
                        <input type="text" name="longitude" id="longitude" class="form-control <?php echo (!empty($longitude_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($longitude); ?>" required>
                        <div class="invalid-feedback"><?php echo $longitude_err; ?></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Location</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const typeSelect = document.getElementById('type');
        const capacityGroup = document.getElementById('capacity_group');
        const capacityKgInput = document.getElementById('capacity_kg');
        const capacityM3Input = document.getElementById('capacity_m3');

        function toggleCapacityFields() {
            if (typeSelect.value === 'warehouse') {
                capacityGroup.style.display = 'block';
                capacityKgInput.setAttribute('required', 'required');
                capacityM3Input.setAttribute('required', 'required');
            } else {
                capacityGroup.style.display = 'none';
                capacityKgInput.removeAttribute('required');
                capacityM3Input.removeAttribute('required');
            }
        }

        toggleCapacityFields();
        typeSelect.addEventListener('change', toggleCapacityFields);
    });
</script>