<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/code_helpers.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
	header("location: " . BASE_URL . "index.php");
	exit;
}

// Allow admin, logistics_manager, and the customer who owns the order
$userRole = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'] ?? null;

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$asPdf = isset($_GET['pdf']) && $_GET['pdf'] == '1';
$autoPrint = isset($_GET['auto_print']) && $_GET['auto_print'] == '1';

if ($orderId <= 0) {
	echo 'Invalid order ID';
	exit;
}

// Access check for customer
if ($userRole === 'customer') {
	$sqlOwn = "SELECT customer_id FROM orders WHERE order_id = ?";
	if ($stmt = mysqli_prepare($conn, $sqlOwn)) {
		mysqli_stmt_bind_param($stmt, 'i', $orderId);
		mysqli_stmt_execute($stmt);
		$res = mysqli_stmt_get_result($stmt);
		$row = mysqli_fetch_assoc($res);
		mysqli_stmt_close($stmt);
		if (!$row || (int)$row['customer_id'] !== (int)$userId) {
			echo 'Unauthorized';
			exit;
		}
	}
}

// Fetch order
$order = null;
$sqlOrder = "SELECT o.order_id, o.order_date, o.total_amount, o.status, o.shipping_address,
                    u.username AS customer_name, u.phone AS customer_phone, u.email AS customer_email
             FROM orders o
             JOIN users u ON o.customer_id = u.user_id
             WHERE o.order_id = ?";
if ($stmt = mysqli_prepare($conn, $sqlOrder)) {
	mysqli_stmt_bind_param($stmt, 'i', $orderId);
	if (mysqli_stmt_execute($stmt)) {
		$res = mysqli_stmt_get_result($stmt);
		$order = mysqli_fetch_assoc($res);
	}
	mysqli_stmt_close($stmt);
}

if (!$order) { echo 'Order not found'; exit; }

// Fetch products
$items = [];
$subtotal = 0.0;
$sqlItems = "SELECT op.product_id, p.name AS product_name, op.quantity_kg, op.price_at_order
             FROM order_products op
             JOIN products p ON op.product_id = p.product_id
             WHERE op.order_id = ?";
if ($stmt = mysqli_prepare($conn, $sqlItems)) {
	mysqli_stmt_bind_param($stmt, 'i', $orderId);
	if (mysqli_stmt_execute($stmt)) {
		$res = mysqli_stmt_get_result($stmt);
		while ($row = mysqli_fetch_assoc($res)) {
			$qty = (float)$row['quantity_kg'];
			$price = (float)($row['price_at_order'] ?? 0);
			$total = $qty * $price;
			$subtotal += $total;
			$items[] = [
				'product_id' => (int)$row['product_id'],
				'name' => (string)$row['product_name'],
				'quantity' => $qty,
				'price' => $price,
				'total' => $total,
			];
		}
	}
	mysqli_stmt_close($stmt);
}

$orderCode = getOrderCode($order['order_id']);
$docTitle = 'POD - ' . $orderCode;

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($docTitle); ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		body { background: #fff; }
		.invoice { max-width: 900px; margin: 24px auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; }
		.invoice-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
		.brand { font-size: 24px; font-weight: 700; color: #2d6a4f; }
		.title { font-size: 20px; font-weight: 600; }
		.meta small { color: #6b7280; display: block; }
		.table th, .table td { vertical-align: middle; }
		.footer-note { color: #6b7280; font-size: 14px; }
		@media print {
			.no-print { display: none !important; }
			.invoice { border: 0; margin: 0; }
		}
	</style>
</head>
<body>
	<div class="invoice">
		<div class="invoice-header">
			<div>
				<div class="brand">Farm Flo</div>
				<div class="title">INVOICE</div>
			</div>
			<div class="text-end meta">
				<small>Invoice No:</small>
				<div class="fw-bold"><?php echo htmlspecialchars($orderCode); ?></div>
				<small>Date:</small>
				<div class="fw-bold"><?php echo htmlspecialchars(date('d/m/y', strtotime($order['order_date']))); ?></div>
			</div>
		</div>

		<div class="row mb-4">
			<div class="col-md-6">
				<h6 class="text-muted">Billing To</h6>
				<div class="fw-bold"><?php echo htmlspecialchars($order['customer_name']); ?></div>
				<div>Phone: <?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?></div>
				<div>Address: <?php echo htmlspecialchars($order['shipping_address'] ?? ''); ?></div>
			</div>
			<div class="col-md-6">
				<h6 class="text-muted">Sent From</h6>
				<div class="fw-bold">FarmFlow</div>
				<div>Phone: 01676225090</div>
				<div>Address: Uttara, Dhaka</div>
			</div>
		</div>

		<div class="mb-3">
			<svg id="barcode"></svg>
		</div>

		<div class="table-responsive">
			<table class="table table-bordered">
				<thead class="table-light">
					<tr>
						<th style="width:10%">ID</th>
						<th>Product</th>
						<th style="width:15%" class="text-end">Price</th>
						<th style="width:15%" class="text-end">Quantity</th>
						<th style="width:15%" class="text-end">Total</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($items as $it): ?>
					<tr>
						<td><?php echo htmlspecialchars(getProductCode($it['product_id'])); ?></td>
						<td><?php echo htmlspecialchars($it['name']); ?></td>
						<td class="text-end">৳<?php echo number_format($it['price'], 2); ?></td>
						<td class="text-end"><?php echo number_format($it['quantity'], 2); ?></td>
						<td class="text-end">৳<?php echo number_format($it['total'], 2); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="d-flex justify-content-end">
			<table class="table w-auto">
				<tr>
					<th class="text-end">Subtotal:</th>
					<td class="text-end">৳<?php echo number_format($subtotal, 2); ?></td>
				</tr>
				<tr>
					<th class="text-end">Total:</th>
					<td class="text-end">৳<?php echo number_format($subtotal, 2); ?></td>
				</tr>
			</table>
		</div>

		<div class="footer-note mt-4">
			Shipping note: 
		</div>

		<div class="no-print mt-3 d-flex gap-2">
			<a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>orders/view.php?id=<?php echo (int)$order['order_id']; ?>">Back to Order</a>
			<button class="btn btn-primary" onclick="window.print()">Print</button>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
	<script>
		try {
			JsBarcode('#barcode', '<?php echo addslashes($orderCode); ?>', {format: 'CODE128', lineColor: '#000', width: 2, height: 40, displayValue: true});
		} catch (e) { /* ignore */ }
		<?php if ($autoPrint): ?>
			window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 300); });
		<?php endif; ?>
		<?php if ($asPdf): ?>
			window.addEventListener('load', async function(){
				const { jsPDF } = window.jspdf;
				const node = document.querySelector('.invoice');
				const canvas = await html2canvas(node, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
				const imgData = canvas.toDataURL('image/png');
				const pdf = new jsPDF('p', 'pt', 'a4');
				const pageWidth = pdf.internal.pageSize.getWidth();
				const pageHeight = pdf.internal.pageSize.getHeight();
				const imgWidth = pageWidth - 40; // margins
				const imgHeight = canvas.height * imgWidth / canvas.width;
				let y = 20;
				if (imgHeight <= pageHeight - 40) {
					pdf.addImage(imgData, 'PNG', 20, y, imgWidth, imgHeight);
				} else {
					// paginate
					let remaining = imgHeight;
					let position = 20;
					const pageCanvas = document.createElement('canvas');
					const ctx = pageCanvas.getContext('2d');
					const ratio = imgWidth / canvas.width;
					pageCanvas.width = canvas.width;
					pageCanvas.height = Math.floor((pageHeight - 40) / ratio);
					while (remaining > 0) {
						ctx.clearRect(0,0,pageCanvas.width,pageCanvas.height);
						ctx.drawImage(canvas, 0, (canvas.height - remaining/ratio), canvas.width, pageCanvas.height, 0, 0, pageCanvas.width, pageCanvas.height);
						const pageImg = pageCanvas.toDataURL('image/png');
						pdf.addImage(pageImg, 'PNG', 20, 20, imgWidth, pageHeight - 40);
						remaining -= (pageHeight - 40) * ratio;
						if (remaining > 0) pdf.addPage();
					}
				}
				pdf.save('<?php echo addslashes($docTitle); ?>.pdf');
			});
		<?php endif; ?>
	</script>
</body>
</html>


