<?php
session_start();
require_once 'config.php';

// Check if super admin is logged in
if (!isset($_SESSION['admin_id'])) {
    die(json_encode(['error' => 'Unauthorized access']));
}

// Get current admin's role
$current_admin_id = $_SESSION['admin_id'];
$admin_sql = "SELECT role FROM users WHERE id = ?";
$admin_stmt = $conn->prepare($admin_sql);
$admin_stmt->bind_param('i', $current_admin_id);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();
$current_admin = $admin_result->fetch_assoc();

// Only super admin can create new admins
if ($current_admin['role'] !== 'super_admin') {
    echo '<div class="alert alert-danger">Only super admin can create new admins.</div>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        echo '<div class="alert alert-danger">All fields are required.</div>';
        exit;
    }

    try {
        // Check if username or email is already taken
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            echo '<div class="alert alert-danger">Username or email already taken.</div>';
            exit;
        }

        // Validate password requirements
        $hasLength = strlen($password) >= 8;
        $hasUppercase = preg_match('/[A-Z]/', $password);
        $hasSymbol = preg_match('/[!@#$%^&*]/', $password);

        if (!$hasLength || !$hasUppercase || !$hasSymbol) {
            echo '<div class="alert alert-danger">Password must be at least 8 characters long and contain at least one uppercase letter and one symbol (!@#$%^&*).</div>';
            exit;
        }

        // Create the new admin
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password, is_admin, role, email_verified, created_at) VALUES (?, ?, ?, 1, 'admin', 1, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $username, $email, $hashed_password);

        if ($stmt->execute()) {
            echo '<div class="alert alert-success">Admin created successfully!</div>';
        } else {
            echo '<div class="alert alert-danger">Failed to create admin. Please try again.</div>';
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?> 