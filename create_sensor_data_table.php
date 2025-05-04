<?php
include 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS sensor_data (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    temperature DECIMAL(5,2) NOT NULL,
    humidity DECIMAL(5,2) NOT NULL,
    gas_level INT(11) NOT NULL,
    fan_status BOOLEAN NOT NULL,
    rpm INT(11) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'sensor_data' created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?> 