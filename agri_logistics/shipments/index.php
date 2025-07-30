<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if (!in_array($_SESSION["role"], ['admin', 'logistics_manager', 'driver', 'customer'])) {
    $_SESSION['error_message'] = "You do not have permission to access Shipments.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Shipment Management";
$current_page = "shipments";

$logged_in_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';
$records_per_page = 10;
$current_page_num = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page_num - 1) * $records_per_page;

$where_params_values = [];
$where_params_types = '';
$where_clauses = [];

$select_cols = "s.shipment_id, s.order_id, ol.name AS origin_name, dl.name AS destination_name, 
                v.license_plate, d.first_name, d.last_name, s.planned_departure, 
                s.planned_arrival, s.actual_departure, s.actual_arrival, s.status, 
                s.total_weight_kg, s.total_volume_m3,
                s.created_at, s.updated_at,
                uc.username AS created_by_username, uu.username AS updated_by_username";
$from_tables = "shipments s
                JOIN locations ol ON s.origin_location_id = ol.location_id
                JOIN locations dl ON s.destination_location_id = dl.location_id
                LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
                LEFT JOIN drivers d ON s.driver_id = d.driver_id
                LEFT JOIN users uc ON s.created_by = uc.user_id
                LEFT JOIN users uu ON s.updated_by = uu.user_id";

// --- DYNAMIC FILTERING BASED ON USER ROLE ---
if ($user_role == 'customer') {
    $from_tables .= " JOIN orders o ON s.order_id = o.order_id";
    $where_clauses[] = "o.customer_id = ?";
    $where_params_types .= 'i';
    $where_params_values[] = $logged_in_user_id;
} elseif ($user_role == 'driver') {
    $driver_profile_id = null;
    $sql_driver_id = "SELECT driver_id FROM drivers WHERE user_id = ?";
    if ($stmt_driver_id = mysqli_prepare($conn, $sql_driver_id)) {
        mysqli_stmt_bind_param($stmt_driver_id, "i", $logged_in_user_id);
        mysqli_stmt_execute($stmt_driver_id);
        mysqli_stmt_bind_result($stmt_driver_id, $driver_profile_id);
        mysqli_stmt_fetch($stmt_driver_id);
        mysqli_stmt_close($stmt_driver_id);
    }
    if ($driver_profile_id) {
        $where_clauses[] = "s.driver_id = ?";
        $where_params_types .= 'i';
        $where_params_values[] = $driver_profile_id;
    } else {
        $where_clauses[] = "s.driver_id = 0";
    }
}

// --- Search and Status Filter Logic ---
if (!empty($search_query)) {
    $where_clauses[] = "(s.shipment_id LIKE ? OR ol.name LIKE ? OR dl.name LIKE ? OR v.license_plate LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $where_params_types .= 'ssssss';
    $where_params_values = array_merge($where_params_values, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
}

if (!empty($filter_status)) {
    $where_clauses[] = "s.status = ?";
    $where_params_types .= 's';
    $where_params_values[] = $filter_status;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
}

// --- Total records for pagination (for COUNT query) ---
$total_records = 0;
$sql_count = "SELECT COUNT(s.shipment_id) AS total_count FROM " . $from_tables . $where_sql;
if ($stmt_count = mysqli_prepare($conn, $sql_count)) {
    if (!empty($where_params_types)) { // FIX IS HERE: Only bind if there are parameters
        mysqli_stmt_bind_param($stmt_count, $where_params_types, ...$where_params_values);
    }
    mysqli_stmt_execute($stmt_count);
    $result_count = mysqli_stmt_get_result($stmt_count);
    $row_count = mysqli_fetch_assoc($result_count);
    $total_records = $row_count['total_count'];
    mysqli_stmt_close($stmt_count);
} else {
    error_log("Shipment count query failed: " . mysqli_error($conn));
    echo '<div class="alert alert-danger">ERROR: Could not retrieve shipment count. Please try again later.</div>';
}

$total_pages = ceil($total_records / $records_per_page);

// --- Fetch shipments for current page (for main SELECT query) ---
$shipments = [];
$sql_shipments = "SELECT " . $select_cols . " FROM " . $from_tables . $where_sql . " ORDER BY s.planned_departure DESC LIMIT ? OFFSET ?";

if ($stmt_shipments = mysqli_prepare($conn, $sql_shipments)) {
    $final_bind_types = $where_params_types . 'ii';
    $final_bind_params = array_merge($where_params_values, [$records_per_page, $offset]);
    
    // FIX IS HERE: Only bind if there are parameters
    if (mysqli_stmt_bind_param($stmt_shipments, $final_bind_types, ...$final_bind_params)) {
        if (!mysqli_stmt_execute($stmt_shipments)) {
            error_log("Shipment list query execute failed: " . mysqli_error($conn));
            echo '<div class="alert alert-danger">ERROR: Could not execute shipment list query. Please try again later.</div>';
        }
        $result_shipments = mysqli_stmt_get_result($stmt_shipments);
    
        if (mysqli_num_rows($result_shipments) > 0) {
            while ($row = mysqli_fetch_assoc($result_shipments)) {
                $shipments[] = $row;
            }
            mysqli_free_result($result_shipments);
        }
    } else {
        error_log("Shipment list bind param failed: " . mysqli_error($conn));
        echo '<div class="alert alert-danger">ERROR: Could not bind parameters for shipment list. Please try again later.</div>';
    }
    mysqli_stmt_close($stmt_shipments);
} else {
    error_log("Shipment list query prepare failed: " . mysqli_error($conn));
    echo '<div class="alert alert-danger">ERROR: Could not prepare SQL statement for shipments. Please try again later.</div>';
    echo '<div class="alert alert-warning">Database Error Details (for admin): ' . mysqli_error($conn) . '</div>';
}

// Connection is closed in footer.php
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Shipment List</h2>
            <?php if ($_SESSION["role"] == 'admin' || $_SESSION["role"] == 'logistics_manager'): ?>
                <a href="<?php echo BASE_URL; ?>shipments/create.php" class="btn btn-success"><i class="fas fa-plus"></i> Create New Shipment</a>
            <?php endif; ?>
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

        <div class="card p-3 mb-4 shadow-sm">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="row g-3 align-items-center">
                <div class="col-md-5">
                    <label for="search_input" class="visually-hidden">Search</label>
                    <input type="text" class="form-control" id="search_input" name="search" placeholder="Search by ID, Origin/Dest, Vehicle, Driver..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="col-md-4">
                    <label for="status_filter" class="visually-hidden">Filter by Status</label>
                    <select class="form-select" id="status_filter" name="status_filter">
                        <option value="">All Statuses</option>
                        <?php
                        $all_statuses = ['pending', 'assigned', 'picked_up', 'in_transit', 'delivered', 'delayed', 'cancelled'];
                        foreach ($all_statuses as $status_option) {
                            $selected = ($filter_status == $status_option) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($status_option) . '" ' . $selected . '>' . htmlspecialchars(ucwords(str_replace('_', ' ', $status_option))) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
                    <a href="<?php echo BASE_URL; ?>shipments/index.php" class="btn btn-secondary ms-2"><i class="fas fa-redo"></i> Reset</a>
                </div>
            </form>
        </div>

        <?php if (!empty($shipments)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Origin</th>
                            <th>Destination</th>
                            <th>Order ID</th>
                            <th>Vehicle</th>
                            <th>Driver</th>
                            <th>Planned Departure</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Last Updated By</th>
                            <th>Last Updated At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shipments as $shipment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($shipment['shipment_id']); ?></td>
                                <td><?php echo htmlspecialchars($shipment['origin_name']); ?></td>
                                <td><?php echo htmlspecialchars($shipment['destination_name']); ?></td>
                                <td>
                                    <?php if ($shipment['order_id']): ?>
                                        <a href="<?php echo BASE_URL; ?>orders/view.php?id=<?php echo $shipment['order_id']; ?>"><?php echo htmlspecialchars($shipment['order_id']); ?></a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($shipment['license_plate'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(($shipment['first_name'] ? $shipment['first_name'] . ' ' . $shipment['last_name'] : 'N/A')); ?></td>
                                <td><?php echo htmlspecialchars($shipment['planned_departure']); ?></td>
                                <td><span class="badge bg-<?php
                                    switch ($shipment['status']) {
                                        case 'pending': echo 'secondary'; break;
                                        case 'assigned': echo 'primary'; break;
                                        case 'picked_up': echo 'info'; break;
                                        case 'in_transit': echo 'warning'; break;
                                        case 'delivered': echo 'success'; break;
                                        case 'delayed': echo 'danger'; break;
                                        case 'cancelled': echo 'dark'; break;
                                        default: echo 'light text-dark';
                                    }
                                ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $shipment['status']))); ?></span></td>
                                <td><?php echo htmlspecialchars($shipment['created_by_username'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($shipment['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($shipment['updated_by_username'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($shipment['updated_at']); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>shipments/view.php?id=<?php echo $shipment['shipment_id']; ?>" class="btn btn-sm btn-info mb-1" title="View Details"><i class="fas fa-eye"></i></a>
                                    <?php if ($_SESSION["role"] == 'admin' || $_SESSION["role"] == 'logistics_manager'): ?>
                                        <a href="<?php echo BASE_URL; ?>shipments/edit.php?id=<?php echo $shipment['shipment_id']; ?>" class="btn btn-sm btn-primary mb-1" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="<?php echo BASE_URL; ?>shipments/delete.php?id=<?php echo $shipment['shipment_id']; ?>" class="btn btn-sm btn-danger mb-1" title="Delete" onclick="return confirm('Are you sure you want to delete this shipment? This cannot be undone.');"><i class="fas fa-trash-alt"></i></a>
                                    <?php endif; ?>
                                    <?php if ($_SESSION["role"] == 'driver' || $_SESSION["role"] == 'logistics_manager' || $_SESSION["role"] == 'admin'): ?>
                                         <a href="<?php echo BASE_URL; ?>shipments/update_status.php?id=<?php echo $shipment['shipment_id']; ?>" class="btn btn-sm btn-warning mb-1" title="Update Status"><i class="fas fa-clipboard-check"></i> Status</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($current_page_num > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $current_page_num - 1; ?><?php echo (!empty($search_query) ? '&search=' . urlencode($search_query) : ''); ?><?php echo (!empty($filter_status) ? '&status_filter=' . urlencode($filter_status) : ''); ?>">Previous</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($i == $current_page_num) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo (!empty($search_query) ? '&search=' . urlencode($search_query) : ''); ?><?php echo (!empty($filter_status) ? '&status_filter=' . urlencode($filter_status) : ''); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($current_page_num < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $current_page_num + 1; ?><?php echo (!empty($search_query) ? '&search=' . urlencode($search_query) : ''); ?><?php echo (!empty($filter_status) ? '&status_filter=' . urlencode($filter_status) : ''); ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="text-center text-muted mt-2">
                Displaying <?php echo count($shipments); ?> of <?php echo $total_records; ?> shipments.
            </div>

        <?php else: ?>
            <div class="alert alert-info">No shipments found matching your criteria.</div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>