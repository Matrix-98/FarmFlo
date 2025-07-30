<?php
require_once '../config/db.php';

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

$name = $address = $type = $latitude = $longitude = "";
$name_err = $address_err = $type_err = $latitude_err = $longitude_err = "";

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

    if (empty($name_err) && empty($address_err) && empty($type_err) && empty($latitude_err) && empty($longitude_err)) {
        // FIX: Add 'created_by' to the INSERT statement
        $sql = "INSERT INTO locations (name, address, type, latitude, longitude, created_by) VALUES (?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            // FIX: Add 'i' for created_by and the $param_created_by variable
            mysqli_stmt_bind_param($stmt, "sssddi", $param_name, $param_address, $param_type, $param_latitude, $param_longitude, $param_created_by);

            $param_name = $name;
            $param_address = $address;
            $param_type = $type;
            $param_latitude = $latitude;
            $param_longitude = $longitude;
            $param_created_by = $_SESSION['user_id']; // Capture logged-in user's ID

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Location added successfully!";
                header("location: " . BASE_URL . "locations/index.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error: Could not add location. " . mysqli_error($conn);
                error_log("Error adding location: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Error preparing insert statement: " . mysqli_error($conn);
            error_log("Error preparing location insert statement: " . mysqli_error($conn));
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
                    <input type="text" name="name" id="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($name); ?>">
                    <div class="invalid-feedback"><?php echo $name_err; ?></div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                    <textarea name="address" id="address" class="form-control <?php echo (!empty($address_err)) ? 'is-invalid' : ''; ?>" rows="3"><?php echo htmlspecialchars($address); ?></textarea>
                    <div class="invalid-feedback"><?php echo $address_err; ?></div>
                </div>

                <div class="mb-3">
                    <label for="type" class="form-label">Location Type <span class="text-danger">*</span></label>
                    <select name="type" id="type" class="form-select <?php echo (!empty($type_err)) ? 'is-invalid' : ''; ?>">
                        <option value="">Select Type</option>
                        <option value="farm" <?php echo ($type == 'farm') ? 'selected' : ''; ?>>Farm</option>
                        <option value="warehouse" <?php echo ($type == 'warehouse') ? 'selected' : ''; ?>>Warehouse</option>
                        <option value="processing_plant" <?php echo ($type == 'processing_plant') ? 'selected' : ''; ?>>Processing Plant</option>
                        <option value="delivery_point" <?php echo ($type == 'delivery_point') ? 'selected' : ''; ?>>Delivery Point</option>
                        <option value="other" <?php echo ($type == 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                    <div class="invalid-feedback"><?php echo $type_err; ?></div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="latitude" class="form-label">Latitude <span class="text-danger">*</span></label>
                        <input type="text" name="latitude" id="latitude" class="form-control <?php echo (!empty($latitude_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($latitude); ?>">
                        <div class="invalid-feedback"><?php echo $latitude_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="longitude" class="form-label">Longitude <span class="text-danger">*</span></label>
                        <input type="text" name="longitude" id="longitude" class="form-control <?php echo (!empty($longitude_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($longitude); ?>">
                        <div class="invalid-feedback"><?php echo $longitude_err; ?></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Location</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>