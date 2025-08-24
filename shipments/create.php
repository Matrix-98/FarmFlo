<?php
require_once '../config/db.php';
require_once '../utils/id_generator.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin' && $_SESSION["role"] != 'logistics_manager') {
    $_SESSION['error_message'] = "You do not have permission to create shipments.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Create Shipment";
$current_page = "shipments";

$origin_location_id = $destination_location_id = $vehicle_id = $driver_id = $order_id = "";
$planned_departure = $planned_arrival = $total_weight_kg = $total_volume_m3 = $notes = "";

$origin_location_id_err = $destination_location_id_err = $planned_departure_err = $planned_arrival_err = $driver_id_err = "";

$locations_options = [];
$sql_locations = "SELECT location_id, name, type FROM locations ORDER BY name ASC";
if ($result_locations = mysqli_query($conn, $sql_locations)) {
    while ($row = mysqli_fetch_assoc($result_locations)) {
        $locations_options[] = $row;
    }
    mysqli_free_result($result_locations);
}

$vehicles_options = [];
$sql_vehicles = "SELECT vehicle_id, license_plate, type FROM vehicles WHERE status = 'available' ORDER BY license_plate ASC";
if ($result_vehicles = mysqli_query($conn, $sql_vehicles)) {
    while ($row = mysqli_fetch_assoc($result_vehicles)) {
        $vehicles_options[] = $row;
    }
    mysqli_free_result($result_vehicles);
}

$drivers_options = [];
$sql_drivers = "SELECT driver_id, first_name, last_name FROM drivers WHERE status = 'active' ORDER BY first_name ASC";
if ($result_drivers = mysqli_query($conn, $sql_drivers)) {
    while ($row = mysqli_fetch_assoc($result_drivers)) {
        $drivers_options[] = $row;
    }
    mysqli_free_result($result_drivers);
}



$orders_options = [];
$sql_orders = "SELECT o.order_id, o.order_code, u.username, o.shipping_address FROM orders o LEFT JOIN shipments s ON o.order_id = s.order_id JOIN users u ON o.customer_id = u.user_id WHERE s.order_id IS NULL ORDER BY o.order_id DESC";
if ($result_orders = mysqli_query($conn, $sql_orders)) {
    while ($row = mysqli_fetch_assoc($result_orders)) {
        $orders_options[] = $row;
    }
    mysqli_free_result($result_orders);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Assign order_id first so it's available for validation
    $order_id = !empty($_POST["order_id"]) ? $_POST["order_id"] : NULL;

    if (empty(trim($_POST["origin_location_id"]))) {
        $origin_location_id_err = "Please select an origin location.";
    } else {
        $origin_location_id = trim($_POST["origin_location_id"]);
    }

    if (empty(trim($_POST["destination_location_id"]))) {
        $destination_location_id_err = "Please select a destination location.";
    } else {
        $destination_location_id = trim($_POST["destination_location_id"]);
    }

    // Handle order address case
    if ($destination_location_id === 'order_address') {
        if (empty($order_id)) {
            $destination_location_id_err = "Order address can only be used when an order is selected.";
        } else {
            // Get the order's shipping address and add it to notes
            $order_address = '';
            foreach ($orders_options as $order) {
                if ($order['order_id'] == $order_id) {
                    $order_address = $order['shipping_address'];
                    break;
                }
            }
            
            // Add order address to notes
            $notes .= "\n\nOrder Delivery Address: " . $order_address;
            
            // Use a default delivery point location (we'll create one if it doesn't exist)
            $sql_check_delivery = "SELECT location_id FROM locations WHERE type = 'delivery_point' AND name = 'Customer Delivery Address' LIMIT 1";
            $result_delivery = mysqli_query($conn, $sql_check_delivery);
            if ($result_delivery && mysqli_num_rows($result_delivery) > 0) {
                $row = mysqli_fetch_assoc($result_delivery);
                $destination_location_id = $row['location_id'];
            } else {
                // Create a default delivery point location
                $sql_create_delivery = "INSERT INTO locations (name, address, type, created_by) VALUES ('Customer Delivery Address', 'Dynamic delivery address from order', 'delivery_point', ?)";
                if ($stmt_create_delivery = mysqli_prepare($conn, $sql_create_delivery)) {
                    mysqli_stmt_bind_param($stmt_create_delivery, "i", $logged_in_user_id);
                    if (mysqli_stmt_execute($stmt_create_delivery)) {
                        $destination_location_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($stmt_create_delivery);
                    } else {
                        $destination_location_id_err = "Error creating delivery location.";
                    }
                } else {
                    $destination_location_id_err = "Error preparing delivery location creation.";
                }
            }
        }
    } elseif ($origin_location_id == $destination_location_id && !empty($origin_location_id) && !empty($destination_location_id)) {
        $destination_location_id_err = "Origin and Destination cannot be the same.";
    }

    if (empty(trim($_POST["planned_departure"]))) {
        $planned_departure_err = "Please enter a planned departure date/time.";
    } else {
        $planned_departure = trim($_POST["planned_departure"]);
    }

    if (empty(trim($_POST["planned_arrival"]))) {
        $planned_arrival_err = "Please enter a planned arrival date/time.";
    } else {
        $planned_arrival = trim($_POST["planned_arrival"]);
        if (!empty($planned_departure) && strtotime($planned_arrival) <= strtotime($planned_departure)) {
            $planned_arrival_err = "Planned arrival must be after planned departure.";
        }
    }

    $vehicle_id = !empty($_POST["vehicle_id"]) ? $_POST["vehicle_id"] : NULL;
    
    if (empty(trim($_POST["driver_id"]))) {
        $driver_id_err = "Driver assignment is mandatory for shipments.";
    } else {
        $driver_id = trim($_POST["driver_id"]);
    }
    $total_weight_kg = !empty($_POST["total_weight_kg"]) && is_numeric($_POST["total_weight_kg"]) ? $_POST["total_weight_kg"] : NULL;
    $total_volume_m3 = !empty($_POST["total_volume_m3"]) && is_numeric($_POST["total_volume_m3"]) ? $_POST["total_volume_m3"] : NULL;
    $notes = trim($_POST["notes"]);



    // Get logged in user ID early for use in validation
    $logged_in_user_id = $_SESSION['user_id'];
    
    if (empty($origin_location_id_err) && empty($destination_location_id_err) && empty($planned_departure_err) && empty($planned_arrival_err) && empty($driver_id_err)) {

        mysqli_begin_transaction($conn);
        $param_status = 'pending';

        try {
            $shipment_code = generateShipmentId();
            $sql_shipment = "INSERT INTO shipments (shipment_code, origin_location_id, destination_location_id, vehicle_id, driver_id, order_id, planned_departure, planned_arrival, total_weight_kg, total_volume_m3, notes, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_shipment = mysqli_prepare($conn, $sql_shipment)) {
                
                // NEW: Use a dynamic array for binding parameters
                $bind_params_shipment = [
                    $shipment_code,
                    $origin_location_id,
                    $destination_location_id,
                    $vehicle_id,
                    $driver_id,
                    $order_id,
                    $planned_departure,
                    $planned_arrival,
                    $total_weight_kg,
                    $total_volume_m3,
                    $notes,
                    $param_status,
                    $logged_in_user_id
                ];

                // NEW: Use a dynamic type string based on the parameters
                $bind_types_shipment = "";
                foreach ($bind_params_shipment as $param) {
                    if (is_int($param)) {
                        $bind_types_shipment .= 'i';
                    } elseif (is_double($param)) {
                        $bind_types_shipment .= 'd';
                    } elseif (is_string($param)) {
                        $bind_types_shipment .= 's';
                    } else {
                        // Default to string for NULLs or other types
                        $bind_types_shipment .= 's';
                    }
                }
                
                // Final bind call using dynamic arrays
                if (mysqli_stmt_bind_param($stmt_shipment, $bind_types_shipment, ...$bind_params_shipment)) {
                    if (!mysqli_stmt_execute($stmt_shipment)) {
                        throw new Exception("Error creating shipment: " . mysqli_error($conn));
                    }
                    $shipment_id = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt_shipment);
                } else {
                    throw new Exception("Error preparing shipment bind parameters: " . mysqli_error($conn));
                }
                
            } else {
                throw new Exception("Error preparing shipment insert statement: " . mysqli_error($conn));
            }



            if ($order_id) {
                $sql_update_order = "UPDATE orders SET status = 'shipped', updated_by = ? WHERE order_id = ?";
                if ($stmt_update_order = mysqli_prepare($conn, $sql_update_order)) {
                    mysqli_stmt_bind_param($stmt_update_order, "ii", $logged_in_user_id, $order_id);
                    if (!mysqli_stmt_execute($stmt_update_order)) {
                         error_log("Error updating order status after shipment creation: " . mysqli_error($conn));
                    }
                    mysqli_stmt_close($stmt_update_order);
                } else {
                     error_log("Error preparing order status update after shipment creation: " . mysqli_error($conn));
                }
            }
            
            mysqli_commit($conn);
            
            // Get order code if order_id exists
            $order_code = 'N/A';
            if ($order_id) {
                $sql_order_code = "SELECT order_code FROM orders WHERE order_id = ?";
                if ($stmt_order_code = mysqli_prepare($conn, $sql_order_code)) {
                    mysqli_stmt_bind_param($stmt_order_code, "i", $order_id);
                    mysqli_stmt_execute($stmt_order_code);
                    $result_order_code = mysqli_stmt_get_result($stmt_order_code);
                    if ($row_order_code = mysqli_fetch_assoc($result_order_code)) {
                        $order_code = $row_order_code['order_code'];
                    }
                    mysqli_stmt_close($stmt_order_code);
                }
            }
            
            $_SESSION['success_message'] = "Shipment created successfully for Order: " . $order_code . " with Shipment: " . $shipment_code;
            header("location: " . BASE_URL . "shipments/index.php");
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error_message'] = "Transaction failed: " . $e->getMessage();
            error_log("Shipment creation failed: " . $e->getMessage());
        }
    }
}

?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="mb-4">Create New Shipment</h2>
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
                <div class="mb-3">
                    <label for="order_id" class="form-label">Link to Order (Optional)</label>
                    <select name="order_id" id="order_id" class="form-select">
                        <option value="">Select an Unassigned Order</option>
                        <?php foreach ($orders_options as $order): ?>
                            <option value="<?php echo htmlspecialchars($order['order_id']); ?>" 
                                    data-address="<?php echo htmlspecialchars($order['shipping_address']); ?>"
                                    <?php echo ($order_id == $order['order_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($order['order_code']); ?> (Customer: <?php echo htmlspecialchars($order['username']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">A shipment will automatically be created in 'pending' status for the selected order. You can optionally add more products below.</small>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="origin_location_id" class="form-label">Origin Location <span class="text-danger">*</span></label>
                        <select name="origin_location_id" id="origin_location_id" class="form-select <?php echo (!empty($origin_location_id_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Origin</option>
                            <?php foreach ($locations_options as $location): ?>
                                <option value="<?php echo htmlspecialchars($location['location_id']); ?>" <?php echo ($origin_location_id == $location['location_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location['name']); ?> (<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $location['type']))); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $origin_location_id_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="destination_location_id" class="form-label">Destination Location <span class="text-danger">*</span></label>
                        <select name="destination_location_id" id="destination_location_id" class="form-select <?php echo (!empty($destination_location_id_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Destination</option>
                            <option value="order_address" id="order_address_option" style="display: none;">
                                📦 Order Delivery Address (will be set automatically)
                            </option>
                            <?php foreach ($locations_options as $location): ?>
                                <option value="<?php echo htmlspecialchars($location['location_id']); ?>" <?php echo ($destination_location_id == $location['location_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location['name']); ?> (<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $location['type']))); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $destination_location_id_err; ?></div>
                        <small class="form-text text-muted">When an order is selected, the order's delivery address will be automatically used as destination.</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="planned_departure" class="form-label">Planned Departure <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="planned_departure" id="planned_departure" class="form-control <?php echo (!empty($planned_departure_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($planned_departure); ?>">
                        <div class="invalid-feedback"><?php echo $planned_departure_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="planned_arrival" class="form-label">Planned Arrival <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="planned_arrival" id="planned_arrival" class="form-control <?php echo (!empty($planned_arrival_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($planned_arrival); ?>">
                        <div class="invalid-feedback"><?php echo $planned_arrival_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="vehicle_id" class="form-label">Assign Vehicle</label>
                        <select name="vehicle_id" id="vehicle_id" class="form-select">
                            <option value="">Select Vehicle (Optional)</option>
                            <?php foreach ($vehicles_options as $vehicle): ?>
                                <option value="<?php echo htmlspecialchars($vehicle['vehicle_id']); ?>" <?php echo ($vehicle_id == $vehicle['vehicle_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vehicle['license_plate']); ?> (<?php echo htmlspecialchars($vehicle['type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="driver_id" class="form-label">Assign Driver <span class="text-danger">*</span></label>
                        <select name="driver_id" id="driver_id" class="form-select <?php echo (!empty($driver_id_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Driver</option>
                            <?php foreach ($drivers_options as $driver): ?>
                                <option value="<?php echo htmlspecialchars($driver['driver_id']); ?>" <?php echo ($driver_id == $driver['driver_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $driver_id_err; ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="total_weight_kg" class="form-label">Total Estimated Weight (kg)</label>
                        <input type="number" name="total_weight_kg" id="total_weight_kg" class="form-control" value="<?php echo htmlspecialchars($total_weight_kg); ?>" step="0.01">
                        <small class="form-text text-muted">Leave empty or set to 0 if unknown/not applicable.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="total_volume_m3" class="form-label">Total Estimated Volume (m³)</label>
                        <input type="number" name="total_volume_m3" id="total_volume_m3" class="form-control" value="<?php echo htmlspecialchars($total_volume_m3); ?>" step="0.01">
                        <small class="form-text text-muted">Leave empty or set to 0 if unknown/not applicable.</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3"><?php echo htmlspecialchars($notes); ?></textarea>
                </div>



                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success mt-3"><i class="fas fa-truck-loading"></i> Create Shipment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Order address functionality
        const orderSelect = document.getElementById('order_id');
        const destinationSelect = document.getElementById('destination_location_id');
        const orderAddressOption = document.getElementById('order_address_option');
        
        orderSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const orderAddress = selectedOption.getAttribute('data-address');
            
            if (this.value && orderAddress) {
                // Show the order address option and select it
                orderAddressOption.style.display = 'block';
                orderAddressOption.textContent = `📦 Order #${this.value} Address: ${orderAddress}`;
                destinationSelect.value = 'order_address';
                
                // Add visual indication
                destinationSelect.classList.add('border-success', 'bg-light');
                
                // Disable other destination options
                Array.from(destinationSelect.options).forEach(option => {
                    if (option.value !== 'order_address' && option.value !== '') {
                        option.disabled = true;
                    }
                });
                
                // Show success message
                const successMsg = document.createElement('div');
                successMsg.className = 'alert alert-success mt-2';
                successMsg.innerHTML = `<i class="fas fa-check-circle"></i> Order delivery address will be automatically used as destination.`;
                destinationSelect.parentNode.appendChild(successMsg);
                
                // Remove any existing success message
                setTimeout(() => {
                    const existingMsg = destinationSelect.parentNode.querySelector('.alert-success');
                    if (existingMsg) {
                        existingMsg.remove();
                    }
                }, 5000);
                
            } else {
                // Hide the order address option and enable all options
                orderAddressOption.style.display = 'none';
                destinationSelect.value = '';
                
                // Remove visual indication
                destinationSelect.classList.remove('border-success', 'bg-light');
                
                // Enable all destination options
                Array.from(destinationSelect.options).forEach(option => {
                    option.disabled = false;
                });
                
                // Remove any existing success message
                const existingMsg = destinationSelect.parentNode.querySelector('.alert-success');
                if (existingMsg) {
                    existingMsg.remove();
                }
            }
        });
        
        // Initialize on page load if order is already selected
        if (orderSelect.value) {
            orderSelect.dispatchEvent(new Event('change'));
        }
    });
</script>