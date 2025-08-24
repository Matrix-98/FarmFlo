<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check user role for access control
if (!in_array($_SESSION["role"], ['admin', 'farm_manager', 'logistics_manager', 'driver', 'customer'])) {
    $_SESSION['error_message'] = "You do not have permission to access Shipment Management.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$user_role = $_SESSION['role'];
$page_title = ($user_role == 'customer') ? "My Shipments" : (($user_role == 'driver') ? "My Assigned Shipments" : "Shipment Management");
$current_page = "shipments";
$logged_in_user_id = $_SESSION['user_id'];

// Pagination settings
$records_per_page = 10;
$current_page_num = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page_num - 1) * $records_per_page;

// Search and filter parameters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';

// Build the SQL query with search and filter conditions
$sql_conditions = [];
$sql_bind_params = [];
$sql_bind_types = '';

// Role-based filtering
if ($user_role == 'customer') {
    $sql_conditions[] = "s.order_id IN (SELECT order_id FROM orders WHERE customer_id = ?)";
    $sql_bind_params[] = $logged_in_user_id;
    $sql_bind_types .= 'i';
} elseif ($user_role == 'driver') {
    // Get the driver's ID
    $driver_sql = "SELECT driver_id FROM drivers WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $driver_sql);
    mysqli_stmt_bind_param($stmt, "i", $logged_in_user_id);
    mysqli_stmt_execute($stmt);
    $driver_result = mysqli_stmt_get_result($stmt);
    $driver_info = mysqli_fetch_assoc($driver_result);
    mysqli_stmt_close($stmt);

    if ($driver_info) {
        // Drivers can only see shipments assigned to them
        $sql_conditions[] = "s.driver_id = ?";
        $sql_bind_params[] = $driver_info['driver_id'];
        $sql_bind_types .= 'i';
    } else {
        // If no driver record found, show no shipments
        $sql_conditions[] = "1 = 0";
    }
}

// Search condition
if (!empty($search_query)) {
    $sql_conditions[] = "(s.shipment_id LIKE ? OR s.shipment_code LIKE ? OR s.order_id LIKE ? OR o.order_code LIKE ? OR ol.name LIKE ? OR dl.name LIKE ? OR v.license_plate LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ?)";
    $search_param = "%$search_query%";
    $sql_bind_params[] = $search_param;
    $sql_bind_params[] = $search_param;
    $sql_bind_params[] = $search_param;
    $sql_bind_params[] = $search_param;
    $sql_bind_params[] = $search_param;
    $sql_bind_params[] = $search_param;
    $sql_bind_params[] = $search_param;
    $sql_bind_params[] = $search_param;
    $sql_bind_params[] = $search_param;
    $sql_bind_types .= 'sssssssss';
}

// Status filter
if (!empty($filter_status)) {
    $sql_conditions[] = "s.status = ?";
    $sql_bind_params[] = $filter_status;
    $sql_bind_types .= 's';
}

// Base SQL query
$sql = "SELECT s.shipment_id, s.shipment_code, s.order_id, o.order_code, s.status, s.planned_departure, s.planned_arrival, s.actual_departure, s.actual_arrival, s.total_weight_kg, s.total_volume_m3, s.notes, s.created_at, s.updated_at,
               ol.name AS origin_name, dl.name AS destination_name,
               v.license_plate, v.type AS vehicle_type,
               d.first_name, d.last_name,
               uc.username AS created_by_username, uu.username AS updated_by_username
        FROM shipments s
        LEFT JOIN orders o ON s.order_id = o.order_id
        JOIN locations ol ON s.origin_location_id = ol.location_id
        JOIN locations dl ON s.destination_location_id = dl.location_id
        LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
        LEFT JOIN drivers d ON s.driver_id = d.driver_id
        LEFT JOIN users uc ON s.created_by = uc.user_id
        LEFT JOIN users uu ON s.updated_by = uu.user_id";

// Add WHERE clause if there are conditions
if (!empty($sql_conditions)) {
    $sql .= " WHERE " . implode(' AND ', $sql_conditions);
}

// Add ORDER BY clause
$sql .= " ORDER BY s.created_at DESC";

// Count total records for pagination
$count_sql = "SELECT COUNT(*) FROM shipments s
        JOIN locations ol ON s.origin_location_id = ol.location_id
        JOIN locations dl ON s.destination_location_id = dl.location_id
        LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
        LEFT JOIN drivers d ON s.driver_id = d.driver_id
        LEFT JOIN users uc ON s.created_by = uc.user_id
        LEFT JOIN users uu ON s.updated_by = uu.user_id";

// Add WHERE clause if there are conditions
if (!empty($sql_conditions)) {
    $count_sql .= " WHERE " . implode(' AND ', $sql_conditions);
}

$total_records = 0;
if (!empty($sql_bind_params)) {
    if ($stmt = mysqli_prepare($conn, $count_sql)) {
        mysqli_stmt_bind_param($stmt, $sql_bind_types, ...$sql_bind_params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $total_records = mysqli_fetch_row($result)[0];
        mysqli_stmt_close($stmt);
    }
} else {
    $result = mysqli_query($conn, $count_sql);
    $total_records = mysqli_fetch_row($result)[0];
}

$total_pages = ceil($total_records / $records_per_page);

// Add pagination to main query
$sql .= " LIMIT ? OFFSET ?";
$sql_bind_params[] = $records_per_page;
$sql_bind_params[] = $offset;
$sql_bind_types .= 'ii';

// Execute the main query
$shipments = [];
if (!empty($sql_bind_params)) {
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, $sql_bind_types, ...$sql_bind_params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $shipments[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
} else {
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $shipments[] = $row;
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
                            <h2><?php echo ($user_role == 'customer') ? 'My Shipments' : (($user_role == 'driver') ? 'My Assigned Shipments' : 'Shipment Management'); ?></h2>
            <?php if (in_array($user_role, ['admin', 'logistics_manager'])): ?>
            <a href="<?php echo BASE_URL; ?>shipments/create.php" class="btn btn-success"><i class="fas fa-plus"></i> Create Shipment</a>
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

        <!-- Search and Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <!-- Debug Information (remove in production) -->
                <?php if (isset($_GET['debug']) && $_SESSION['role'] == 'admin'): ?>
                <div class="alert alert-info mb-3">
                    <strong>Debug Info:</strong><br>
                    Search Query: "<?php echo htmlspecialchars($search_query); ?>"<br>
                    Status Filter: "<?php echo htmlspecialchars($filter_status); ?>"<br>
                    User Role: <?php echo htmlspecialchars($user_role); ?><br>
                    Search includes: Shipment ID, Shipment Code, Order ID, Order Code, Locations, Vehicle, Driver<br>
                    User ID: <?php echo $logged_in_user_id; ?><br>
                    Total Records: <?php echo $total_records; ?><br>
                    SQL Conditions: <?php echo !empty($sql_conditions) ? implode(' AND ', $sql_conditions) : 'None'; ?><br>
                    <?php if ($user_role == 'driver'): ?>
                    Driver Info:<br>
                    <?php
                        $driver_sql = "SELECT driver_id, first_name, last_name FROM drivers WHERE user_id = ?";
                        $stmt = mysqli_prepare($conn, $driver_sql);
                        mysqli_stmt_bind_param($stmt, "i", $logged_in_user_id);
                        mysqli_stmt_execute($stmt);
                        $driver_result = mysqli_stmt_get_result($stmt);
                        $driver_info = mysqli_fetch_assoc($driver_result);
                        if ($driver_info) {
                            echo "Driver ID: " . $driver_info['driver_id'] . "<br>";
                            echo "Name: " . htmlspecialchars($driver_info['first_name'] . ' ' . $driver_info['last_name']) . "<br>";
                        } else {
                            echo "No driver record found for this user.<br>";
                        }
                        mysqli_stmt_close($stmt);
                    ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search by shipment code, order code, location, vehicle, driver...">
                    </div>
                    <div class="col-md-3">
                        <label for="status_filter" class="form-label">Status</label>
                        <select class="form-select" id="status_filter" name="status_filter">
                            <option value="">All Statuses</option>
                                                            <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="assigned" <?php echo $filter_status == 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                                <option value="in_transit" <?php echo $filter_status == 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                                <option value="out_for_delivery" <?php echo $filter_status == 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                                <option value="delivered" <?php echo $filter_status == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="failed" <?php echo $filter_status == 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Search
                            </button>
                            <a href="<?php echo BASE_URL; ?>shipments/" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                            <?php if ($_SESSION['role'] == 'admin'): ?>
                            <a href="<?php echo BASE_URL; ?>shipments/?debug=1" class="btn btn-outline-info">
                                <i class="fas fa-bug me-1"></i>Debug
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Shipments List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-truck me-2"></i><?php echo ($user_role == 'customer') ? 'My Shipments' : (($user_role == 'driver') ? 'My Assigned Shipments' : 'Shipments List'); ?></h5>
                <div class="d-flex gap-2">
                    <button onclick="exportToCSV()" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download me-1"></i>Export CSV
                    </button>
                    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Results Summary -->
                <div class="mb-3">
                    <p class="text-muted mb-0">
                        Showing <?php echo count($shipments); ?> of <?php echo $total_records; ?> shipments
                        <?php if (!empty($search_query) || !empty($filter_status)): ?>
                            (filtered results)
                        <?php endif; ?>
                    </p>
                </div>
                
                <?php if (!empty($shipments)): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="shipmentsTable">
                        <thead>
                            <tr>
                                <th>Shipment Code</th>
                                <th>Order ID</th>
                                <th>Route</th>
                                <th>Status</th>
                                <th>Vehicle</th>
                                <th>Driver</th>
                                <th>Planned Schedule</th>
                                <th>Actual Schedule</th>
                                <th>Cargo</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shipments as $shipment): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                            <i class="fas fa-truck text-primary" style="font-size: 0.8rem;"></i>
                                        </div>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($shipment['shipment_code']); ?></span>
                                    </div>
                                </td>
                                <td>
                    <?php if ($shipment['order_code']): ?>
                        <span class="badge bg-info">📦 Order #<?php echo htmlspecialchars($shipment['order_code']); ?></span>
                    <?php else: ?>
                        <span class="text-muted">N/A</span>
                    <?php endif; ?>
                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <small class="text-muted">From: <?php echo htmlspecialchars($shipment['origin_name']); ?></small>
                                        <small class="text-muted">To: <?php echo htmlspecialchars($shipment['destination_name']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        switch ($shipment['status']) {
                                            case 'pending': echo 'secondary'; break;
                                            case 'assigned': echo 'info'; break;
                                            case 'in_transit': echo 'primary'; break;
                                            case 'out_for_delivery': echo 'warning'; break;
                                            case 'delivered': echo 'success'; break;
                                            case 'failed': echo 'danger'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>"><?php echo ucwords(str_replace('_', ' ', $shipment['status'])); ?></span>
                                </td>
                                <td>
                                    <?php if ($shipment['license_plate']): ?>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($shipment['license_plate']); ?></span>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($shipment['vehicle_type']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($shipment['first_name'] && $shipment['last_name']): ?>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($shipment['first_name'] . ' ' . $shipment['last_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <small class="text-muted">Depart: <?php echo date('M d, Y H:i', strtotime($shipment['planned_departure'])); ?></small>
                                        <small class="text-muted">Arrive: <?php echo date('M d, Y H:i', strtotime($shipment['planned_arrival'])); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($shipment['actual_departure'] && $shipment['actual_arrival']): ?>
                                        <div class="d-flex flex-column">
                                            <small class="text-muted">Depart: <?php echo date('M d, Y H:i', strtotime($shipment['actual_departure'])); ?></small>
                                            <small class="text-muted">Arrive: <?php echo date('M d, Y H:i', strtotime($shipment['actual_arrival'])); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not started</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <small class="text-muted">Weight: <?php echo number_format($shipment['total_weight_kg'], 2); ?> kg</small>
                                        <small class="text-muted">Volume: <?php echo number_format($shipment['total_volume_m3'], 3); ?> m³</small>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($shipment['created_by_username']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="<?php echo BASE_URL; ?>shipments/view.php?id=<?php echo $shipment['shipment_id']; ?>" class="btn btn-outline-info" title="View Shipment">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (in_array($user_role, ['admin', 'logistics_manager'])): ?>
                                        <a href="<?php echo BASE_URL; ?>shipments/edit.php?id=<?php echo $shipment['shipment_id']; ?>" class="btn btn-outline-primary" title="Edit Shipment">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" onclick="deleteShipment(<?php echo $shipment['shipment_id']; ?>, '<?php echo htmlspecialchars($shipment['shipment_id']); ?>')" title="Delete Shipment">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Shipments pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($current_page_num > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $current_page_num - 1; ?>&search=<?php echo urlencode($search_query); ?>&status_filter=<?php echo urlencode($filter_status); ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $current_page_num - 2); $i <= min($total_pages, $current_page_num + 2); $i++): ?>
                        <li class="page-item <?php echo $i == $current_page_num ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&status_filter=<?php echo urlencode($filter_status); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($current_page_num < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $current_page_num + 1; ?>&search=<?php echo urlencode($search_query); ?>&status_filter=<?php echo urlencode($filter_status); ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>

                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-truck text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No Shipments Found</h5>
                                         <p class="text-muted">Start by creating your first shipment.</p>
                     <?php if (in_array($user_role, ['admin', 'logistics_manager'])): ?>
                     <a href="<?php echo BASE_URL; ?>shipments/create.php" class="btn btn-primary">
                         <i class="fas fa-plus me-2"></i>Create First Shipment
                     </a>
                     <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function deleteShipment(shipmentId, trackingNumber) {
    if (confirm('Are you sure you want to delete shipment "' + trackingNumber + '"? This action cannot be undone.')) {
        window.location.href = '<?php echo BASE_URL; ?>shipments/delete.php?id=' + shipmentId;
    }
}

function exportToCSV() {
    const table = document.getElementById('shipmentsTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const cols = row.querySelectorAll('td, th');
        let csvRow = [];
        
        for (let j = 0; j < cols.length - 1; j++) { // Exclude the Actions column
            let text = cols[j].innerText.replace(/"/g, '""');
            csvRow.push('"' + text + '"');
        }
        
        csv.push(csvRow.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'shipments_export.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../includes/footer.php'; ?>