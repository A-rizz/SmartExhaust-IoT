<?php
include 'config.php';
header('Content-Type: application/json');

// Validate required parameters
$required_params = ['temperature', 'humidity', 'gas_level', 'fan_status', 'rpm'];
$missing_params = array_diff($required_params, array_keys($_POST));

if (!empty($missing_params)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing required parameters',
        'missing' => $missing_params
    ]);
    exit();
}

// Validate data types
$temperature = filter_var($_POST['temperature'], FILTER_VALIDATE_FLOAT);
$humidity = filter_var($_POST['humidity'], FILTER_VALIDATE_FLOAT);
$gas_level = filter_var($_POST['gas_level'], FILTER_VALIDATE_INT);
$fan_status = filter_var($_POST['fan_status'], FILTER_VALIDATE_BOOLEAN);
$rpm = filter_var($_POST['rpm'], FILTER_VALIDATE_INT);

if ($temperature === false || $humidity === false || $gas_level === false || $fan_status === null || $rpm === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data types']);
    exit();
}

// Validate ranges
if ($temperature < -50 || $temperature > 100 || 
    $humidity < 0 || $humidity > 100 || 
    $gas_level < 0 || $gas_level > 4095 || 
    $rpm < 0 || $rpm > 10000) {
    http_response_code(400);
    echo json_encode(['error' => 'Values out of valid range']);
    exit();
}

try {
    // Prepare and execute SQL statement
    $stmt = $conn->prepare("INSERT INTO sensor_data (temperature, humidity, gas_level, fan_status, rpm) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ddiis", $temperature, $humidity, $gas_level, $fan_status, $rpm);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Data recorded successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception("Failed to insert data");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}

$stmt->close();
$conn->close();
?>