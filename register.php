<?php
include 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate password requirements
    $hasLength = strlen($password) >= 8;
    $hasUppercase = preg_match('/[A-Z]/', $password);
    $hasSymbol = preg_match('/[!@#$%^&*]/', $password);

    if (!$hasLength || !$hasUppercase || !$hasSymbol) {
        $error = "Password must be at least 8 characters long and contain at least one uppercase letter and one symbol (!@#$%^&*)";
    } else if ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Username or email already exists";
        } else {
            // Generate verification code
            $verification_code = md5(uniqid(rand(), true));
            
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (username, password, email, verification_code) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $username, $hashed_password, $email, $verification_code);
            
            if ($stmt->execute()) {
                // Send verification email
                $mail = new PHPMailer(true);
                try {
                    //Server settings
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';  // Change this to your SMTP server
                    $mail->SMTPAuth = true;
                    $mail->Username = 'smartexhaustsystem@gmail.com';  // Change this to your email
                    $mail->Password = 'zjjn rxww uplf puci';  // Change this to your app password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    //Recipients
                    $mail->setFrom('your-email@gmail.com', 'Sensor Monitoring');
                    $mail->addAddress($email);

                    //Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Email Verification - Sensor Monitoring';
                    $mail->Body = "Hi $username, Please click the following link to verify your email and start using our system: <br><br>
                                 <a href='http://localhost/exhaust/verify.php?code=$verification_code'>
                                 Verify Email</a>";

                    $mail->send();
                    $success = "Registration successful! Please check your email to verify your account.";
                } catch (Exception $e) {
                    $error = "Registration successful but verification email could not be sent. Please contact support.";
                }
            } else {
                $error = "Error creating account";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Sensor Monitoring</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/auth.css" rel="stylesheet">
    <style>
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            z-index: 9999;
        }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #0077b6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-text {
            margin-top: 20px;
            color: #0077b6;
            font-weight: 500;
            text-align: center;
        }
        .password-requirements {
            font-size: 0.9rem;
        }
        .password-requirements li {
            margin: 5px 0;
            transition: all 0.3s ease;
        }
        .password-requirements li.valid {
            color: #28a745;
        }
        .password-requirements li.valid i {
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="loading-spinner"></div>
            <div class="loading-text">Sending verification email...</div>
        </div>
    </div>
    <div class="container">
        <div class="auth-container smooth-fade">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-center">Create Account</h3>
                </div>
                <div class="card-body">
                    <?php if(isset($error)) { ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php } ?>
                    <?php if(isset($success)) { ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php } ?>
                    <form method="POST" action="" id="registerForm" autocomplete="off">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required autocomplete="username">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required autocomplete="email">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                            </div>
                            <div class="password-requirements mt-2">
                                <div class="form-text">Password must meet the following requirements:</div>
                                <ul class="list-unstyled">
                                    <li id="length" class="text-danger"><i class="fas fa-times-circle me-2"></i>At least 8 characters long</li>
                                    <li id="uppercase" class="text-danger"><i class="fas fa-times-circle me-2"></i>Contains at least one uppercase letter</li>
                                    <li id="symbol" class="text-danger"><i class="fas fa-times-circle me-2"></i>Contains at least one symbol (!@#$%^&*)</li>
                                    <li id="match" class="text-danger"><i class="fas fa-times-circle me-2"></i>Passwords match</li>
                                </ul>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms_accepted" name="terms_accepted" required autocomplete="off">
                            <label class="form-check-label" for="terms_accepted">
                                I agree to the <a href="terms.php">Terms and Conditions</a> and <a href="privacy.php">Privacy Policy</a>
                            </label>
                        </div>
                        <button type="button" class="btn btn-primary w-100" id="showRegisterModal">Register</button>
                    </form>
                    <!-- Registration Confirmation Modal -->
                    <div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true" data-bs-backdrop="false">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="registerModalLabel">Confirm Registration</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body">
                            <div id="registerSummary"></div>
                            <div class="alert alert-warning mt-2">Are you sure all your details are correct?</div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" id="confirmRegisterBtn" class="btn btn-primary">Yes, Register</button>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="text-center mt-3">
                        <a href="login.php">Already have an account? Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Show registration confirmation modal
    $("#showRegisterModal").on("click", function() {
        var username = $("#username").val();
        var email = $("#email").val();
        var summary = '<ul>';
        summary += '<li><b>Username:</b> ' + $('<div>').text(username).html() + '</li>';
        summary += '<li><b>Email:</b> ' + $('<div>').text(email).html() + '</li>';
        summary += '</ul>';
        $("#registerSummary").html(summary);
        var modal = new bootstrap.Modal(document.getElementById('registerModal'), { backdrop: false });
        modal.show();
    });

    // On confirm, submit the form
    $("#confirmRegisterBtn").on("click", function() {
        var modalEl = document.getElementById('registerModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
        
        // Show loading overlay and ensure display:flex for centering
        $("#loadingOverlay").fadeIn(function() {
            $(this).css("display", "flex");
        });
        
        // Submit the form after a short delay to allow modal to hide
        setTimeout(function() { 
            $("#registerForm").submit();
        }, 300);
    });

    // Hide loading overlay if there is an error or success message on page load
    $(document).ready(function() {
        if ($('.alert').length > 0) {
            $('#loadingOverlay').hide();
        }
    });

    // Show/hide password toggle
    $(document).on('click', '.toggle-password', function() {
        var input = $(this).siblings('input');
        var icon = $(this).find('i');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Smooth transition on page load
    $(document).ready(function() {
        $(".smooth-fade").addClass("is-leaving");
        setTimeout(function() {
            $(".smooth-fade").removeClass("is-leaving");
        }, 350);
    });

    $(document).ready(function() {
        function checkPasswordMatch() {
            const password = $('#password').val();
            const confirmPassword = $('#confirm_password').val();
            if (password && confirmPassword && password === confirmPassword) {
                $('#match').removeClass('text-danger').addClass('valid');
                $('#match i').removeClass('fa-times-circle').addClass('fa-check-circle');
            } else {
                $('#match').removeClass('valid').addClass('text-danger');
                $('#match i').removeClass('fa-check-circle').addClass('fa-times-circle');
            }
        }

        $('#password, #confirm_password').on('input', function() {
            const password = $('#password').val();
            
            // Check length
            if (password.length >= 8) {
                $('#length').removeClass('text-danger').addClass('valid');
                $('#length i').removeClass('fa-times-circle').addClass('fa-check-circle');
            } else {
                $('#length').removeClass('valid').addClass('text-danger');
                $('#length i').removeClass('fa-check-circle').addClass('fa-times-circle');
            }
            
            // Check uppercase
            if (/[A-Z]/.test(password)) {
                $('#uppercase').removeClass('text-danger').addClass('valid');
                $('#uppercase i').removeClass('fa-times-circle').addClass('fa-check-circle');
            } else {
                $('#uppercase').removeClass('valid').addClass('text-danger');
                $('#uppercase i').removeClass('fa-check-circle').addClass('fa-times-circle');
            }
            
            // Check symbol
            if (/[!@#$%^&*]/.test(password)) {
                $('#symbol').removeClass('text-danger').addClass('valid');
                $('#symbol i').removeClass('fa-times-circle').addClass('fa-check-circle');
            } else {
                $('#symbol').removeClass('valid').addClass('text-danger');
                $('#symbol i').removeClass('fa-check-circle').addClass('fa-times-circle');
            }
            checkPasswordMatch();
        });
        $('#confirm_password').on('input', checkPasswordMatch);
    });
    </script>
</body>
</html> 