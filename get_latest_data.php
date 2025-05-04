<?php
include 'config.php';
$sql = "SELECT * FROM sensor_data ORDER BY timestamp DESC LIMIT 1";
$result = $conn->query($sql);
if ($result) {
    $latest_data = $result->fetch_assoc();
    echo json_encode($latest_data);
} else {
    echo json_encode(['error' => 'No data']);
}
?> 