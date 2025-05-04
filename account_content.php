<?php
session_start();
include 'config.php';
// Fetch current user info
$username = $_SESSION['username'];
$sql = "SELECT profile_pic FROM users WHERE username = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$profilePic = $user && $user['profile_pic'] ? $user['profile_pic'] : null;
?>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow" style="border-radius: 18px; margin-top: 40px;">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <?php if ($profilePic): ?>
                            <img src="uploads/profile/<?php echo htmlspecialchars($profilePic); ?>" alt="Profile Picture" style="width:80px; height:80px; object-fit:cover; border-radius:50%; box-shadow: 0 4px 24px rgba(0, 119, 182, 0.10); border: 3px solid #43cea2;">
                        <?php else: ?>
                            <div style="display: inline-block; background: linear-gradient(135deg, #43cea2 0%, #0077b6 100%); border-radius: 50%; width: 80px; height: 80px; box-shadow: 0 4px 24px rgba(0, 119, 182, 0.10);">
                                <i class="fa-solid fa-user fa-3x text-white" style="line-height: 80px;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h5 class="card-title mb-4 text-center" style="color: #0077b6; font-weight: 700;">Account Management</h5>
                    <form id="accountForm" enctype="multipart/form-data">
                        <div class="mb-3 text-center">
                            <label for="profilePic" class="form-label" style="color: #0077b6;">Change Profile Picture</label>
                            <input class="form-control" type="file" id="profilePic" name="profilePic" accept="image/*">
                        </div>
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label" style="color: #0077b6;">Current Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1" title="Show/Hide Password"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="newUsername" class="form-label" style="color: #0077b6;">New Username</label>
                            <input type="text" class="form-control" id="newUsername" name="newUsername">
                        </div>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label" style="color: #0077b6;">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="newPassword" name="newPassword">
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1" title="Show/Hide Password"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label" style="color: #0077b6;">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword">
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1" title="Show/Hide Password"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                        <button type="button" id="showConfirmModal" class="btn w-100" style="background: linear-gradient(90deg, #0077b6 0%, #43cea2 100%); color: #fff; font-weight: 600;" title="Update Account">Update Account</button>
                        <div id="accountMsg" class="mt-3"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmModalLabel">Confirm Account Update</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="confirmSummary"></div>
        <div class="alert alert-warning mt-2">Are you sure you want to update your account with these changes?</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" title="Cancel">Cancel</button>
        <button type="button" id="confirmUpdateBtn" class="btn btn-primary" title="Confirm Update">Yes, Update</button>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Show confirmation modal with summary
$("#showConfirmModal").on("click", function() {
    var newUsername = $("#newUsername").val();
    var newPassword = $("#newPassword").val();
    var profilePic = $("#profilePic")[0].files[0];
    var summary = '<ul>';
    if (newUsername) summary += '<li><b>New Username:</b> ' + $('<div>').text(newUsername).html() + '</li>';
    if (newPassword) summary += '<li><b>New Password:</b> *******</li>';
    if (profilePic) summary += '<li><b>Profile Picture:</b> ' + $('<div>').text(profilePic.name).html() + '</li>';
    if (!newUsername && !newPassword && !profilePic) summary += '<li>No changes detected.</li>';
    summary += '</ul>';
    $("#confirmSummary").html(summary);
    // Move modal to body if not already there
    var $modal = $("#confirmModal");
    if (!$modal.parent().is("body")) {
        $modal.appendTo("body");
    }
    var modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
});
// On confirm, submit the form via AJAX
$("#confirmUpdateBtn").on("click", function() {
    var formData = new FormData(document.getElementById('accountForm'));
    var modalEl = document.getElementById('confirmModal');
    var modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modalInstance.hide();
    $("#accountMsg").html('<div class="alert alert-info fade show">Updating...</div>');
    $.ajax({
        url: 'update_account.php',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(response) {
            $("#accountMsg").hide().html(response).fadeIn(600);
            if (response.includes("Account updated successfully")) {
                modalEl.addEventListener('hidden.bs.modal', function handler() {
                    modalEl.removeEventListener('hidden.bs.modal', handler);
                    location.reload();
                });
            }
        },
        error: function() {
            $("#accountMsg").hide().html('<div class="alert alert-danger fade show">An error occurred. Please try again.</div>').fadeIn(600);
        }
    });
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
// Auto-close alerts after 3 seconds
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.classList.add('fade');
        setTimeout(function() {
            alert.style.display = 'none';
        }, 500);
    });
}, 3000);
</script>
<style>
#accountForm .form-control:focus {
    border-color: #43cea2;
    box-shadow: 0 0 0 0.2rem rgba(67, 206, 162, 0.15);
}
.card {
    border: none;
    background: #f8fcff;
}
/* Add user-select for Safari */
* {
    user-select: auto;
    -webkit-user-select: auto;
}
/* Add width: -webkit-fill-available for Edge */
.w-100 {
    width: 100% !important;
    width: -webkit-fill-available !important;
}
</style> 