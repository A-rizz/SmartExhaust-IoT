<?php
include '../config.php';
header('Content-Type: application/json');

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

if ($api_key !== ESP32_API_KEY) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Invalid API key',
        'received' => $api_key,
        'expected' => ESP32_API_KEY
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required parameters
    $required = ['temperature', 'humidity', 'gas_level', 'fan_status', 'rpm'];
    $missing = array_diff($required, array_keys($_POST));
    
    if (!empty($missing)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters', 'missing' => $missing]);
        exit();
    }
    
    // Validate and sanitize input
    $temperature = filter_var($_POST['temperature'], FILTER_VALIDATE_FLOAT);
    $humidity = filter_var($_POST['humidity'], FILTER_VALIDATE_FLOAT);
    $gas_level = filter_var($_POST['gas_level'], FILTER_VALIDATE_INT);
    $fan_status = filter_var($_POST['fan_status'], FILTER_VALIDATE_INT);
    $rpm = filter_var($_POST['rpm'], FILTER_VALIDATE_INT);
    
    if ($temperature === false || $humidity === false || 
        $gas_level === false || $fan_status === false || $rpm === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data format']);
        exit();
    }
    
    // Insert data into database
    $sql = "INSERT INTO sensor_data (temperature, humidity, gas_level, fan_status, rpm) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ddiis", $temperature, $humidity, $gas_level, $fan_status, $rpm);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Data recorded successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save data']);
    }
    
    $stmt->close();
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

$conn->close();
?> 