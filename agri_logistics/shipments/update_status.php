<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check user role for access control
if (!in_array($_SESSION["role"], ['admin', 'logistics_manager', 'driver'])) {
    $_SESSION['error_message'] = "You do not have permission to update shipment status.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Update Shipment Status";
$current_page = "shipments";

$shipment_id = $status = $actual_departure = $actual_arrival = "";
$status_err = "";

// Initialize audit trail variables for display
$created_at = $updated_at = $created_by_username = $updated_by_username = '';


// Fetch current shipment status and audit data
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $shipment_id = trim($_GET["id"]);

    // FIX: Include created_at, updated_at, created_by, updated_by in the SELECT query
    $sql_fetch_status = "SELECT status, actual_departure, actual_arrival, created_at, updated_at, created_by, updated_by FROM shipments WHERE shipment_id = ?";
    if ($stmt_fetch = mysqli_prepare($conn, $sql_fetch_status)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $param_id);
        $param_id = $shipment_id;
        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);
            if (mysqli_num_rows($result_fetch) == 1) {
                $row = mysqli_fetch_assoc($result_fetch);
                $status = $row["status"];
                $actual_departure = $row["actual_departure"] ? date('Y-m-d\TH:i', strtotime($row["actual_departure"])) : '';
                $actual_arrival = $row["actual_arrival"] ? date('Y-m-d\TH:i', strtotime($row["actual_arrival"])) : '';
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
                $_SESSION['error_message'] = "Shipment not found.";
                header("location: " . BASE_URL . "shipments/index.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "Oops! Something went wrong fetching status.";
            error_log("Error executing shipment status fetch: " . mysqli_error($conn));
            header("location: " . BASE_URL . "shipments/index.php");
            exit();
        }
        mysqli_stmt_close($stmt_fetch);
    } else {
        $_SESSION['error_message'] = "Error preparing shipment status fetch statement.";
        error_log("Error preparing shipment status fetch statement: " . mysqli_error($conn));
        header("location: " . BASE_URL . "shipments/index.php");
        exit();
    }
} else if ($_SERVER["REQUEST_METHOD"] != "POST") {
    $_SESSION['error_message'] = "Invalid request. No shipment ID provided.";
    header("location: " . BASE_URL . "shipments/index.php");
    exit();
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $shipment_id = $_POST["shipment_id"];

    if (empty(trim($_POST["status"]))) {
        $status_err = "Please select a status.";
    } else {
        $status = trim($_POST["status"]);
    }

    $actual_departure = !empty(trim($_POST["actual_departure"])) ? trim($_POST["actual_departure"]) : NULL;
    $actual_arrival = !empty(trim($_POST["actual_arrival"])) ? trim($_POST["actual_arrival"]) : NULL;

    if (empty($status_err)) {
        $logged_in_user_id = $_SESSION['user_id'];
        // FIX: Add 'updated_by' to the UPDATE statement
        $sql_update_status = "UPDATE shipments SET status = ?, actual_departure = ?, actual_arrival = ?, updated_by = ? WHERE shipment_id = ?";

        if ($stmt_update = mysqli_prepare($conn, $sql_update_status)) {
            mysqli_stmt_bind_param($stmt_update, "sssii", $param_status, $param_actual_departure, $param_actual_arrival, $param_updated_by, $param_id);

            $param_status = $status;
            $param_actual_departure = $actual_departure;
            $param_actual_arrival = $actual_arrival;
            $param_updated_by = $logged_in_user_id; // Capture logged-in user's ID
            $param_id = $shipment_id;

            if (mysqli_stmt_execute($stmt_update)) {
                $_SESSION['success_message'] = "Shipment status updated successfully!";
                header("location: " . BASE_URL . "shipments/index.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error: Could not update status. " . mysqli_error($conn);
                error_log("Error updating shipment status: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt_update);
        } else {
            $_SESSION['error_message'] = "Error preparing update statement: " . mysqli_error($conn);
            error_log("Error preparing shipment status update statement: " . mysqli_error($conn));
        }
    }
}
// mysqli_close($conn) is in footer.php
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Update Shipment Status (ID: <?php echo htmlspecialchars($shipment_id); ?>)</h2>
        <a href="<?php echo BASE_URL; ?>shipments/index.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Shipment List</a>

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
                <input type="hidden" name="shipment_id" value="<?php echo htmlspecialchars($shipment_id); ?>">

                <div class="mb-3">
                    <label for="status" class="form-label">Shipment Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select <?php echo (!empty($status_err)) ? 'is-invalid' : ''; ?>">
                        <option value="">Select Status</option>
                        <option value="pending" <?php echo ($status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="assigned" <?php echo ($status == 'assigned') ? 'selected' : ''; ?>>Assigned</option>
                        <option value="picked_up" <?php echo ($status == 'picked_up') ? 'selected' : ''; ?>>Picked Up</option>
                        <option value="in_transit" <?php echo ($status == 'in_transit') ? 'selected' : ''; ?>>In Transit</option>
                        <option value="delivered" <?php echo ($status == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                        <option value="delayed" <?php echo ($status == 'delayed') ? 'selected' : ''; ?>>Delayed</option>
                        <option value="cancelled" <?php echo ($status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <div class="invalid-feedback"><?php echo $status_err; ?></div>
                </div>

                <div class="mb-3">
                    <label for="actual_departure" class="form-label">Actual Departure Time</label>
                    <input type="datetime-local" name="actual_departure" id="actual_departure" class="form-control" value="<?php echo htmlspecialchars($actual_departure); ?>">
                </div>

                <div class="mb-3">
                    <label for="actual_arrival" class="form-label">Actual Arrival Time</label>
                    <input type="datetime-local" name="actual_arrival" id="actual_arrival" class="form-control" value="<?php echo htmlspecialchars($actual_arrival); ?>">
                </div>

                <button type="submit" class="btn btn-warning"><i class="fas fa-sync-alt"></i> Update Status</button>
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