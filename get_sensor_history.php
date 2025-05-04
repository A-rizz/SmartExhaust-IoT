<?php
include 'config.php';

// Get the last 20 records (or adjust as needed)
$sql = "SELECT timestamp, temperature, humidity, gas_level FROM sensor_data ORDER BY timestamp DESC LIMIT 20";
$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Return in reverse order (oldest first)
$data = array_reverse($data);

header('Content-Type: application/json');
echo json_encode($data);
?>