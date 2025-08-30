<?php
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

// Access control: Admin and Logistics Manager can manage documents. Others can view.
if (!in_array($_SESSION["role"], ['admin', 'logistics_manager', 'farm_manager', 'warehouse_manager', 'driver', 'customer'])) {
    $_SESSION['error_message'] = "You do not have permission to view documents.";
    header("location: ../dashboard.php");
    exit;
}

$page_title = "Manage Documents";
$current_page = "documents"; // Set to documents for correct sidebar highlighting

$shipment_id = null;
$documents = [];
$shipment_details = null;

if (isset($_GET['shipment_id']) && !empty(trim($_GET['shipment_id']))) {
    $shipment_id = trim($_GET['shipment_id']);

    // Fetch shipment details for header
    $sql_shipment = "SELECT s.shipment_id, ol.name AS origin_name, dl.name AS destination_name
                     FROM shipments s
                     JOIN locations ol ON s.origin_location_id = ol.location_id
                     JOIN locations dl ON s.destination_location_id = dl.location_id
                     WHERE s.shipment_id = ?";
    if ($stmt_shipment = mysqli_prepare($conn, $sql_shipment)) {
        mysqli_stmt_bind_param($stmt_shipment, "i", $param_shipment_id);
        $param_shipment_id = $shipment_id;
        if (mysqli_stmt_execute($stmt_shipment)) {
            $result_shipment = mysqli_stmt_get_result($stmt_shipment);
            $shipment_details = mysqli_fetch_assoc($result_shipment);
        }
        mysqli_stmt_close($stmt_shipment);
    }

    // Fetch documents for this shipment
    $sql_docs = "SELECT d.document_id, d.document_type, d.file_name, d.upload_date, u.username AS uploaded_by_username
                 FROM documents d
                 JOIN users u ON d.uploaded_by = u.user_id
                 WHERE d.shipment_id = ?
                 ORDER BY d.upload_date DESC";
    
    if ($stmt_docs = mysqli_prepare($conn, $sql_docs)) {
        mysqli_stmt_bind_param($stmt_docs, "i", $param_shipment_id);
        $param_shipment_id = $shipment_id;
        if (mysqli_stmt_execute($stmt_docs)) {
            $result_docs = mysqli_stmt_get_result($stmt_docs);
            while ($row = mysqli_fetch_assoc($result_docs)) {
                $documents[] = $row;
            }
            mysqli_free_result($result_docs);
        } else {
            $_SESSION['error_message'] = "Error fetching documents: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt_docs);
    } else {
        $_SESSION['error_message'] = "Error preparing document query.";
    }

} else {
    $_SESSION['error_message'] = "No shipment ID provided to view documents.";
    header("location: ../shipments/index.php"); // Redirect if no shipment ID
    exit;
}
mysqli_close($conn);
?>

<?php include '../includes/head.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Documents for Shipment ID: <?php echo htmlspecialchars($shipment_id); ?></h2>
            <?php if ($shipment_details): ?>
                <p class="lead mb-0">From: <?php echo htmlspecialchars($shipment_details['origin_name']); ?> To: <?php echo htmlspecialchars($shipment_details['destination_name']); ?></p>
            <?php endif; ?>
        </div>
        <a href="../shipments/view.php?id=<?php echo htmlspecialchars($shipment_id); ?>" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Shipment Details</a>

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

        <?php if (in_array($_SESSION["role"], ['admin', 'logistics_manager'])): ?>
            <div class="card p-4 shadow-sm mb-4">
                <h4 class="card-title mb-3">Upload New Document</h4>
                <form action="upload.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="shipment_id" value="<?php echo htmlspecialchars($shipment_id); ?>">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="document_type" class="form-label">Document Type <span class="text-danger">*</span></label>
                            <select name="document_type" id="document_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="invoice">Invoice</option>
                                <option value="bill_of_lading">Bill of Lading</option>
                                <option value="customs_declaration">Customs Declaration</option>
                                <option value="quality_certificate">Quality Certificate</option>
                                <option value="packing_list">Packing List</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="document_file" class="form-label">Select File <span class="text-danger">*</span></label>
                            <input type="file" name="document_file" id="document_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.png" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Document</button>
                </form>
            </div>
        <?php endif; ?>

        <h4 class="mt-4 mb-3">Uploaded Documents</h4>
        <?php if (!empty($documents)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>File Name</th>
                            <th>Uploaded By</th>
                            <th>Upload Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doc['document_id']); ?></td>
                                <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $doc['document_type']))); ?></td>
                                <td><?php echo htmlspecialchars($doc['file_name']); ?></td>
                                <td><?php echo htmlspecialchars($doc['uploaded_by_username']); ?></td>
                                <td><?php echo htmlspecialchars($doc['upload_date']); ?></td>
                                <td>
                                    <a href="download.php?id=<?php echo $doc['document_id']; ?>" class="btn btn-sm btn-info me-2" title="Download"><i class="fas fa-download"></i></a>
                                    <?php if (in_array($_SESSION["role"], ['admin', 'logistics_manager'])): ?>
                                        <a href="delete.php?id=<?php echo $doc['document_id']; ?>&shipment_id=<?php echo $shipment_id; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this document?');"><i class="fas fa-trash-alt"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No documents found for this shipment.</div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>