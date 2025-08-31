<?php
require_once '../config/db.php';
require_once '../utils/id_generator.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check if user has permission to create drivers
if (!in_array($_SESSION["role"], ['admin', 'logistics_manager'])) {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Create Driver";
$current_page = "drivers";

// Fetch available users with 'driver' role who are not yet linked to a driver profile
$available_users = [];
$sql_users = "SELECT u.user_id, u.username, u.email 
              FROM users u 
              LEFT JOIN drivers d ON u.user_id = d.user_id 
              WHERE u.role = 'driver' AND d.user_id IS NULL 
              ORDER BY u.username ASC";
if ($stmt_users = mysqli_prepare($conn, $sql_users)) {
    mysqli_stmt_execute($stmt_users);
    $result_users = mysqli_stmt_get_result($stmt_users);
    while ($row = mysqli_fetch_assoc($result_users)) {
        $available_users[] = $row;
    }
    mysqli_stmt_close($stmt_users);
} else {
    error_log("Error preparing available users query for drivers: " . mysqli_error($conn));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $phone_number = trim($_POST["phone_number"]);
    $email = trim($_POST["email"]);
    $license_number = trim($_POST["license_number"]);
    $vehicle_type = trim($_POST["vehicle_type"]);
    $experience_years = trim($_POST["experience_years"]);
    $status = trim($_POST["status"]);
    $user_id = !empty($_POST["user_id"]) ? trim($_POST["user_id"]) : null;
    
    // Validate inputs
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    
    if (empty($phone_number)) {
        $errors[] = "Phone number is required.";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($license_number)) {
        $errors[] = "License number is required.";
    }
    
    if (empty($vehicle_type)) {
        $errors[] = "Vehicle type is required.";
    }
    
    if (!empty($experience_years) && (!is_numeric($experience_years) || $experience_years < 0 || $experience_years > 50)) {
        $errors[] = "Experience years must be a number between 0 and 50.";
    }
    
    if (empty($status)) {
        $errors[] = "Status is required.";
    }
    
    // Check if user_id is already linked to another driver
    if (!empty($user_id)) {
        $check_sql = "SELECT driver_id FROM drivers WHERE user_id = ?";
        if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
            mysqli_stmt_bind_param($check_stmt, "i", $user_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            if (mysqli_num_rows($check_result) > 0) {
                $errors[] = "This user is already linked to another driver.";
            }
            mysqli_stmt_close($check_stmt);
        }
    }
    
    if (empty($errors)) {
        // Generate driver code
        $driver_code = generateDriverId();
        
        // Insert driver
        $sql = "INSERT INTO drivers (driver_code, first_name, last_name, phone_number, email, license_number, vehicle_type, experience_years, status, user_id, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssssssii", $driver_code, $first_name, $last_name, $phone_number, $email, $license_number, $vehicle_type, $experience_years, $status, $user_id, $_SESSION["user_id"]);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Driver created successfully with code: " . $driver_code;
                header("location: " . BASE_URL . "drivers/");
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
            <h2><i class="fas fa-plus me-2"></i>Create Driver</h2>
            <a href="<?php echo BASE_URL; ?>drivers/" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Drivers
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
                        <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Driver Details</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone_number" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="user_id" class="form-label">Link to User Account (Optional)</label>
                                    <select class="form-select" id="user_id" name="user_id">
                                        <option value="">No User Account</option>
                                        <?php foreach ($available_users as $user): ?>
                                            <option value="<?php echo htmlspecialchars($user['user_id']); ?>" <?php echo (isset($_POST['user_id']) && $_POST['user_id'] == $user['user_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Link this driver to an existing user account for system access.</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="license_number" class="form-label">License Number *</label>
                                    <input type="text" class="form-control" id="license_number" name="license_number" value="<?php echo isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="vehicle_type" class="form-label">Vehicle Type *</label>
                                    <select class="form-select" id="vehicle_type" name="vehicle_type" required>
                                        <option value="">Select Vehicle Type</option>
                                        <option value="truck" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'truck') ? 'selected' : ''; ?>>Truck</option>
                                        <option value="van" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'van') ? 'selected' : ''; ?>>Van</option>
                                        <option value="pickup" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'pickup') ? 'selected' : ''; ?>>Pickup</option>
                                        <option value="motorcycle" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'motorcycle') ? 'selected' : ''; ?>>Motorcycle</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="experience_years" class="form-label">Experience (Years)</label>
                                    <input type="number" class="form-control" id="experience_years" name="experience_years" value="<?php echo isset($_POST['experience_years']) ? htmlspecialchars($_POST['experience_years']) : ''; ?>" min="0" max="50">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="on_leave" <?php echo (isset($_POST['status']) && $_POST['status'] == 'on_leave') ? 'selected' : ''; ?>>On Leave</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Create Driver
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
                            <strong>Driver Code:</strong> Will be automatically generated (e.g., D25001)<br><br>
                            <strong>User Linking:</strong><br>
                            • Link to existing user accounts for system access<br>
                            • Only available users with 'driver' role are shown<br>
                            • Optional - drivers can exist without user accounts<br><br>
                            <strong>Vehicle Types:</strong><br>
                            • <strong>Truck:</strong> Large cargo vehicles<br>
                            • <strong>Van:</strong> Medium delivery vehicles<br>
                            • <strong>Pickup:</strong> Small utility vehicles<br>
                            • <strong>Motorcycle:</strong> Quick delivery options<br><br>
                            <strong>Status Options:</strong><br>
                            • <strong>Active:</strong> Available for assignments<br>
                            • <strong>Inactive:</strong> Not available<br>
                            • <strong>On Leave:</strong> Temporarily unavailable
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>