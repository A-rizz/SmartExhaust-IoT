<?php
require_once 'config.php';

try {
    // Add role column if it doesn't exist
    $sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('user', 'admin', 'super_admin') DEFAULT 'user'";
    $conn->query($sql);

    // Update existing admins to have the admin role
    $sql = "UPDATE users SET role = 'admin' WHERE is_admin = 1";
    $conn->query($sql);

    // Create default super admin if it doesn't exist
    $super_admin_username = "superadmin";
    $super_admin_email = "superadmin@example.com";
    $super_admin_password = password_hash("SuperAdmin123!", PASSWORD_DEFAULT);

    $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $super_admin_username, $super_admin_email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows == 0) {
        $sql = "INSERT INTO users (username, email, password, is_admin, role, created_at) VALUES (?, ?, ?, 1, 'super_admin', NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $super_admin_username, $super_admin_email, $super_admin_password);
        $stmt->execute();
        echo "Super admin account created successfully!<br>";
        echo "Username: superadmin<br>";
        echo "Password: SuperAdmin123!<br>";
        echo "Please change these credentials immediately after first login.";
    } else {
        echo "Super admin account already exists.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 