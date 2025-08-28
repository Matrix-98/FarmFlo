<?php
require_once '../config/db.php';
require_once '../utils/inventory_helpers.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check if user is admin
if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Tracking Data View";
$current_page = "admin";

// Filter parameters
$driver_filter = $_GET['driver_id'] ?? '';
$shipment_filter = $_GET['shipment_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($driver_filter) {
    $where_conditions[] = "td.recorded_by = ?";
    $params[] = $driver_filter;
    $param_types .= 'i';
}

if ($shipment_filter) {
    $where_conditions[] = "td.shipment_id = ?";
    $params[] = $shipment_filter;
    $param_types .= 'i';
}

if ($status_filter) {
    $where_conditions[] = "td.delivery_status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($date_filter) {
    $where_conditions[] = "DATE(td.recorded_at) = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get tracking data
$tracking_data = [];
$sql = "SELECT td.*, s.shipment_id, u.username as customer_name, 
               CONCAT(d.first_name, ' ', d.last_name) as driver_name, d.phone as driver_phone,
               v.license_plate, v.type as vehicle_type
        FROM tracking_data td
        JOIN shipments s ON td.shipment_id = s.shipment_id
        JOIN orders o ON s.order_id = o.order_id
        JOIN users u ON o.customer_id = u.user_id
        JOIN drivers d ON s.driver_id = d.driver_id
        LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
        $where_clause
        ORDER BY td.recorded_at DESC
        LIMIT 100";

if ($stmt = mysqli_prepare($conn, $sql)) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $tracking_data[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Get total shipments count for reference
$total_shipments = 0;
$sql_count = "SELECT COUNT(*) as count FROM shipments";
$result_count = mysqli_query($conn, $sql_count);
if ($result_count) {
    $row_count = mysqli_fetch_assoc($result_count);
    $total_shipments = $row_count['count'];
}

// Get drivers for filter
$drivers = [];
$sql_drivers = "SELECT DISTINCT d.driver_id, CONCAT(d.first_name, ' ', d.last_name) as name, d.phone 
                FROM drivers d
                LEFT JOIN tracking_data td ON d.user_id = td.recorded_by
                ORDER BY d.first_name, d.last_name";
$result_drivers = mysqli_query($conn, $sql_drivers);
if ($result_drivers) {
    while ($row = mysqli_fetch_assoc($result_drivers)) {
        $drivers[] = $row;
    }
}

// Get shipments for filter
$shipments = [];
$sql_shipments = "SELECT DISTINCT s.shipment_id, u.username as customer_name
                  FROM shipments s
                  JOIN orders o ON s.order_id = o.order_id
                  JOIN users u ON o.customer_id = u.user_id
                  ORDER BY s.shipment_id DESC";
$result_shipments = mysqli_query($conn, $sql_shipments);
if ($result_shipments) {
    while ($row = mysqli_fetch_assoc($result_shipments)) {
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
            <h2><i class="fas fa-map-marker-alt me-2"></i>Tracking Data View</h2>
            <div class="d-flex gap-2">
                <a href="<?php echo BASE_URL; ?>admin/" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Admin
                </a>
                <button onclick="exportToCSV()" class="btn btn-outline-success">
                    <i class="fas fa-download me-1"></i>Export CSV
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row">
                            <div class="col-md-3 mb-3">
                                <label for="driver_id" class="form-label">Driver</label>
                                <select class="form-select" id="driver_id" name="driver_id">
                                    <option value="">All Drivers</option>
                                    <?php foreach ($drivers as $driver): ?>
                                    <option value="<?php echo $driver['driver_id']; ?>" 
                                            <?php echo $driver_filter == $driver['driver_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($driver['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="shipment_id" class="form-label">Shipment</label>
                                <select class="form-select" id="shipment_id" name="shipment_id">
                                    <option value="">All Shipments</option>
                                    <?php foreach ($shipments as $shipment): ?>
                                    <option value="<?php echo $shipment['shipment_id']; ?>" 
                                            <?php echo $shipment_filter == $shipment['shipment_id'] ? 'selected' : ''; ?>>
                                        #<?php echo $shipment['shipment_id']; ?> - <?php echo htmlspecialchars($shipment['customer_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="in_transit" <?php echo $status_filter == 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                                    <option value="out_for_delivery" <?php echo $status_filter == 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                                    <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo $date_filter; ?>">
                            </div>
                            <div class="col-md-2 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="<?php echo BASE_URL; ?>admin/tracking_view.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tracking Data Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>Tracking Data 
                            <span class="badge bg-primary ms-2"><?php echo count($tracking_data); ?> entries</span>
                            <?php if ($total_shipments > 0): ?>
                            <span class="badge bg-info ms-2"><?php echo $total_shipments; ?> total shipments</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($tracking_data)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="trackingTable">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Driver</th>
                                        <th>Shipment</th>
                                        <th>Customer</th>
                                        <th>Vehicle</th>
                                        <th>Location</th>
                                        <th>Temperature</th>
                                        <th>Humidity</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tracking_data as $track): ?>
                                    <tr>
                                        <td>
                                            <small>
                                                <?php echo date('M d, Y', strtotime($track['recorded_at'])); ?><br>
                                                <strong><?php echo date('H:i:s', strtotime($track['recorded_at'])); ?></strong>
                                            </small>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($track['driver_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($track['driver_phone']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">#<?php echo $track['shipment_id']; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($track['customer_name']); ?></td>
                                        <td>
                                            <?php if ($track['license_plate']): ?>
                                            <small>
                                                <?php echo htmlspecialchars($track['license_plate']); ?><br>
                                                <span class="text-muted"><?php echo htmlspecialchars($track['vehicle_type']); ?></span>
                                            </small>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo number_format($track['latitude'], 6); ?><br>
                                                <?php echo number_format($track['longitude'], 6); ?>
                                            </small>
                                            <button class="btn btn-sm btn-outline-primary ms-1" 
                                                    onclick="showOnMap(<?php echo $track['latitude']; ?>, <?php echo $track['longitude']; ?>)">
                                                <i class="fas fa-map"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $track['temperature'] > 30 ? 'danger' : ($track['temperature'] > 20 ? 'warning' : 'success'); ?>">
                                                <?php echo $track['temperature']; ?>°C
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $track['humidity']; ?>%</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $track['delivery_status'] == 'delivered' ? 'success' : 
                                                    ($track['delivery_status'] == 'failed' ? 'danger' : 'primary'); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $track['delivery_status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($track['order_notes']): ?>
                                            <button class="btn btn-sm btn-outline-secondary" 
                                                    onclick="showNotes('<?php echo htmlspecialchars(addslashes($track['order_notes'])); ?>')">
                                                <i class="fas fa-sticky-note"></i> View
                                            </button>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-info" 
                                                        onclick="viewDetails(<?php echo $track['tracking_id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-map-marker-alt text-muted" style="font-size: 4rem;"></i>
                            <h4 class="text-muted mt-3">No Tracking Data Found</h4>
                            <p class="text-muted">
                                <?php if ($total_shipments > 0): ?>
                                    No tracking data matches your current filters. 
                                    <br>There are <?php echo $total_shipments; ?> shipments in the system.
                                    <br>Drivers need to add tracking data using the "Tracking Entry" page.
                                <?php else: ?>
                                    No shipments found in the system. Please create shipments first.
                                <?php endif; ?>
                            </p>
                            <?php if ($total_shipments > 0): ?>
                            <div class="mt-3">
                                <a href="<?php echo BASE_URL; ?>driver/tracking_entry.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Add Tracking Data
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Shipments (for reference) -->
        <?php if ($total_shipments > 0 && empty($tracking_data)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-shipping-fast me-2"></i>All Shipments (No Tracking Data Yet)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get all shipments for reference
                        $all_shipments = [];
                        $sql_all = "SELECT s.shipment_id, s.status, s.created_at, s.planned_arrival,
                                          u.username as customer_name, CONCAT(d.first_name, ' ', d.last_name) as driver_name
                                   FROM shipments s
                                   JOIN orders o ON s.order_id = o.order_id
                                   JOIN users u ON o.customer_id = u.user_id
                                   LEFT JOIN drivers d ON s.driver_id = d.driver_id
                                   ORDER BY s.shipment_id DESC
                                   LIMIT 10";
                        $result_all = mysqli_query($conn, $sql_all);
                        if ($result_all) {
                            while ($row = mysqli_fetch_assoc($result_all)) {
                                $all_shipments[] = $row;
                            }
                        }
                        ?>
                        
                        <?php if (!empty($all_shipments)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Shipment ID</th>
                                        <th>Customer</th>
                                        <th>Driver</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Planned Arrival</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_shipments as $shipment): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary">#<?php echo $shipment['shipment_id']; ?></span></td>
                                        <td><?php echo htmlspecialchars($shipment['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($shipment['driver_name'] ?? 'Not Assigned'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $shipment['status'] == 'delivered' ? 'success' : 
                                                    ($shipment['status'] == 'in_transit' ? 'primary' : 
                                                    ($shipment['status'] == 'pending' ? 'warning' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $shipment['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($shipment['created_at'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($shipment['planned_arrival'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                These shipments are available for tracking. Drivers can add tracking data using the "Tracking Entry" page.
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Notes Modal -->
<div class="modal fade" id="notesModal" tabindex="-1" aria-labelledby="notesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notesModalLabel">Order Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="notesContent"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Map Modal -->
<div class="modal fade" id="mapModal" tabindex="-1" aria-labelledby="mapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mapModalLabel">Location on Map</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="map" style="height: 400px; width: 100%;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showNotes(notes) {
    document.getElementById('notesContent').textContent = notes;
    const modal = new bootstrap.Modal(document.getElementById('notesModal'));
    modal.show();
}

function showOnMap(lat, lng) {
    const modal = new bootstrap.Modal(document.getElementById('mapModal'));
    modal.show();
    
    // Initialize map after modal is shown
    setTimeout(() => {
        const map = L.map('map').setView([lat, lng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        L.marker([lat, lng]).addTo(map)
            .bindPopup(`Location: ${lat}, ${lng}`)
            .openPopup();
    }, 500);
}

function viewDetails(trackingId) {
    // You can implement detailed view here
    alert('Detailed view for tracking ID: ' + trackingId);
}

function exportToCSV() {
    const table = document.getElementById('trackingTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        
        for (let j = 0; j < cols.length - 1; j++) { // Skip last column (actions)
            let text = cols[j].innerText.replace(/,/g, ';');
            rowData.push('"' + text + '"');
        }
        
        csv.push(rowData.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'tracking_data_' + new Date().toISOString().split('T')[0] + '.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Auto-refresh every 60 seconds
setInterval(() => {
    if (!document.hidden) {
        location.reload();
    }
}, 60000);
</script>

<!-- Leaflet CSS and JS for maps -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

<?php include '../includes/footer.php'; ?>
