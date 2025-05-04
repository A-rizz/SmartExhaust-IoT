<?php
include 'config.php';

if (isset($_GET['code'])) {
    $verification_code = $_GET['code'];
    
    // Check if verification code exists
    $sql = "SELECT * FROM users WHERE verification_code = ? AND email_verified = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $verification_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update user as verified
        $update_sql = "UPDATE users SET email_verified = 1, verification_code = NULL WHERE verification_code = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("s", $verification_code);
        
        if ($update_stmt->execute()) {
            $message = "Email verified successfully! You can now login.";
            $message_type = "success";
        } else {
            $message = "Error verifying email. Please try again.";
            $message_type = "danger";
        }
        $update_stmt->close();
    } else {
        $message = "Invalid or expired verification code.";
        $message_type = "danger";
    }
    $stmt->close();
} else {
    $message = "No verification code provided.";
    $message_type = "danger";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Email Verification - Sensor Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/auth.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-center">Email Verification</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 