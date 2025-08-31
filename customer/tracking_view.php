<?php
require_once '../config/db.php';
require_once '../utils/inventory_helpers.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check if user is a customer
if ($_SESSION["role"] != 'customer') {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "My Shipment Tracking";
$current_page = "customer";

$user_id = $_SESSION['user_id'];

// Get customer's shipments with tracking data
$shipments = [];
$sql = "SELECT DISTINCT s.shipment_id, s.order_id, s.status, s.created_at, s.planned_arrival,
               o.shipping_address, u.username as customer_name,
                                   CONCAT(d.first_name, ' ', d.last_name) as driver_name, d.phone as driver_phone,
               v.license_plate, v.type as vehicle_type,
               COUNT(sp.product_id) as product_count,
               SUM(sp.quantity_kg) as total_quantity
        FROM shipments s
        JOIN orders o ON s.order_id = o.order_id
        JOIN users u ON o.customer_id = u.user_id
        LEFT JOIN drivers d ON s.driver_id = d.driver_id
        LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
        LEFT JOIN shipment_products sp ON s.shipment_id = sp.shipment_id
        WHERE o.customer_id = ?
        GROUP BY s.shipment_id, s.order_id, s.status, s.created_at, s.planned_arrival, 
                                              o.shipping_address, u.username, CONCAT(d.first_name, ' ', d.last_name), d.phone, v.license_plate, v.type
        ORDER BY s.created_at DESC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $shipments[] = $row;
    }
    mysqli_stmt_close($stmt);
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
            <h2><i class="fas fa-truck me-2"></i>My Shipment Tracking</h2>
            <div class="d-flex gap-2">
                <button onclick="refreshTracking()" class="btn btn-outline-primary">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
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

        <!-- Customer Info Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="card-title mb-1">
                                    <i class="fas fa-user me-2"></i>Customer Dashboard
                                </h5>
                                <p class="card-text mb-0">
                                    Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                                    You have <?php echo count($shipments); ?> shipments to track.
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <div class="d-flex flex-column">
                                    <span class="h4 mb-0"><?php echo count($shipments); ?></span>
                                    <small>Total Shipments</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shipments List -->
        <?php if (!empty($shipments)): ?>
            <div class="row">
                <?php foreach ($shipments as $shipment): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card h-100 border-<?php echo $shipment['status'] == 'delivered' ? 'success' : ($shipment['status'] == 'in_transit' ? 'info' : 'warning'); ?>">
                            <div class="card-header bg-<?php echo $shipment['status'] == 'delivered' ? 'success' : ($shipment['status'] == 'in_transit' ? 'info' : 'warning'); ?> text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="fas fa-shipping-fast me-2"></i>Shipment #<?php echo $shipment['shipment_id']; ?>
                                    </h6>
                                    <span class="badge bg-light text-dark">
                                <?php echo ucfirst(str_replace('_', ' ', $shipment['status'])); ?>
                            </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Shipment Details -->
                                <div class="mb-3">
                                    <h6 class="text-muted mb-2">Shipment Details</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">Products:</small><br>
                                            <span class="fw-semibold"><?php echo $shipment['product_count']; ?> items</span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Quantity:</small><br>
                                            <span class="fw-semibold"><?php echo number_format($shipment['total_quantity'], 2); ?> kg</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Driver Info -->
                                <?php if ($shipment['driver_name']): ?>
                                    <div class="mb-3">
                                        <h6 class="text-muted mb-2">Driver</h6>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-user-tie text-primary me-2"></i>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($shipment['driver_name']); ?></span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-phone text-success me-2"></i>
                                            <span><?php echo htmlspecialchars($shipment['driver_phone']); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Vehicle Info -->
                                <?php if ($shipment['license_plate']): ?>
                                    <div class="mb-3">
                                        <h6 class="text-muted mb-2">Vehicle</h6>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-truck text-secondary me-2"></i>
                                            <span><?php echo htmlspecialchars($shipment['license_plate']); ?></span>
                                            <small class="text-muted ms-2">(<?php echo htmlspecialchars($shipment['vehicle_type']); ?>)</small>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Delivery Timeline -->
                                <div class="mb-3">
                                    <h6 class="text-muted mb-2">Delivery Timeline</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">Created:</small><br>
                                            <span><?php echo date('M d, Y', strtotime($shipment['created_at'])); ?></span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Estimated:</small><br>
                                            <span><?php echo date('M d, Y', strtotime($shipment['planned_arrival'])); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tracking Button -->
                                <div class="mt-3">
                                    <button class="btn btn-primary w-100 mb-2"
                                            onclick="viewTrackingDetails(<?php echo $shipment['shipment_id']; ?>)">
                                        <i class="fas fa-map-marker-alt me-2"></i>View Tracking Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-truck text-muted" style="font-size: 4rem;"></i>
                <h4 class="text-muted mt-3">No Shipments Found</h4>
                <p class="text-muted">You don't have any shipments to track at the moment.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tracking Details Modal -->
<div class="modal fade" id="trackingModal" tabindex="-1" aria-labelledby="trackingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="trackingModalLabel">Shipment Tracking Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="trackingModalBody">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading tracking data...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function viewTrackingDetails(shipmentId) {
        const modal = new bootstrap.Modal(document.getElementById('trackingModal'));
        modal.show();

        // Load tracking data via AJAX
        fetch(`<?php echo BASE_URL; ?>api/get_tracking_data.php?shipment_id=${shipmentId}`)
            .then(response => response.json())
            .then(data => {
                const modalBody = document.getElementById('trackingModalBody');

                if (data.success && data.tracking_data.length > 0) {
                    let html = `
                    <div class="row">
                        <div class="col-12">
                            <h6 class="mb-3">Tracking History</h6>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>Location</th>
                                            <th>Temperature</th>
                                            <th>Humidity</th>
                                            <th>Status</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;

                    data.tracking_data.forEach(track => {
                        html += `
                        <tr>
                            <td>
                                <small>
                                    ${new Date(track.recorded_at).toLocaleDateString()}<br>
                                    <strong>${new Date(track.recorded_at).toLocaleTimeString()}</strong>
                                </small>
                            </td>
                            <td>
                                <small>
                                    ${parseFloat(track.latitude).toFixed(6)}, ${parseFloat(track.longitude).toFixed(6)}
                                </small>
                                <button class="btn btn-sm btn-outline-primary ms-1"
                                        onclick="showOnMap(${track.latitude}, ${track.longitude})">
                                    <i class="fas fa-map"></i>
                                </button>
                            </td>
                            <td>
                                <span class="badge bg-${track.temperature > 30 ? 'danger' : (track.temperature > 20 ? 'warning' : 'success')}">
                                    ${track.temperature}°C
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info">${track.humidity}%</span>
                            </td>
                            <td>
                                                        <span class="badge bg-${track.delivery_status === 'delivered' ? 'success' :
                            (track.delivery_status === 'failed' ? 'danger' : 'primary')}">
                            ${track.delivery_status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                </span>
                            </td>
                            <td>
                                ${track.order_notes ? `<small class="text-muted">${track.order_notes}</small>` : '<span class="text-muted">-</span>'}
                            </td>
                        </tr>
                    `;
                    });

                    html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;

                    modalBody.innerHTML = html;
                } else {
                    modalBody.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-map-marker-alt text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">No Tracking Data Available</h5>
                        <p class="text-muted">Tracking data for this shipment has not been recorded yet.</p>
                    </div>
                `;
                }
            })
            .catch(error => {
                document.getElementById('trackingModalBody').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading tracking data. Please try again.
                </div>
            `;
            });
    }

    function showOnMap(lat, lng) {
        // Open map in new window
        const mapUrl = `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}&zoom=15`;
        window.open(mapUrl, '_blank');
    }

    function refreshTracking() {
        const refreshBtn = event.target;
        const originalText = refreshBtn.innerHTML;
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
        refreshBtn.disabled = true;

        setTimeout(() => {
            window.location.reload();
        }, 500);
    }

    // Auto-refresh every 2 minutes
    setInterval(() => {
        if (!document.hidden) {
            location.reload();
        }
    }, 120000);
</script>

<?php include '../includes/footer.php'; ?>
