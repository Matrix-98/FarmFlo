<?php
require_once '../config/db.php';
require_once '../utils/id_generator.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: " . BASE_URL . "index.php");
    exit;
}

if ($_SESSION["role"] != 'admin') {
    $_SESSION['error_message'] = "You do not have permission to edit locations.";
    header("location: " . BASE_URL . "dashboard.php");
    exit;
}

$page_title = "Edit Location";
$current_page = "locations";

$location_id = $name = $address = $type = $latitude = $longitude = $capacity_kg = $capacity_m3 = "";
$name_err = $address_err = $type_err = $latitude_err = $longitude_err = $capacity_kg_err = $capacity_m3_err = "";

// Initialize audit trail variables for display
$created_at = $updated_at = $created_by_username = $updated_by_username = '';

// Track currently assigned managers for this location (for display)
$assigned_managers = [];


// Fetch existing location data if ID is provided in GET request
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $location_id = trim($_GET["id"]);

    // FIX: Include capacity_kg and capacity_m3 in the SELECT query
    $sql_fetch_location = "SELECT name, address, type, latitude, longitude, capacity_kg, capacity_m3, created_at, updated_at, created_by, updated_by FROM locations WHERE location_id = ?";
    if ($stmt_fetch = mysqli_prepare($conn, $sql_fetch_location)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $param_id);
        $param_id = $location_id;

        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);

            if (mysqli_num_rows($result_fetch) == 1) {
                $row = mysqli_fetch_assoc($result_fetch);
                $name = $row["name"];
                $address = $row["address"];
                $type = $row["type"];
                $latitude = $row["latitude"];
                $longitude = $row["longitude"];
                $capacity_kg = $row["capacity_kg"]; // NEW: Fetch capacity
                $capacity_m3 = $row["capacity_m3"]; // NEW: Fetch capacity
                // Capture audit data for display
                $created_at = $row["created_at"];
                $updated_at = $row["updated_at"];
                $created_by_id = $row["created_by"];
                $updated_by_id = $row["updated_by"];

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

                // Fetch assigned warehouse managers for this location (if any)
                $sql_assigned_mgrs = "SELECT u.user_id, u.username, u.email
                                      FROM user_assigned_locations ual
                                      JOIN users u ON ual.user_id = u.user_id
                                      WHERE ual.location_id = ?";
                if ($stmt_mgrs = mysqli_prepare($conn, $sql_assigned_mgrs)) {
                    mysqli_stmt_bind_param($stmt_mgrs, "i", $param_id);
                    mysqli_stmt_execute($stmt_mgrs);
                    $res_mgrs = mysqli_stmt_get_result($stmt_mgrs);
                    while ($mgr = mysqli_fetch_assoc($res_mgrs)) {
                        $assigned_managers[] = $mgr;
                    }
                    mysqli_stmt_close($stmt_mgrs);
                }

            } else {
                $_SESSION['error_message'] = "Location not found.";
                header("location: " . BASE_URL . "locations/index.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "Oops! Something went wrong fetching location data. Please try again later.";
            error_log("Error executing location fetch: " . mysqli_error($conn));
            header("location: " . BASE_URL . "locations/index.php");
            exit();
        }
        mysqli_stmt_close($stmt_fetch);
    } else {
        $_SESSION['error_message'] = "Error preparing location fetch statement. Please try again later.";
        error_log("Error preparing location fetch statement: " . mysqli_error($conn));
        header("location: " . BASE_URL . "locations/index.php");
        exit();
    }
} else if ($_SERVER["REQUEST_METHOD"] != "POST") {
    $_SESSION['error_message'] = "Invalid request. No location ID provided.";
    header("location: " . BASE_URL . "locations/index.php");
    exit();
}