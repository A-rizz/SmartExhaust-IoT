<?php
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    echo '<div class="alert alert-danger">Session expired. Please log in again.</div>';
    exit();
}

$username = $_SESSION['username'];
$currentPassword = $_POST['currentPassword'] ?? '';
$newUsername = trim($_POST['newUsername'] ?? '');
$newPassword = $_POST['newPassword'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';

// Fetch user info
$sql = "SELECT * FROM users WHERE username = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo '<div class="alert alert-danger">User not found.</div>';
    exit();
}

// Verify current password
if (!password_verify($currentPassword, $user['password'])) {
    echo '<div class="alert alert-danger">Current password is incorrect.</div>';
    exit();
}

$updates = [];
$params = [];
$types = '';

// Handle username change
if ($newUsername && $newUsername !== $username) {
    // Check if new username is taken
    $check = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $check->bind_param('s', $newUsername);
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

// Handle password change
if ($newPassword) {
    if ($newPassword !== $confirmPassword) {
        echo '<div class="alert alert-danger">New passwords do not match.</div>';
        exit();
    }
    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
    $updates[] = 'password = ?';
    $params[] = $hashed;
    $types .= 's';
}

// Handle profile picture upload
$profilePicName = $user['profile_pic'];
if (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] === UPLOAD_ERR_OK) {
    $fileTmp = $_FILES['profilePic']['tmp_name'];
    $fileName = basename($_FILES['profilePic']['name']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($ext, $allowed)) {
        echo '<div class="alert alert-danger">Invalid image format. Allowed: jpg, jpeg, png, gif.</div>';
        exit();
    }
    $newName = uniqid('profile_', true) . '.' . $ext;
    $dest = 'uploads/profile/' . $newName;
    if (!move_uploaded_file($fileTmp, $dest)) {
        echo '<div class="alert alert-danger">Failed to upload image.</div>';
        exit();
    }
    // Optionally delete old profile pic
    if ($profilePicName && file_exists('uploads/profile/' . $profilePicName)) {
        @unlink('uploads/profile/' . $profilePicName);
    }
    $updates[] = 'profile_pic = ?';
    $params[] = $newName;
    $types .= 's';
    $profilePicName = $newName;
}

if (empty($updates)) {
    echo '<div class="alert alert-info">No changes made.</div>';
    exit();
}

// Build update query
$query = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE username = ?';
$params[] = $username;
$types .= 's';
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
if ($stmt->execute()) {
    // Update session username if changed
    if ($newUsername && $newUsername !== $username) {
        $_SESSION['username'] = $newUsername;
    }
    echo '<div class="alert alert-success">Account updated successfully.</div>';
} else {
    echo '<div class="alert alert-danger">Failed to update account. Please try again.</div>';
}
?> 