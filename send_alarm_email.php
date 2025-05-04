<?php
include 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $temperature = isset($_POST['temperature']) ? floatval($_POST['temperature']) : null;
    $level = isset($_POST['level']) ? $_POST['level'] : '';
    if ($temperature === null || !$level) {
        http_response_code(400);
        echo 'Invalid data.';
        exit;
    }

    // Fetch all user emails
    $emails = [];
    $sql = "SELECT email FROM users WHERE is_verified = 1";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row['email'];
    }

    if (empty($emails)) {
        http_response_code(404);
        echo 'No users to notify.';
        exit;
    }

    // Email subject and message
    $subject = ($level === 'critical' ? 'CRITICAL' : 'WARNING') . ": High Temperature Alert";
    $message = "<h2 style='color:#b91c1c;'>" . strtoupper($level) . " ALERT</h2>";
    $message .= "<p>The system has detected a temperature of <strong>" . $temperature . "°C</strong>.</p>";
    if ($level === 'critical') {
        $message .= "<p style='color:#b91c1c;'><strong>Immediate action is required!</strong></p>";
    } else {
        $message .= "<p style='color:#eab308;'><strong>Please monitor the situation closely.</strong></p>";
    }
    $message .= "<hr><p>This is an automated alert from the Exhaust Fan Monitoring System.</p>";

    // Send email to all users
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'smartexhaustsystem@gmail.com';
        $mail->Password = 'zjjn rxww uplf puci';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom('smartexhaustsystem@gmail.com', 'Smart Exhaust System');
        foreach ($emails as $email) {
            $mail->addAddress($email);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->send();
        echo 'Alarm email sent.';
    } catch (Exception $e) {
        http_response_code(500);
        echo 'Failed to send email: ' . $mail->ErrorInfo;
    }
} else {
    http_response_code(405);
    echo 'Method not allowed.';
} 