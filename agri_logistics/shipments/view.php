<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

// Check user role for access control
if (!in_array($_SESSION["role"], ['admin', 'logistics_manager', 'driver', 'customer', 'farm_manager', 'warehouse_manager'])) {
    $_SESSION['error_message'] = "You do not have permission to view shipment details.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "View Shipment";
$current_page = "shipments";

$shipment = null;
$shipment_products = [];
$tracking_history = [];

// Initialize audit trail variables for display
$created_at = $updated_at = $created_by_username = $updated_by_username = '';

if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $shipment_id = trim($_GET["id"]);

    // Fetch shipment details including audit trail and order ID
    $sql_shipment = "SELECT
                        s.shipment_id,
                        ol.name AS origin_name, ol.address AS origin_address, ol.latitude AS origin_lat, ol.longitude AS origin_lon,
                        dl.name AS destination_name, dl.address AS destination_address, dl.latitude AS destination_lat, dl.longitude AS destination_lon,
                        v.license_plate, v.type AS vehicle_type,
                        d.first_name, d.last_name, d.phone_number,
                        s.planned_departure, s.planned_arrival, s.actual_departure, s.actual_arrival,
                        s.status, s.total_weight_kg, s.total_volume_m3, s.notes,
                        s.created_at, s.updated_at, s.created_by, s.updated_by,
                        s.order_id
                    FROM shipments s
                    JOIN locations ol ON s.origin_location_id = ol.location_id
                    JOIN locations dl ON s.destination_location_id = dl.location_id
                    LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
                    LEFT JOIN drivers d ON s.driver_id = d.driver_id
                    WHERE s.shipment_id = ?";
    
    if ($stmt_shipment = mysqli_prepare($conn, $sql_shipment)) {
        mysqli_stmt_bind_param($stmt_shipment, "i", $param_id);
        $param_id = $shipment_id;
        if (mysqli_stmt_execute($stmt_shipment)) {
            $result_shipment = mysqli_stmt_get_result($stmt_shipment);
            if (mysqli_num_rows($result_shipment) == 1) {
                $shipment = mysqli_fetch_assoc($result_shipment);
                // Capture audit data for display
                $created_at = $shipment["created_at"];
                $updated_at = $shipment["updated_at"];
                $created_by_id = $shipment["created_by"];
                $updated_by_id = $shipment["updated_by"];
                $order_id_linked = $shipment['order_id'];

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
            $_SESSION['error_message'] = "Error fetching shipment details: " . mysqli_error($conn);
            error_log("Error executing shipment fetch: " . mysqli_error($conn));
            header("location: " . BASE_URL . "shipments/index.php");
            exit();
        }
        mysqli_stmt_close($stmt_shipment);
    } else {
        $_SESSION['error_message'] = "Error preparing shipment query.";
        error_log("Error preparing shipment fetch statement: " . mysqli_error($conn));
        header("location: " . BASE_URL . "shipments/index.php");
        exit();
    }

    // FIX: Fetch products with the batch ID
    $sql_products = "SELECT sp.quantity, sp.unit, p.product_name, p.packaging_details, p.storage_requirements, p.batch_id
                     FROM shipment_products sp
                     JOIN products p ON sp.product_id = p.product_id
                     WHERE sp.shipment_id = ?";
    
    if ($stmt_products = mysqli_prepare($conn, $sql_products)) {
        mysqli_stmt_bind_param($stmt_products, "i", $param_id);
        $param_id = $shipment_id;
        if (mysqli_stmt_execute($stmt_products)) {
            $result_products = mysqli_stmt_get_result($stmt_products);
            while ($row = mysqli_fetch_assoc($result_products)) {
                $shipment_products[] = $row;
            }
        } else {
            $_SESSION['error_message'] = "Error fetching shipment products: " . mysqli_error($conn);
            error_log("Error fetching shipment products: " . mysqli_error($conn));
            header("location: " . BASE_URL . "shipments/index.php");
            exit();
        }
        mysqli_stmt_close($stmt_products);
    } else {
        $_SESSION['error_message'] = "Error preparing shipment products query.";
        error_log("Error preparing shipment products query: " . mysqli_error($conn));
        header("location: " . BASE_URL . "shipments/index.php");
        exit();
    }

    // Fetch tracking data associated with this shipment
    $tracking_history = [];
    $sql_tracking = "SELECT timestamp, latitude, longitude, temperature, humidity, status_update
                     FROM tracking_data
                     WHERE shipment_id = ?
                     ORDER BY timestamp DESC"; // Latest updates first
    
    if ($stmt_tracking = mysqli_prepare($conn, $sql_tracking)) {
        mysqli_stmt_bind_param($stmt_tracking, "i", $param_id);
        $param_id = $shipment_id;
        if (mysqli_stmt_execute($stmt_tracking)) {
            $result_tracking = mysqli_stmt_get_result($stmt_tracking);
            while ($row = mysqli_fetch_assoc($result_tracking)) {
                $tracking_history[] = $row;
            }
        } else {
            $_SESSION['error_message'] = "Error fetching tracking history: " . mysqli_error($conn);
            error_log("Error fetching tracking history: " . mysqli_error($conn));
        }
        mysqli_stmt_close($stmt_tracking);
    } else {
        $_SESSION['error_message'] = "Error preparing tracking history query.";
        error_log("Error preparing tracking history query: " . mysqli_error($conn));
    }

} else {
    $_SESSION['error_message'] = "Invalid request. No shipment ID provided.";
    header("location: " . BASE_URL . "shipments/index.php");
    exit();
}
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Shipment Details (ID: <?php echo htmlspecialchars($shipment['shipment_id']); ?>)</h2>
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

        <div class="card p-4 shadow-sm mb-4">
            <h4 class="card-title mb-3">Shipment Information</h4>
            <div class="row">
                <div class="col-md-6 mb-2">
                    <strong>Origin:</strong> <?php echo htmlspecialchars($shipment['origin_name']); ?> (<?php echo htmlspecialchars($shipment['origin_address']); ?>)
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Destination:</strong> <?php echo htmlspecialchars($shipment['destination_name']); ?> (<?php echo htmlspecialchars($shipment['destination_address']); ?>)
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Order ID:</strong>
                    <?php if ($order_id_linked): ?>
                        <a href="<?php echo BASE_URL; ?>orders/view.php?id=<?php echo htmlspecialchars($order_id_linked); ?>"><?php echo htmlspecialchars($order_id_linked); ?></a>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Status:</strong> <span class="badge bg-<?php
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
                    ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $shipment['status']))); ?></span>
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Planned Departure:</strong> <?php echo htmlspecialchars($shipment['planned_departure']); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Planned Arrival:</strong> <?php echo htmlspecialchars($shipment['planned_arrival']); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Actual Departure:</strong> <?php echo htmlspecialchars($shipment['actual_departure'] ?: 'N/A'); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Actual Arrival:</strong> <?php echo htmlspecialchars($shipment['actual_arrival'] ?: 'N/A'); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Vehicle:</strong> <?php echo htmlspecialchars($shipment['license_plate'] ? $shipment['license_plate'] . ' (' . $shipment['vehicle_type'] . ')' : 'N/A'); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Driver:</strong> <?php echo htmlspecialchars($shipment['first_name'] ? $shipment['first_name'] . ' ' . $shipment['last_name'] . ' (' . $shipment['phone_number'] . ')' : 'N/A'); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Total Weight:</strong> <?php echo htmlspecialchars($shipment['total_weight_kg'] ? $shipment['total_weight_kg'] . ' kg' : 'N/A'); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Total Volume:</strong> <?php echo htmlspecialchars($shipment['total_volume_m3'] ? $shipment['total_volume_m3'] . ' m³' : 'N/A'); ?>
                </div>
                <div class="col-12 mb-2">
                    <strong>Notes:</strong> <?php echo htmlspecialchars($shipment['notes'] ?: 'No notes.'); ?>
                </div>
            </div>
            <?php if (isset($created_at) || isset($updated_at)): ?>
            <div class="mt-3 border-top pt-3 text-muted small">
                Created: <?php echo htmlspecialchars($created_at); ?> by <?php echo htmlspecialchars($created_by_username ?: 'N/A'); ?><br>
                Last Updated: <?php echo htmlspecialchars($updated_at); ?> by <?php echo htmlspecialchars($updated_by_username ?: 'N/A'); ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card p-4 shadow-sm mb-4">
            <h4 class="card-title mb-3">Products in Shipment</h4>
            <?php if (!empty($shipment_products)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Product Name</th>
                                <th>Batch ID</th>
                                <th>Quantity</th>
                                <th>Packaging</th>
                                <th>Storage Req.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shipment_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['batch_id'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($product['quantity'] . ' ' . $product['unit']); ?></td>
                                    <td><?php echo htmlspecialchars($product['packaging_details'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($product['storage_requirements'] ?: 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No products associated with this shipment.</p>
            <?php endif; ?>
        </div>

        <div class="card p-4 shadow-sm mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="card-title">Tracking History</h4>
                <?php if ($_SESSION["role"] == 'admin' || $_SESSION["role"] == 'logistics_manager' || $_SESSION["role"] == 'driver'): ?>
                    <a href="<?php echo BASE_URL; ?>driver_interface/update_tracking.php?shipment_id=<?php echo htmlspecialchars($shipment['shipment_id']); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-plus"></i> Add Tracking Point</a>
                <?php endif; ?>
            </div>

            <?php if (!empty($tracking_history)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>Location (Lat, Long)</th>
                                <th>Temp (°C)</th>
                                <th>Humidity (%)</th>
                                <th>Update Notes</th>
                                <th>Map Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tracking_history as $track): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($track['timestamp']); ?></td>
                                    <td><?php echo htmlspecialchars($track['latitude'] . ', ' . $track['longitude']); ?></td>
                                    <td><?php echo htmlspecialchars($track['temperature'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($track['humidity'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($track['status_update'] ?: 'Location Update'); ?></td>
                                    <td>
                                        <?php if ($track['latitude'] && $track['longitude']): ?>
                                            <a href="https://maps.google.com/?q=<?php echo htmlspecialchars($track['latitude'] . ',' . $track['longitude']); ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="View on Google Maps">
                                                <i class="fas fa-map-marker-alt"></i> Map
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    <h5>Latest Known Location:</h5>
                    <?php if (!empty($tracking_history)): ?>
                        <p><strong>Lat:</strong> <?php echo htmlspecialchars($tracking_history[0]['latitude']); ?>, <strong>Long:</strong> <?php echo htmlspecialchars($tracking_history[0]['longitude']); ?></p>
                        <div id="map" style="height: 300px; background-color: #eee; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; color: #666;">
                            [Placeholder for Dynamic Map: Requires Google Maps JS API key and more complex JS]
                        </div>
                        <small class="text-muted mt-2 d-block">Integrating a dynamic map (like Google Maps JavaScript API) would require an API key and additional JavaScript. For now, coordinates are listed and a direct map link is provided.</small>
                    <?php else: ?>
                        <p>No tracking points logged yet for this shipment.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p>No tracking history available for this shipment yet. Use the "Add Tracking Point" button above to log new data.</p>
            <?php endif; ?>
        </div>


        <div class="mt-4 text-center">
            <?php if ($_SESSION["role"] == 'admin' || $_SESSION["role"] == 'logistics_manager'): ?>
                <a href="<?php echo BASE_URL; ?>shipments/edit.php?id=<?php echo htmlspecialchars($shipment['shipment_id']); ?>" class="btn btn-primary me-2"><i class="fas fa-edit"></i> Edit Shipment</a>
                <a href="<?php echo BASE_URL; ?>shipments/delete.php?id=<?php echo htmlspecialchars($shipment['shipment_id']); ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this shipment?');"><i class="fas fa-trash-alt"></i> Delete Shipment</a>
            <?php endif; ?>
             <?php if ($_SESSION["role"] == 'driver' || $_SESSION["role"] == 'logistics_manager' || $_SESSION["role"] == 'admin'): ?>
                 <a href="<?php echo BASE_URL; ?>shipments/update_status.php?id=<?php echo htmlspecialchars($shipment['shipment_id']); ?>" class="btn btn-warning ms-2"><i class="fas fa-clipboard-check"></i> Update Shipment Status</a>
            <?php endif; ?>
             <?php if ($_SESSION["role"] == 'admin' || $_SESSION["role"] == 'logistics_manager' || $_SESSION["role"] == 'driver'): ?>
                <a href="<?php echo BASE_URL; ?>driver_interface/update_tracking.php?shipment_id=<?php echo htmlspecialchars($shipment['shipment_id']); ?>" class="btn btn-info ms-2"><i class="fas fa-map-marker-alt"></i> Log New Tracking Point</a>
             <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>