<?php
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

define('DB_HOST', 'localhost:3307');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'image_annotation');

// Get the absolute path to the project directory
define('PROJECT_ROOT', __DIR__);

// Directory configuration with absolute paths
define('UPLOAD_DIR', PROJECT_ROOT . '/uploads/');
define('ANNOTATED_DIR', PROJECT_ROOT . '/annotated_images/');

// Create directories if they don't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
if (!file_exists(ANNOTATED_DIR)) {
    mkdir(ANNOTATED_DIR, 0777, true);
}

// Database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Database connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Test database connection
try {
    $testConn = getDBConnection();
    $testConn->close();
} catch (Exception $e) {
    error_log("Database connection test failed: " . $e->getMessage());
    throw $e;
}
?>