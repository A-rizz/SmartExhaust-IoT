<?php
session_start();
include 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
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

// Get all users
$sql = "SELECT * FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);
$users = $result->fetch_all(MYSQLI_ASSOC);

// Handle user actions
if (isset($_POST['action'])) {
    $user_id = $_POST['user_id'];
    
    // Check if the user is a super admin
    $check_sql = "SELECT role FROM users WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Only super admin can modify super admin accounts
    if ($user['role'] === 'super_admin' && !$is_super_admin) {
        die(json_encode(['error' => 'Only super admin can modify super admin accounts']));
    }
    
    switch ($_POST['action']) {
        case 'delete':
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            break;
            
        case 'toggle_admin':
            $sql = "UPDATE users SET is_admin = NOT is_admin, role = IF(role = 'admin', 'user', 'admin') WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            break;
    }
    
    // Refresh the page
    header("Location: admin_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - Sensor Monitoring</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/auth.css" rel="stylesheet">
    <style>
        /* Remove or neutralize the body selector and its styles */
        /* body {
            background: linear-gradient(135deg, #e0f7fa 0%, #ffffff 100%);
            min-height: 100vh;
        } */
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
            width: calc(100% - 240px);
            padding: 0;
            min-height: 100vh;
        }
        .header {
            background: linear-gradient(90deg, #0077b6 0%, #43cea2 100%);
            color: #fff;
            padding: 18px 32px;
            font-size: 1.3rem;
            font-weight: 600;
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 18px;
            border-top-right-radius: 18px;
            border-top-left-radius: 0;
            width: 100%;
            margin-left: 0;
            position: relative;
            box-sizing: border-box;
        }
        .header .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.5);
            color: #fff;
            padding: 8px 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .header .btn-outline-light:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: #fff;
        }
        .header .btn-outline-light i {
            font-size: 1.1rem;
            margin-right: 8px;
        }
        .user-card,
        .card {
            /* Remove overflow and z-index */
        }
        .user-card {
            transition: all 0.3s ease;
        }
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .admin-badge {
            background-color: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 8px;
        }
        .user-badge {
            background-color: #0d6efd;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 8px;
        }
        .super-admin-badge {
            background-color: #6f42c1;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 8px;
        }
        .dropdown-menu {
            min-width: 160px;
        }
        .user-actions {
            display: flex;
            gap: 0.3rem;
            margin-top: 0;
            align-items: center;
        }
        .btn-admin-toggle {
            background: linear-gradient(90deg, #0077b6 0%, #43cea2 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 0.8rem;
            padding: 0.18rem 0.7rem;
            font-weight: 500;
            transition: background 0.2s, color 0.2s;
            line-height: 1.2;
        }
        .btn-admin-toggle.btn-sm { font-size: 0.8rem; padding: 0.18rem 0.7rem; }
        .btn-admin-toggle:hover {
            background: linear-gradient(90deg, #43cea2 0%, #0077b6 100%);
            color: #fff;
        }
        .btn-delete-user {
            background: transparent;
            color: #dc3545;
            border: 1px solid #dc3545;
            border-radius: 12px;
            font-size: 0.8rem;
            padding: 0.18rem 0.7rem;
            font-weight: 500;
            transition: background 0.2s, color 0.2s;
            line-height: 1.2;
            margin-left: 0.3rem;
        }
        .btn-delete-user.btn-sm { font-size: 0.8rem; padding: 0.18rem 0.7rem; }
        .btn-delete-user:hover {
            background: #dc3545;
            color: #fff;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 0.25rem;
        }
        .btn-outline-primary {
            color: #0077b6;
            border-color: #0077b6;
        }
        .btn-outline-primary:hover {
            background-color: #0077b6;
            color: white;
        }
        .btn-outline-danger {
            color: #dc3545;
            border-color: #dc3545;
        }
        .btn-outline-danger:hover {
            background-color: #dc3545;
            color: white;
        }
        .gap-2 {
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="sidebar d-flex flex-column">
        <div class="mb-4 text-center">
            <span class="fs-4 fw-bold">Sensor Monitoring</span>
        </div>
        <nav class="nav flex-column mb-auto">
            <a class="nav-link active" href="#"><i class="fa-solid fa-user-shield"></i> <span>Admin Dashboard</span></a>
        </nav>
        <div class="sidebar-footer mt-auto text-center">
            <div class="small">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></div>
            <button class="btn btn-outline-primary mt-2" id="showAdminAccountModal"><i class="fas fa-user-cog me-2"></i>Manage My Account</button>
            <button class="btn btn-outline-danger mt-2" id="showLogoutModal"><i class="fas fa-sign-out-alt me-2"></i>Logout</button>
        </div>
    </div>
    <div class="main-content">
        <div class="header">Admin Dashboard</div>
        <div class="container py-4">
            <?php if ($is_super_admin): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <button class="btn btn-primary" id="showCreateAdminModal">
                        <i class="fas fa-user-plus me-2"></i>Create New Admin
                    </button>
                </div>
            </div>
            <?php endif; ?>
            <div class="row">
                <?php foreach ($users as $user): ?>
                <div class="col-md-4 mb-4">
                    <div class="card user-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ($user['role'] === 'super_admin'): ?>
                                        <span class="super-admin-badge">Super Admin</span>
                                    <?php elseif ($user['is_admin']): ?>
                                        <span class="admin-badge">Admin</span>
                                    <?php else: ?>
                                        <span class="user-badge">User</span>
                                    <?php endif; ?>
                                </h5>
                                <div class="user-actions">
                                    <?php if ($user['role'] !== 'super_admin' || $is_super_admin): ?>
                                        <?php if ($user['id'] != $current_admin_id): ?>
                                            <?php if ($user['role'] !== 'super_admin'): ?>
                                                <button class="btn btn-sm btn-outline-primary toggle-admin-btn" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-userid="<?php echo $user['id']; ?>" data-admin="<?php echo $user['is_admin']; ?>">
                                                    <?php echo $user['is_admin'] ? 'Remove Admin' : 'Make Admin'; ?>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info edit-user-btn" data-userid="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-email="<?php echo htmlspecialchars($user['email']); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger delete-user-btn" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-userid="<?php echo $user['id']; ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-info edit-user-btn" data-userid="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-email="<?php echo htmlspecialchars($user['email']); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted small">Use "Manage My Account" to edit your details</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="card-text">
                                <small class="text-muted">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </small>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
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
            <a href="logout.php" class="btn btn-danger">Logout</a>
          </div>
        </div>
      </div>
    </div>
    <!-- Delete User Confirmation Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Are you sure you want to delete user <span id="deleteUserName"></span>?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <form method="POST" id="deleteUserForm">
                <input type="hidden" name="user_id" id="deleteUserId">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <!-- Grant/Revoke Admin Confirmation Modal -->
    <div class="modal fade" id="adminModal" tabindex="-1" aria-labelledby="adminModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="adminModalLabel">Confirm Admin Change</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <span id="adminActionText"></span>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <form method="POST" id="adminForm">
                <input type="hidden" name="user_id" id="adminUserId">
                <input type="hidden" name="action" value="toggle_admin">
                <button type="submit" class="btn btn-primary" id="adminActionBtn">Confirm</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <!-- Admin Account Management Modal -->
    <div class="modal fade" id="adminAccountModal" tabindex="-1" aria-labelledby="adminAccountModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="adminAccountModalLabel">Manage My Account</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" id="adminAccountForm">
            <div class="modal-body">
              <div id="adminAccountMsg"></div>
              <div class="mb-3">
                <label for="adminUsername" class="form-label">Username</label>
                <input type="text" class="form-control" id="adminUsername" name="adminUsername" value="<?php echo htmlspecialchars($_SESSION['admin_username']); ?>" required>
              </div>
              <div class="mb-3">
                <label for="adminEmail" class="form-label">Email</label>
                <input type="email" class="form-control" id="adminEmail" name="adminEmail" value="<?php echo htmlspecialchars($user['email']); ?>" required>
              </div>
              <div class="mb-3">
                <label for="adminPassword" class="form-label">New Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="adminPassword" name="adminPassword" placeholder="Leave blank to keep current password">
                    <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Update</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <div class="modal-body">
                        <div id="editUserMsg"></div>
                        <input type="hidden" name="user_id" id="editUserId">
                        <div class="mb-3">
                            <label for="editUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="editUsername" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="editEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="editEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="editPassword" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="editPassword" name="password" placeholder="Leave blank to keep current password">
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                            </div>
                            <small class="text-muted">Only fill this if you want to change the password</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Create Admin Modal -->
    <div class="modal fade" id="createAdminModal" tabindex="-1" aria-labelledby="createAdminModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createAdminModalLabel">Create New Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="createAdminForm">
                    <div class="modal-body">
                        <div id="createAdminMsg"></div>
                        <div class="mb-3">
                            <label for="newAdminUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="newAdminUsername" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="newAdminEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="newAdminEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="newAdminPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="newAdminPassword" name="password" required>
                            <div class="form-text">
                                Password must be at least 8 characters long and contain at least one uppercase letter and one symbol (!@#$%^&*)
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Admin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Logout confirmation
    $("#showLogoutModal").on("click", function() {
        var modal = new bootstrap.Modal(document.getElementById('logoutModal'));
        modal.show();
    });

    // Delete user confirmation
    $(document).on('click', '.delete-user-btn', function() {
        var userId = $(this).data('userid');
        var username = $(this).data('username');
        $('#deleteUserId').val(userId);
        $('#deleteUserName').text(username);
        var modal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
        modal.show();
    });

    // Handle delete user form submission
    $("#deleteUserForm").on("submit", function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: formData,
            success: function() {
                location.reload();
            },
            error: function() {
                alert('Failed to delete user. Please try again.');
            }
        });
    });

    // Grant/Revoke admin confirmation
    $(document).on('click', '.toggle-admin-btn', function() {
        var userId = $(this).data('userid');
        var username = $(this).data('username');
        var isAdmin = $(this).data('admin');
        $('#adminUserId').val(userId);
        var actionText = isAdmin == 1 ? 'Are you sure you want to remove admin access from <b>' + username + '</b>?' : 'Are you sure you want to grant admin access to <b>' + username + '</b>?';
        $('#adminActionText').html(actionText);
        $('#adminActionBtn').text(isAdmin == 1 ? 'Remove Admin' : 'Make Admin');
        var modal = new bootstrap.Modal(document.getElementById('adminModal'));
        modal.show();
    });

    // Handle admin toggle form submission
    $("#adminForm").on("submit", function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: formData,
            success: function() {
                location.reload();
            },
            error: function() {
                alert('Failed to update admin status. Please try again.');
            }
        });
    });

    // Show Admin Account Management Modal
    $("#showAdminAccountModal").on("click", function() {
        var modal = new bootstrap.Modal(document.getElementById('adminAccountModal'));
        modal.show();
    });

    // Handle Admin Account Update
    $("#adminAccountForm").on("submit", function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $.ajax({
            url: 'update_admin_account.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                $("#adminAccountMsg").html(response);
                if (response.includes("Account updated successfully")) {
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                }
            },
            error: function() {
                $("#adminAccountMsg").html('<div class="alert alert-danger">An error occurred. Please try again.</div>');
            }
        });
    });

    // Show Edit User Modal
    $(document).on('click', '.edit-user-btn', function() {
        var userId = $(this).data('userid');
        var username = $(this).data('username');
        var email = $(this).data('email');
        
        $('#editUserId').val(userId);
        $('#editUsername').val(username);
        $('#editEmail').val(email);
        $('#editPassword').val('');
        $('#editUserMsg').html('');
        
        var modal = new bootstrap.Modal(document.getElementById('editUserModal'));
        modal.show();
    });

    // Handle Edit User Form Submission
    $("#editUserForm").on("submit", function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        
        $.ajax({
            url: 'update_user.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                $("#editUserMsg").html(response);
                if (response.includes("User updated successfully")) {
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                }
            },
            error: function() {
                $("#editUserMsg").html('<div class="alert alert-danger">An error occurred. Please try again.</div>');
            }
        });
    });

    $(document).ready(function() {
        // Show Create Admin Modal
        $("#showCreateAdminModal").on("click", function() {
            $('#createAdminMsg').html('');
            $('#newAdminUsername').val('');
            $('#newAdminEmail').val('');
            $('#newAdminPassword').val('');
            var modal = new bootstrap.Modal(document.getElementById('createAdminModal'));
            modal.show();
        });

        // Handle Create Admin Form Submission
        $("#createAdminForm").on("submit", function(e) {
            e.preventDefault();
            var formData = $(this).serialize();
            
            $.ajax({
                url: 'create_admin.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    $("#createAdminMsg").html(response);
                    if (response.includes("Admin created successfully")) {
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                },
                error: function() {
                    $("#createAdminMsg").html('<div class="alert alert-danger">An error occurred. Please try again.</div>');
                }
            });
        });
    });
    </script>
</body>
</html> 