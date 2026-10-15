<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);          
define('DB_USER', 'root');
define('DB_PASS', '');          
define('DB_NAME', 'unitrade_db');

// --- CONNECTING ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// --- CHECKING IF CONNECTION SUCCEED ---
if ($conn->connect_error) {
    error_log('DB connection failed: ' . $conn->connect_error);
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// --- AVOIDING ALPHABETICAL ERROR ---
$conn->set_charset('utf8mb4');
?>
