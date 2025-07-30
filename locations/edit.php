<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to edit locations.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Edit Location";
$current_page = "locations";

$location_id = $name = $address = $type = $latitude = $longitude = "";
$name_err = $address_err = $type_err = $latitude_err = $longitude_err = "";

// Initialize audit trail variables for display
$created_at = $updated_at = $created_by_username = $updated_by_username = '';


// Fetch existing location data if ID is provided in GET request
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $location_id = trim($_GET["id"]);

    // FIX: Include created_at, updated_at, created_by, updated_by in the SELECT query
    $sql_fetch_location = "SELECT name, address, type, latitude, longitude, created_at, updated_at, created_by, updated_by FROM locations WHERE location_id = ?";
    if ($stmt_fetch = mysqli_prepare($conn, $sql_fetch_location)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $param_id);
        $param_id = $location_id;

        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);

            if (mysqli_num_rows($result_fetch) == 1) {
                $row = mysqli_fetch_assoc($result_fetch);
                $name = $row["name"];
                $address = $row["address"];
                $type = $row["type"];
                $latitude = $row["latitude"];
                $longitude = $row["longitude"];
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
                $_SESSION['error_message'] = "Location not found.";
                header("location: " . BASE_URL . "locations/index.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "Oops! Something went wrong fetching location data. Please try again later.";
            error_log("Error executing location fetch: " . mysqli_error($conn));
            header("location: " . BASE_URL . "locations/index.php");
            exit();
        }
        mysqli_stmt_close($stmt_fetch);
    } else {
        $_SESSION['error_message'] = "Error preparing location fetch statement. Please try again later.";
        error_log("Error preparing location fetch statement: " . mysqli_error($conn));
        header("location: " . BASE_URL . "locations/index.php");
        exit();
    }
} else if ($_SERVER["REQUEST_METHOD"] != "POST") { // Redirect if no ID provided in GET, and not a POST request
    $_SESSION['error_message'] = "Invalid request. No location ID provided.";
    header("location: " . BASE_URL . "locations/index.php");
    exit();
}


// Process form data when form is submitted (when data is posted back to this page)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $location_id = $_POST["location_id"]; // Get ID from hidden field

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
        // FIX: Add 'updated_by' to the UPDATE statement
        // Assumes 'updated_at' is handled by DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP in DB
        $sql = "UPDATE locations SET name = ?, address = ?, type = ?, latitude = ?, longitude = ?, updated_by = ? WHERE location_id = ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            // FIX: Add 'i' for updated_by and the $param_updated_by variable
            mysqli_stmt_bind_param($stmt, "sssddii", $param_name, $param_address, $param_type, $param_latitude, $param_longitude, $param_updated_by, $param_id);

            $param_name = $name;
            $param_address = $address;
            $param_type = $type;
            $param_latitude = $latitude;
            $param_longitude = $longitude;
            $param_updated_by = $_SESSION['user_id']; // Capture logged-in user's ID
            $param_id = $location_id;

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Location updated successfully!";
                header("location: " . BASE_URL . "locations/index.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error: Could not update location. " . mysqli_error($conn);
                error_log("Error updating location: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['error_message'] = "Error preparing update statement: " . mysqli_error($conn);
            error_log("Error preparing location update statement: " . mysqli_error($conn));
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
        <h2 class="mb-4">Edit Location</h2>
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
                <input type="hidden" name="location_id" value="<?php echo htmlspecialchars($location_id); ?>">

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

                <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Update Location</button>
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