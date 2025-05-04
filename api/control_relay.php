<?php
include '../config.php';
header('Content-Type: application/json');

// Check authentication
session_start();
$is_esp32 = false;

// Check API key for ESP32
$headers = apache_request_headers();
$api_key = '';
if (isset($headers['X-API-Key'])) {
    $api_key = $headers['X-API-Key'];
    if ($api_key === '123456789') { // Match ESP32's API key
        $is_esp32 = true;
    }
}

// If not ESP32, check user session
if (!$is_esp32) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - Please log in']);
        exit();
    }
}

// Get current settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT * FROM relay_control LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        // Convert to proper boolean/number types
        $row['current_state'] = (bool)$row['current_state'];
        $row['auto_mode'] = (bool)$row['auto_mode'];
        $row['gas_threshold'] = (int)$row['gas_threshold'];
        $row['temperature_threshold'] = (float)$row['temperature_threshold'];
        $row['humidity_threshold'] = (float)$row['humidity_threshold'];
        echo json_encode($row);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch settings']);
    }
    exit();
}

// Update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_esp32) { // Only allow updates from dashboard
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            exit();
        }
        
        if (isset($data['action'])) {
            // Toggle relay state
            $new_state = $data['action'] === 'on' ? 1 : 0;
            $sql = "UPDATE relay_control SET current_state = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $new_state);
        } 
        else if (isset($data['auto_mode'])) {
            // Update auto mode and reset current state to OFF when switching modes
            $sql = "UPDATE relay_control SET auto_mode = ?, current_state = 0";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $data['auto_mode']);
        }
        else if (isset($data['thresholds'])) {
            // Validate threshold values
            if (!isset($data['thresholds']['gas']) || 
                !isset($data['thresholds']['temperature']) || 
                !isset($data['thresholds']['humidity'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing threshold values']);
                exit();
            }
            
            // Update thresholds
            $sql = "UPDATE relay_control SET 
                    gas_threshold = ?,
                    temperature_threshold = ?,
                    humidity_threshold = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("idd", 
                $data['thresholds']['gas'],
                $data['thresholds']['temperature'],
                $data['thresholds']['humidity']
            );
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request data']);
            exit();
        }
        
        if (isset($stmt)) {
            if ($stmt->execute()) {
                // Log the action
                $action = isset($data['action']) ? "Fan turned " . strtoupper($data['action']) :
                         (isset($data['auto_mode']) ? "Auto mode " . ($data['auto_mode'] ? "enabled" : "disabled") :
                         "Thresholds updated");
                
                try {
                    $log_sql = "INSERT INTO user_activity_log (user_id, action, ip_address) VALUES (?, ?, ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $log_stmt->bind_param("iss", $_SESSION['user_id'], $action, $_SERVER['REMOTE_ADDR']);
                    $log_stmt->execute();
                } catch (Exception $e) {
                    // Log error but don't fail the request
                    error_log("Failed to log user activity: " . $e->getMessage());
                }
                
                echo json_encode(['success' => true, 'message' => $action]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update settings', 'details' => $conn->error]);
            }
            $stmt->close();
        }
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'ESP32 not allowed to modify settings']);
    }
}

$conn->close();
?> 