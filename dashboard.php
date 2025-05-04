<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Fetch current user info for sidebar
$username = $_SESSION['username'];
$sql = "SELECT profile_pic FROM users WHERE username = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$profilePic = $user && $user['profile_pic'] ? $user['profile_pic'] : null;

// Handle data update from ESP32
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and validate data
    $temperature = filter_var($_POST['temperature'] ?? null, FILTER_VALIDATE_FLOAT);
    $humidity = filter_var($_POST['humidity'] ?? null, FILTER_VALIDATE_FLOAT);
    $gas_level = filter_var($_POST['gas_level'] ?? null, FILTER_VALIDATE_INT);
    $fan_status = filter_var($_POST['fan_status'] ?? null, FILTER_VALIDATE_INT);
    $rpm = filter_var($_POST['rpm'] ?? null, FILTER_VALIDATE_INT);
    
    if ($temperature !== false && $humidity !== false && $gas_level !== false) {
        $sql = "INSERT INTO sensor_data (temperature, humidity, gas_level, fan_status, rpm) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ddiii", $temperature, $humidity, $gas_level, $fan_status, $rpm);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Get latest sensor data
$sql = "SELECT * FROM sensor_data ORDER BY timestamp DESC LIMIT 1";
$result = $conn->query($sql);
if ($result) {
    $latest_data = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Sensor Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0f7fa 0%, #ffffff 100%);
            min-height: 100vh;
        }
        .sidebar {
            height: 100vh;
            background: linear-gradient(180deg, #0077b6 0%, #43cea2 100%);
            color: #fff;
            padding-top: 30px;
            position: fixed;
            width: 240px;
            z-index: 1000;
        }
        .sidebar .nav-link {
            color: #fff;
            font-size: 1.1rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.15);
            color: #fff;
        }
        .sidebar .nav-link i {
            margin-right: 12px;
            font-size: 1.3rem;
        }
        .sidebar .sidebar-footer {
            position: absolute;
            bottom: 30px;
            left: 0;
            width: 100%;
            text-align: center;
        }
        .main-content {
            margin-left: 240px;
            padding: 0;
            min-height: 100vh;
        }
        .header {
            background: linear-gradient(90deg, #0077b6 0%, #43cea2 100%);
            color: #fff;
            padding: 18px 32px;
            font-size: 1.3rem;
            font-weight: 600;
            border-bottom-left-radius: 18px;
            border-bottom-right-radius: 18px;
        }
        @media (max-width: 768px) {
            .sidebar { width: 100px; padding-top: 10px; }
            .main-content { margin-left: 100px; }
            .sidebar .nav-link span { display: none; }
        }
        #content-area {
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.35s cubic-bezier(.4,0,.2,1), transform 0.35s cubic-bezier(.4,0,.2,1);
            will-change: opacity, transform;
        }
        #content-area.is-leaving {
            opacity: 0;
            transform: translateY(30px);
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="sidebar d-flex flex-column">
        <div class="mb-4 text-center">
            <span class="fs-4 fw-bold">Sensor Monitoring</span>
        </div>
        <nav class="nav flex-column mb-auto">
            <a class="nav-link active" href="#" data-page="dashboard_content.php" id="nav-dashboard"><i class="fa-solid fa-gauge"></i> <span>Dashboard</span></a>
            <a class="nav-link" href="#" data-page="threshold_settings.php" id="nav-thresholds"><i class="fa-solid fa-sliders-h"></i> <span>Temperature Threshold</span></a>
            <a class="nav-link" href="#" data-page="account_content.php" id="nav-account"><i class="fa-solid fa-user-cog"></i> <span>Account Management</span></a>
            <a class="nav-link" href="#" data-page="about_content.php" id="nav-about"><i class="fa-solid fa-circle-info"></i> <span>About Us</span></a>
            <a class="nav-link" href="logout.php" id="nav-logout"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a>
        </nav>
        <div class="sidebar-footer mt-auto text-center">
            <?php if ($profilePic): ?>
                <img src="uploads/profile/<?php echo htmlspecialchars($profilePic); ?>" alt="Profile Picture" style="width:48px; height:48px; object-fit:cover; border-radius:50%; box-shadow: 0 4px 24px rgba(0, 119, 182, 0.10); border: 2px solid #43cea2; margin-bottom: 8px;">
            <?php else: ?>
                <div style="display: inline-block; background: linear-gradient(135deg, #43cea2 0%, #0077b6 100%); border-radius: 50%; width: 48px; height: 48px; box-shadow: 0 4px 24px rgba(0, 119, 182, 0.10); margin-bottom: 8px;">
                    <i class="fa-solid fa-user fa-lg text-white" style="line-height: 48px;"></i>
                </div>
            <?php endif; ?>
            <div class="small">Welcome, <?php echo $_SESSION['username']; ?></div>
        </div>
    </div>
    <div class="main-content">
        <div id="pageLoader" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); z-index:2000;">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
        <div class="header">Dashboard</div>
        <div id="content-area" class="p-4">
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Relay Control</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-0">Current State</h6>
                                    <p class="mb-0" id="relayState">Loading...</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="relayToggle">
                                    <label class="form-check-label" for="relayToggle">Manual Control</label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Auto Mode</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="autoModeToggle">
                                    <label class="form-check-label" for="autoModeToggle">Enable Auto Mode</label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Threshold Settings</label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text">Gas</span>
                                    <input type="number" class="form-control" id="gasThreshold" placeholder="Gas threshold">
                                </div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text">Temperature</span>
                                    <input type="number" class="form-control" id="tempThreshold" placeholder="Temperature threshold">
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text">Humidity</span>
                                    <input type="number" class="form-control" id="humidityThreshold" placeholder="Humidity threshold">
                                </div>
                            </div>
                            
                            <button class="btn btn-primary" id="saveThresholds">Save Thresholds</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Are you sure you want to log out?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <a href="logout.php" class="btn btn-danger" id="confirmLogoutBtn">Logout</a>
          </div>
        </div>
      </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let refreshInterval;

        function loadContent(page, title) {
            $("#pageLoader").fadeIn(150);
            $("#content-area").addClass("is-leaving");
            
            // Clear any existing refresh interval
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }

            setTimeout(function() {
                $.get(page, function(data) {
                    $("#content-area").html(data);
                    $(".header").text(title);
                    $("#content-area").removeClass("is-leaving");
                    $("#pageLoader").fadeOut(150);

                    // Only set up refresh interval for dashboard page
                    if (page === 'dashboard_content.php') {
                        refreshDashboard();
                        refreshInterval = setInterval(refreshDashboard, 5000);
                    }
                });
            }, 350);
        }

        function refreshDashboard() {
            $.get('api/control_relay.php', function(data) {
                if ($('#relayState').length) {  // Only update if elements exist
                    $('#relayState').text(data.current_state ? 'ON' : 'OFF');
                    $('#relayToggle').prop('checked', data.current_state);
                    $('#autoModeToggle').prop('checked', data.auto_mode);
                }
            });
        }

        $(document).ready(function() {
            // Initial load
            loadContent('dashboard_content.php', 'Dashboard');

            // Sidebar navigation
            $('.sidebar .nav-link').click(function(e) {
                var page = $(this).data('page');
                var title = $(this).find('span').text();
                if (page) {
                    e.preventDefault();
                    $('.sidebar .nav-link').removeClass('active');
                    $(this).addClass('active');
                    loadContent(page, title);
                }
            });

            // Logout confirmation
            $('#nav-logout').on('click', function(e) {
                e.preventDefault();
                var modal = new bootstrap.Modal(document.getElementById('logoutModal'));
                modal.show();
            });

            // Toggle relay state
            $(document).on('change', '#relayToggle', function() {
                const action = $(this).prop('checked') ? 'on' : 'off';
                $.ajax({
                    url: 'api/control_relay.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ action: action }),
                    success: function() {
                        refreshDashboard();
                    }
                });
            });

            // Toggle auto mode
            $(document).on('change', '#autoModeToggle', function() {
                $.ajax({
                    url: 'api/control_relay.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ auto_mode: $(this).prop('checked') }),
                    success: function() {
                        refreshDashboard();
                    }
                });
            });
        });
    </script>
</body>
</html> 