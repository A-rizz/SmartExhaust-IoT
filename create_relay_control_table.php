<?php
include 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS relay_control (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    gas_threshold INT(11) NOT NULL DEFAULT 400,
    temperature_threshold DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    humidity_threshold DECIMAL(5,2) NOT NULL DEFAULT 80.00,
    auto_mode BOOLEAN NOT NULL DEFAULT TRUE,
    current_state BOOLEAN NOT NULL DEFAULT FALSE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    // Insert default settings if table is empty
    $check = "SELECT COUNT(*) as count FROM relay_control";
    $result = $conn->query($check);
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        $insert = "INSERT INTO relay_control (gas_threshold, temperature_threshold, humidity_threshold, auto_mode, current_state) 
                  VALUES (400, 30.00, 80.00, TRUE, FALSE)";
        $conn->query($insert);
    }
    echo "Table 'relay_control' created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?> 