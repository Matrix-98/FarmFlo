<?php
require_once __DIR__ . '/../config/db.php';

// Auth guard
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
	header('Location: ' . BASE_URL . 'index.php');
	exit;
}

// Only admin can manage static chart values
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
	$_SESSION['error_message'] = 'You do not have permission to access Chart Values.';
	header('Location: ' . BASE_URL . 'dashboard.php');
	exit;
}

$page_title = 'Static Chart Values';
$current_page = 'values';

// Utility: sanitize and validate inputs
function getPostString(string $key, bool $allowEmpty = false): ?string {
	if (!isset($_POST[$key])) {
		return null;
	}
	$value = trim((string)$_POST[$key]);
	if ($value === '' && !$allowEmpty) {
		return null;
	}
	return $value;
}

function getPostNumber(string $key): ?float {
	if (!isset($_POST[$key])) {
		return null;
	}
	$value = trim((string)$_POST[$key]);
	if ($value === '' || !is_numeric($value)) {
		return null;
	}
	return (float)$value;
}

// Handle add entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_entry') {
	$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['ajax']) && $_POST['ajax'] === '1');
	$chartKey = getPostString('chart_key');
	$label = getPostString('label');
	$series = getPostString('series', true);
	$value = getPostNumber('value');

	if (!$chartKey || !$label || $value === null) {
		if ($isAjax) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'error' => 'Please provide all required fields.']);
			exit;
		} else {
			$_SESSION['error_message'] = 'Please provide all required fields.';
			header('Location: ' . BASE_URL . 'admin/values.php#' . urlencode((string)$chartKey));
			exit;
		}
	}

	$sql = 'INSERT INTO chart_values (chart_key, label, series, value) VALUES (?, ?, ?, ?)';
	if ($stmt = mysqli_prepare($conn, $sql)) {
		mysqli_stmt_bind_param($stmt, 'sssd', $chartKey, $label, $series, $value);
		if (mysqli_stmt_execute($stmt)) {
			if ($isAjax) {
				$id = mysqli_insert_id($conn);
				$entry = null;
				if ($id > 0) {
					if ($stmt2 = mysqli_prepare($conn, 'SELECT id, chart_key, label, series, value, updated_at FROM chart_values WHERE id = ?')) {
						mysqli_stmt_bind_param($stmt2, 'i', $id);
						if (mysqli_stmt_execute($stmt2)) {
							$res = mysqli_stmt_get_result($stmt2);
							$entry = mysqli_fetch_assoc($res);
						}
						mysqli_stmt_close($stmt2);
					}
				}
				header('Content-Type: application/json');
				echo json_encode(['success' => true, 'entry' => $entry]);
				exit;
			} else {
				$_SESSION['success_message'] = 'Value added successfully.';
			}
		} else {
			if ($isAjax) {
				header('Content-Type: application/json');
				echo json_encode(['success' => false, 'error' => 'Failed to add value.']);
				exit;
			} else {
				$_SESSION['error_message'] = 'Failed to add value.';
			}
		}
		mysqli_stmt_close($stmt);
	} else {
		if ($isAjax) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'error' => 'Failed to prepare statement.']);
			exit;
		} else {
			$_SESSION['error_message'] = 'Failed to prepare statement.';
		}
	}

	if (!$isAjax) {
		header('Location: ' . BASE_URL . 'admin/values.php#' . urlencode((string)$chartKey));
		exit;
	}
}

// Handle delete entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_entry') {
	$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['ajax']) && $_POST['ajax'] === '1');
	$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
	$chartKey = getPostString('chart_key', true) ?? '';
	if ($id <= 0 && $isAjax) {
		header('Content-Type: application/json');
		echo json_encode(['success' => false, 'error' => 'Invalid ID']);
		exit;
	}

	if ($id > 0) {
		$sql = 'DELETE FROM chart_values WHERE id = ?';
		if ($stmt = mysqli_prepare($conn, $sql)) {
			mysqli_stmt_bind_param($stmt, 'i', $id);
			if (mysqli_stmt_execute($stmt)) {
				if ($isAjax) {
					header('Content-Type: application/json');
					echo json_encode(['success' => true]);
					exit;
				} else {
					$_SESSION['success_message'] = 'Value deleted successfully.';
				}
			} else {
				if ($isAjax) {
					header('Content-Type: application/json');
					echo json_encode(['success' => false, 'error' => 'Failed to delete value.']);
					exit;
				} else {
					$_SESSION['error_message'] = 'Failed to delete value.';
				}
			}
			mysqli_stmt_close($stmt);
		} else {
			if ($isAjax) {
				header('Content-Type: application/json');
				echo json_encode(['success' => false, 'error' => 'Failed to prepare statement.']);
				exit;
			} else {
				$_SESSION['error_message'] = 'Failed to prepare statement.';
			}
		}
	}
	if (!$isAjax) {
		header('Location: ' . BASE_URL . 'admin/values.php#' . urlencode((string)$chartKey));
		exit;
	}
}

// Fetch entries for a given chart key
function fetchChartEntries(mysqli $conn, string $chartKey): array {
	$entries = [];
	$sql = 'SELECT id, label, series, value, updated_at FROM chart_values WHERE chart_key = ? ORDER BY updated_at DESC, id DESC';
	if ($stmt = mysqli_prepare($conn, $sql)) {
		mysqli_stmt_bind_param($stmt, 's', $chartKey);
		if (mysqli_stmt_execute($stmt)) {
			$result = mysqli_stmt_get_result($stmt);
			while ($row = mysqli_fetch_assoc($result)) {
				$entries[] = $row;
			}
		}
		mysqli_stmt_close($stmt);
	}
	return $entries;
}

// Define chart keys and labels
$charts = [
	'inventory_by_location' => [
		'title' => 'Inventory Stock Levels by Location',
		'description' => 'Bar chart: location vs total quantity (kg).',
		'show_series' => false,
		'label_placeholder' => 'Location name',
		'value_placeholder' => 'Total quantity (kg)'
	],
	'top_products' => [
		'title' => 'Top 5 Best-Selling Products',
		'description' => 'Bar chart: product vs total amount sold (Taka).',
		'show_series' => false,
		'label_placeholder' => 'Product name',
		'value_placeholder' => 'Total amount sold (৳)'
	],
	'monthly_shipment_volume' => [
		'title' => 'Monthly Shipment Volume',
		'description' => 'Line chart: month vs shipment count.',
		'show_series' => false,
		'label_placeholder' => 'Month (e.g., January)',
		'value_placeholder' => 'Shipment count'
	],
	'driver_delivery_performance' => [
		'title' => 'Delivery Performance by Driver',
		'description' => 'Bar chart: two series (on_time, delayed) per driver.',
		'show_series' => true,
		'label_placeholder' => 'Driver name',
		'value_placeholder' => 'Count'
	],
	'inventory_value_by_stage' => [
		'title' => 'Inventory Value by Stage',
		'description' => 'Pie chart: value by stage (storage, in transit, damaged, spoiled).',
		'show_series' => false,
		'label_placeholder' => 'Stage name',
		'value_placeholder' => 'Total value (৳)'
	]
];

// Preload entries per chart
$entriesByChart = [];
foreach ($charts as $key => $_meta) {
	$entriesByChart[$key] = fetchChartEntries($conn, $key);
}
?>
<?php
// Lightweight AJAX endpoint to list entries for a chart key
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'list') {
	$chartKey = isset($_GET['chart_key']) ? trim((string)$_GET['chart_key']) : '';
	header('Content-Type: application/json');
	if (!isset($charts[$chartKey])) {
		echo json_encode(['success' => false, 'error' => 'Invalid chart key']);
		exit;
	}
	$entries = [];
	$sql = 'SELECT id, label, series, value, updated_at FROM chart_values WHERE chart_key = ? ORDER BY updated_at DESC, id DESC';
	if ($stmt = mysqli_prepare($conn, $sql)) {
		mysqli_stmt_bind_param($stmt, 's', $chartKey);
		if (mysqli_stmt_execute($stmt)) {
			$result = mysqli_stmt_get_result($stmt);
			while ($row = mysqli_fetch_assoc($result)) {
				$entries[] = $row;
			}
		}
		mysqli_stmt_close($stmt);
	}
	echo json_encode(['success' => true, 'entries' => $entries]);
	exit;
}
?>

<?php include __DIR__ . '/../includes/head.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content">
	<?php include __DIR__ . '/../includes/navbar.php'; ?>

	<div class="container-fluid mt-4">
		<h2 class="mb-3">Static Chart Values</h2>
		<p class="text-muted">Manage input values for charts 3–7 on the Reports page. Changes here will be used to render the static charts. Charts 1 and 2 remain dynamic and are not affected.</p>

		<?php if (!empty($_SESSION['success_message'])): ?>
			<div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
		<?php endif; ?>
		<?php if (!empty($_SESSION['error_message'])): ?>
			<div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
		<?php endif; ?>

		<div class="card mb-4">
			<div class="card-header">
				<h5 class="mb-0">Manage Chart Values</h5>
			</div>
			<div class="card-body">
				<div class="row g-3 align-items-end">
					<div class="col-md-4">
						<label class="form-label">Chart</label>
						<select id="chartKey" class="form-select">
							<?php foreach ($charts as $ckey => $cmeta): ?>
								<option value="<?php echo htmlspecialchars($ckey); ?>"><?php echo htmlspecialchars($cmeta['title']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-8">
						<div id="chartDesc" class="text-muted small"></div>
					</div>
				</div>
				<hr>
				<form id="unifiedForm" action="<?php echo BASE_URL; ?>admin/values_api.php" method="post" onsubmit="return submitUnifiedAjax(this);">
					<input type="hidden" name="action" value="add_entry">
					<input type="hidden" name="chart_key" id="formChartKey" value="">
					<input type="hidden" name="ajax" value="1">
					<div class="row g-3">
						<div class="col-md-5">
							<label class="form-label">Label</label>
							<input type="text" name="label" id="formLabel" class="form-control" required>
						</div>
						<div class="col-md-3" id="seriesWrapper" style="display:none;">
							<label class="form-label">Series</label>
							<select name="series" id="formSeries" class="form-select">
								<option value="on_time">On Time</option>
								<option value="delayed">Delayed</option>
							</select>
						</div>
						<div class="col-md-3">
							<label class="form-label">Value</label>
							<input type="number" name="value" id="formValue" class="form-control" step="0.01" required>
						</div>
						<div class="col-md-2">
							<button type="submit" class="btn btn-primary btn-sm w-100">Add</button>
						</div>
					</div>
				</form>
			</div>
		</div>

		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="mb-0">Existing Values</h5>
				<small class="text-muted">Most recent first</small>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-sm align-middle" id="valuesTable">
						<thead>
							<tr>
								<th style="width:35%">Label</th>
								<th style="width:20%" id="thSeries">Series</th>
								<th style="width:20%">Value</th>
								<th style="width:20%">Updated</th>
								<th style="width:5%"></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="accordion" id="chartValuesAccordion" style="display:none;">
			<?php $first = true; foreach ($charts as $key => $meta): ?>
				<div class="accordion-item" id="item-<?php echo htmlspecialchars($key); ?>">
					<h2 class="accordion-header" id="heading-<?php echo htmlspecialchars($key); ?>">
						<button class="accordion-button <?php echo $first ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo htmlspecialchars($key); ?>" aria-expanded="<?php echo $first ? 'true' : 'false'; ?>" aria-controls="collapse-<?php echo htmlspecialchars($key); ?>">
							<?php echo htmlspecialchars($meta['title']); ?>
						</button>
					</h2>
					<div id="collapse-<?php echo htmlspecialchars($key); ?>" class="accordion-collapse collapse <?php echo $first ? 'show' : ''; ?>" aria-labelledby="heading-<?php echo htmlspecialchars($key); ?>" data-bs-parent="#chartValuesAccordion">
						<div class="accordion-body">
							<div class="row">
								<div class="col-lg-5 mb-4">
									<div class="card h-100">
										<div class="card-header">
											<h5 class="mb-0">Add Value</h5>
											<small class="text-muted"><?php echo htmlspecialchars($meta['description']); ?></small>
										</div>
										<div class="card-body">
											<form method="post" action="<?php echo BASE_URL; ?>admin/values.php#<?php echo htmlspecialchars($key); ?>" onsubmit="return submitChartValueAjax(this, '<?php echo htmlspecialchars($key); ?>');">
												<input type="hidden" name="action" value="add_entry">
												<input type="hidden" name="chart_key" value="<?php echo htmlspecialchars($key); ?>">
												<input type="hidden" name="ajax" value="1">

												<div class="mb-3">
													<label class="form-label">Label</label>
													<input type="text" name="label" class="form-control" placeholder="<?php echo htmlspecialchars($meta['label_placeholder']); ?>" required>
												</div>

												<?php if ($meta['show_series']): ?>
													<div class="mb-3">
														<label class="form-label">Series</label>
														<select name="series" class="form-select" required>
															<option value="on_time">On Time</option>
															<option value="delayed">Delayed</option>
														</select>
													</div>
												<?php else: ?>
													<input type="hidden" name="series" value="">
												<?php endif; ?>

												<div class="mb-3">
													<label class="form-label">Value</label>
													<input type="number" name="value" class="form-control" step="0.01" placeholder="<?php echo htmlspecialchars($meta['value_placeholder']); ?>" required>
												</div>

												<button type="submit" class="btn btn-primary">Add</button>
											</form>
										</div>
									</div>
								</div>
								<div class="col-lg-7 mb-4">
									<div class="card h-100">
										<div class="card-header d-flex justify-content-between align-items-center">
											<h5 class="mb-0">Existing Values</h5>
											<span class="text-muted small">Most recent first</span>
										</div>
										<div class="card-body">
											<?php $entries = $entriesByChart[$key]; ?>
											<?php if (!empty($entries)): ?>
												<div class="table-responsive">
													<table class="table table-sm align-middle">
														<thead>
															<tr>
																<th style="width:35%">Label</th>
																<?php if ($meta['show_series']): ?><th style="width:20%">Series</th><?php endif; ?>
																<th style="width:20%">Value</th>
																<th style="width:20%">Updated</th>
																<th style="width:5%"></th>
															</tr>
														</thead>
														<tbody>
															<?php foreach ($entries as $row): ?>
																<tr>
																	<td><?php echo htmlspecialchars($row['label']); ?></td>
																	<?php if ($meta['show_series']): ?><td><span class="badge bg-<?php echo $row['series'] === 'on_time' ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($row['series']); ?></span></td><?php endif; ?>
																	<td><?php echo is_numeric($row['value']) ? number_format((float)$row['value'], 2) : htmlspecialchars((string)$row['value']); ?></td>
																	<td><small class="text-muted"><?php echo htmlspecialchars($row['updated_at']); ?></small></td>
																	<td>
																		<form method="post" action="<?php echo BASE_URL; ?>admin/values.php#<?php echo htmlspecialchars($key); ?>" onsubmit="return deleteChartValueAjax(this, '<?php echo htmlspecialchars($key); ?>');">
																			<input type="hidden" name="action" value="delete_entry">
																			<input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
																			<input type="hidden" name="chart_key" value="<?php echo htmlspecialchars($key); ?>">
																			<input type="hidden" name="ajax" value="1">
																			<button type="submit" class="btn btn-sm btn-outline-danger">
																				<i class="fas fa-trash"></i>
																			</button>
																		</form>
																	</td>
																</tr>
															<?php endforeach; ?>
														</tbody>
													</table>
												</div>
											<?php else: ?>
												<p class="text-muted mb-0">No values added yet.</p>
											<?php endif; ?>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<?php $first = false; ?>
				<?php endforeach; ?>
		</div>
	</div>
</div>

<script>
// Unified manager scripts
const chartsMeta = <?php echo json_encode($charts); ?>;
const apiBase = new URL('values_api.php', window.location.href).toString();
const listUrl = apiBase + '?action=list&chart_key=';

function fetchJson(url, options){
	return fetch(url, options).then(async r => {
		const txt = await r.text();
		try { return JSON.parse(txt); } catch (e) { throw new Error(txt || 'Invalid JSON'); }
	});
}

function setFormForChart(key){
	const meta = chartsMeta[key] || {};
	document.getElementById('formChartKey').value = key;
	document.getElementById('chartDesc').textContent = meta.description || '';
	document.getElementById('seriesWrapper').style.display = meta.show_series ? '' : 'none';
	document.getElementById('thSeries').style.display = meta.show_series ? '' : 'none';
}

function renderEntries(key, entries){
	const meta = chartsMeta[key] || {};
	const tbody = document.querySelector('#valuesTable tbody');
	tbody.innerHTML = '';
	entries.forEach(row => {
		const tr = document.createElement('tr');
		tr.innerHTML = `
			<td>${escapeHtml(row.label || '')}</td>
			${meta.show_series ? `<td><span class="badge ${row.series === 'on_time' ? 'bg-success' : 'bg-warning'}">${escapeHtml(row.series || '')}</span></td>` : ''}
			<td>${Number(row.value || 0).toFixed(2)}</td>
			<td><small class="text-muted">${escapeHtml(row.updated_at || '')}</small></td>
			<td>
				<form method="post" action="${apiBase}" onsubmit="return deleteUnifiedAjax(this, '${key}', ${row.id});">
					<input type="hidden" name="action" value="delete_entry">
					<input type="hidden" name="id" value="${row.id}">
					<input type="hidden" name="chart_key" value="${key}">
					<input type="hidden" name="ajax" value="1">
					<button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
				</form>
			</td>`;
		tbody.appendChild(tr);
	});
}

function loadEntries(key){
	fetchJson(listUrl + encodeURIComponent(key), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept':'application/json' }})
		.then(data => {
			if (!data || !data.success) { alert((data && data.error) ? data.error : 'Failed to load entries'); return; }
			renderEntries(key, data.entries || []);
		})
		.catch(err => { console.error(err); alert('Error loading entries: ' + (err && err.message ? err.message.substring(0,200) : '')); });
}

function submitUnifiedAjax(form){
	const key = document.getElementById('formChartKey').value;
	const fd = new FormData(form);
	fd.set('ajax', '1');
	fetchJson(apiBase, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept':'application/json' } })
		.then(data => {
			if (!data || !data.success) { alert((data && data.error) ? data.error : 'Failed to add'); return; }
			form.reset();
			setFormForChart(key);
			loadEntries(key);
		})
		.catch(err => { console.error(err); alert('Error adding: ' + (err && err.message ? err.message.substring(0,200) : '')); });
	return false;
}

function deleteUnifiedAjax(form, key, id){
	if (!confirm('Delete this value?')) return false;
	const fd = new FormData(form);
	fd.set('ajax','1');
	fetchJson(apiBase, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept':'application/json' } })
		.then(data => {
			if (!data || !data.success) { alert((data && data.error) ? data.error : 'Failed to delete'); return; }
			loadEntries(key);
		})
		.catch(err => { console.error(err); alert('Error deleting: ' + (err && err.message ? err.message.substring(0,200) : '')); });
	return false;
}

// Utilities
function escapeHtml(s){
	return String(s).replace(/[&<>"]/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]); });
}

// Init
window.addEventListener('DOMContentLoaded', function(){
	const select = document.getElementById('chartKey');
	const form = document.getElementById('unifiedForm');
	if (form) { form.action = apiBase; }
	function update(){ setFormForChart(select.value); loadEntries(select.value); }
	select.addEventListener('change', update);
	update();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


