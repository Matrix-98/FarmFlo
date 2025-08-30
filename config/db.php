<?php
// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'agri_logistics_db');

/* Define BASE_URL for consistent linking across the application */
// IMPORTANT: YOU MUST CHANGE THIS TO YOUR ACTUAL PROJECT'S BASE URL!
// Examples:
// If your project is at http://localhost/agri_logistics/
// define('BASE_URL', 'http://localhost/agri_logistics/');
//
// If your project is directly in your htdocs/www/html (e.g., http://localhost/)
// define('BASE_URL', 'http://localhost/');
//
// If your project is on a live server, like https://yourdomain.com/agri_logistics/
// define('BASE_URL', 'https://yourdomain.com/agri_logistics/');

define('BASE_URL', 'http://localhost/agri_logistics/'); // <--- **CHANGE THIS LINE** according to your setup!

/* Attempt to connect to MySQL database */
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn === false) {
    die("ERROR: Could not connect to the database. " . mysqli_connect_error());
}

// Set character set for proper data handling
mysqli_set_charset($conn, "utf8mb4");

// Optional: Start a session (useful for storing user login status)
session_start();
?>