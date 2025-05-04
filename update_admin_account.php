<?php
session_start();
include 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo '<div class="alert alert-danger">Session expired. Please log in again.</div>';
    exit();
}

$admin_id = $_SESSION['admin_id'];
$newUsername = trim($_POST['adminUsername'] ?? '');
$newEmail = trim($_POST['adminEmail'] ?? '');
$newPassword = $_POST['adminPassword'] ?? '';

// Fetch current admin info
$sql = "SELECT * FROM users WHERE id = ? AND is_admin = 1 LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    echo '<div class="alert alert-danger">Admin not found.</div>';
    exit();
}

$updates = [];
$params = [];
$types = '';

// Handle username change
if ($newUsername && $newUsername !== $admin['username']) {
    // Check if new username is taken
    $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
    $check->bind_param('si', $newUsername, $admin_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo '<div class="alert alert-danger">Username already taken.</div>';
        exit();
    }
    $updates[] = 'username = ?';
    $params[] = $newUsername;
    $types .= 's';
}

// Handle email change
if ($newEmail && $newEmail !== $admin['email']) {
    // Check if new email is taken
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $check->bind_param('si', $newEmail, $admin_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo '<div class="alert alert-danger">Email already taken.</div>';
        exit();
    }
    $updates[] = 'email = ?';
    $params[] = $newEmail;
    $types .= 's';
}

// Handle password change
if ($newPassword) {
    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
    $updates[] = 'password = ?';
    $params[] = $hashed;
    $types .= 's';
}

if (empty($updates)) {
    echo '<div class="alert alert-info">No changes made.</div>';
    exit();
}

// Build update query
$query = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
$params[] = $admin_id;
$types .= 'i';
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    // Update session username if changed
    if ($newUsername && $newUsername !== $admin['username']) {
        $_SESSION['admin_username'] = $newUsername;
    }
    echo '<div class="alert alert-success">Account updated successfully.</div>';
} else {
    echo '<div class="alert alert-danger">Failed to update account. Please try again.</div>';
}
?> 