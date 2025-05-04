<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
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
$is_super_admin = ($current_admin['role'] === 'super_admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($user_id) || empty($username) || empty($email)) {
        echo '<div class="alert alert-danger">All fields are required.</div>';
        exit;
    }

    try {
        // Check if the user being edited is a super admin
        $check_sql = "SELECT role FROM users WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('i', $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $user = $result->fetch_assoc();

        // Only super admin can modify super admin accounts
        if ($user['role'] === 'super_admin' && !$is_super_admin) {
            echo '<div class="alert alert-danger">Only super admin can modify super admin accounts.</div>';
            exit;
        }

        // Prevent admins from editing their own account through this endpoint
        if ($user_id == $current_admin_id) {
            echo '<div class="alert alert-danger">Please use the "Manage My Account" button to edit your own account.</div>';
            exit;
        }

        // Check if username or email is already taken by another user
        $check_sql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ssi", $username, $email, $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            echo '<div class="alert alert-danger">Username or email already taken by another user.</div>';
            exit;
        }

        // Build update query based on whether password is being changed
        if (!empty($password)) {
            // Validate password requirements
            $hasLength = strlen($password) >= 8;
            $hasUppercase = preg_match('/[A-Z]/', $password);
            $hasSymbol = preg_match('/[!@#$%^&*]/', $password);

            if (!$hasLength || !$hasUppercase || !$hasSymbol) {
                echo '<div class="alert alert-danger">Password must be at least 8 characters long and contain at least one uppercase letter and one symbol (!@#$%^&*).</div>';
                exit;
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $username, $email, $hashed_password, $user_id);
        } else {
            $sql = "UPDATE users SET username = ?, email = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $username, $email, $user_id);
        }

        if ($stmt->execute()) {
            echo '<div class="alert alert-success">User updated successfully!</div>';
        } else {
            echo '<div class="alert alert-danger">Failed to update user. Please try again.</div>';
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?> 