<?php
require_once __DIR__ . '/../config/db.php';

// Ensure no output before JSON
header('Content-Type: application/json');

// Avoid session write lock during long queries
if (session_status() === PHP_SESSION_ACTIVE) {
	session_write_close();
}

http_response_code(200);

// Auth guard for API
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
	http_response_code(401);
	echo json_encode(['success' => false, 'error' => 'Unauthorized']);
	exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
	http_response_code(403);
	echo json_encode(['success' => false, 'error' => 'Forbidden']);
	exit;
}

// Chart meta for validation (keep in sync with UI)
$charts = [
	'inventory_by_location' => ['show_series' => false],
	'top_products' => ['show_series' => false],
	'monthly_shipment_volume' => ['show_series' => false],
	'driver_delivery_performance' => ['show_series' => true],
	'inventory_value_by_stage' => ['show_series' => false],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
	if ($method === 'GET' && $action === 'list') {
		$chartKey = isset($_GET['chart_key']) ? trim((string)$_GET['chart_key']) : '';
		if (!isset($charts[$chartKey])) {
			http_response_code(400);
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

	if ($method === 'POST' && $action === 'add_entry') {
		$chartKey = isset($_POST['chart_key']) ? trim((string)$_POST['chart_key']) : '';
		$label = isset($_POST['label']) ? trim((string)$_POST['label']) : '';
		$series = isset($_POST['series']) ? trim((string)$_POST['series']) : '';
		$value = isset($_POST['value']) ? trim((string)$_POST['value']) : '';

		if (!$chartKey || !isset($charts[$chartKey]) || $label === '' || $value === '' || !is_numeric($value)) {
			http_response_code(400);
			echo json_encode(['success' => false, 'error' => 'Invalid input']);
			exit;
		}

		$sql = 'INSERT INTO chart_values (chart_key, label, series, value) VALUES (?, ?, ?, ?)';
		if ($stmt = mysqli_prepare($conn, $sql)) {
			$val = (float)$value;
			mysqli_stmt_bind_param($stmt, 'sssd', $chartKey, $label, $series, $val);
			if (mysqli_stmt_execute($stmt)) {
				$id = mysqli_insert_id($conn);
				$entry = null;
				if ($id > 0 && ($stmt2 = mysqli_prepare($conn, 'SELECT id, chart_key, label, series, value, updated_at FROM chart_values WHERE id = ?'))) {
					mysqli_stmt_bind_param($stmt2, 'i', $id);
					if (mysqli_stmt_execute($stmt2)) {
						$res = mysqli_stmt_get_result($stmt2);
						$entry = mysqli_fetch_assoc($res);
					}
					mysqli_stmt_close($stmt2);
				}
				echo json_encode(['success' => true, 'entry' => $entry]);
				exit;
			}
			mysqli_stmt_close($stmt);
		}
		http_response_code(500);
		echo json_encode(['success' => false, 'error' => 'Failed to add value']);
		exit;
	}

	if ($method === 'POST' && $action === 'delete_entry') {
		$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		if ($id <= 0) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid ID']); exit; }
		$sql = 'DELETE FROM chart_values WHERE id = ?';
		if ($stmt = mysqli_prepare($conn, $sql)) {
			mysqli_stmt_bind_param($stmt, 'i', $id);
			if (mysqli_stmt_execute($stmt)) { echo json_encode(['success' => true]); exit; }
			mysqli_stmt_close($stmt);
		}
		http_response_code(500);
		echo json_encode(['success' => false, 'error' => 'Failed to delete value']);
		exit;
	}

	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'Unsupported action']);
	exit;
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['success' => false, 'error' => 'Server error']);
	exit;
}


