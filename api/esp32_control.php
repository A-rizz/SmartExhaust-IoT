<?php
include '../config.php';
header('Content-Type: application/json');

// TEMPORARY DEBUG MODE - REMOVE IN PRODUCTION
$debug_mode = true;

// API key for ESP32 authentication
define('ESP32_API_KEY', '123456789');

// Check API key - more lenient check with debugging
$headers = apache_request_headers(); // Try apache_request_headers instead
$api_key = '';

// Debug output
error_log("Received headers: " . print_r($headers, true));

// Check in multiple places
if (isset($headers['X-API-Key'])) {
    $api_key = $headers['X-API-Key'];
} elseif (isset($headers['x-api-key'])) {
    $api_key = $headers['x-api-key'];
} elseif (isset($_SERVER['HTTP_X_API_KEY'])) {
    $api_key = $_SERVER['HTTP_X_API_KEY'];
} elseif (isset($_GET['api_key'])) {
    $api_key = $_GET['api_key'];
}

error_log("Received API key: " . $api_key);
error_log("Expected API key: " . ESP32_API_KEY);

// Skip API key check if in debug mode
if (!$debug_mode && $api_key !== ESP32_API_KEY) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Invalid API key',
        'received' => $api_key,
        'expected' => ESP32_API_KEY
    ]);
    exit();
}

// Get current settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT * FROM relay_control LIMIT 1";
    $result = $conn->query($sql);
    if ($result) {
        echo json_encode($result->fetch_assoc());
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit();
}

$conn->close();
?> 