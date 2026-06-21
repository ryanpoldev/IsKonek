<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];

$user_query = $conn->prepare("SELECT id, first_name,mid_name,last_name, email, student_id, role, section_id, avatar FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

// $stmt = $conn->prepare("
//     SELECT u.first_name, u.last_name, s.name AS section_name, s.program, s.year 
//     FROM users u 
//     LEFT JOIN sections s ON u.section_id = s.id 
//     WHERE u.id = ?
// ");
// $stmt->bind_param("i", $user_id);
// $stmt->execute();
// $user_section = $stmt->get_result()->fetch_assoc();
// $stmt->close();
$user_section = $_SESSION['section_clean'];
// Access section info:
// echo $data['section_name'];

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

function avatarColor(string $name): string
{
  $colors = ['#33b77a', '#4a90d9', '#d23c3c', '#f5a623', '#9b59b6', '#1abc9c', '#e67e22', '#2980b9'];
  return $colors[abs(crc32($name)) % count($colors)];
}

function initials(string $name): string
{
  $parts = explode(' ', trim($name));
  $i = strtoupper(substr($parts[0], 0, 1));
  if (count($parts) > 1)
    $i .= strtoupper(substr(end($parts), 0, 1));
  return $i;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings – Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/settings.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
  
</head>

<body>
  <div class="app-wrapper">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
      <?php include 'includes/navbar.php'; ?>

      <div class="page-body">

        <div class="mb-4">
          <h2 class="hero-greeting mb-1">Settings ⚙️</h2>
          <p class="hero-sub text-muted">Manage your account and preferences.</p>
        </div>

        <div class="settings-layout">

          <!-- Settings Nav -->
          <nav class="settings-nav">
            <button class="settings-nav-item active" onclick="switchTab('profile', this)">
              <i class="bi bi-person"></i> Profile
            </button>
            <button class="settings-nav-item" onclick="switchTab('password', this)">
              <i class="bi bi-lock"></i> Password
            </button>
          </nav>

          <!-- Settings Panel -->
          <div class="settings-panel">

            <div class="settings-section active" id="tab-profile">
              <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                  <p class="settings-section-title">Profile Information</p>
                  <p class="settings-section-desc mb-0">Update your personal details.</p>
                </div>
                <button type="button" class="btn-upload-avatar" id="editProfileBtn" onclick="toggleProfileEdit()">
                  <i class="bi bi-pencil me-1"></i> Edit Profile
                </button>
              </div>

              <?php if ($success === 'profile'): ?>
                <div class="alert-success-inline"><i class="bi bi-check-circle me-1"></i>Profile updated successfully.</div>
              <?php endif; ?>
              <?php if ($error === 'profile'): ?>
                <div class="alert-error mb-3">Failed to update profile. Please try again.</div>
              <?php endif; ?>

              <div class="d-flex align-items-center gap-16 mb-4" style="gap:16px;">
                <div class="avatar-preview" id="avatarPreview" style="background:<?= avatarColor($user['first_name']) ?>;">
                  <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar">
                  <?php else: ?>
                    <?= initials($_SESSION['full_name']) ?>
                  <?php endif; ?>
                </div>
                <div>
                  <label class="btn-upload-avatar d-none" for="avatarInput" id="avatarUploadLabel">
                    <i class="bi bi-upload me-1"></i> Upload Photo
                  </label>
                  <input type="file" id="avatarInput" accept="image/*" style="display:none;" onchange="previewAvatar(this)">
                  <p class="text-muted mt-1 mb-0 d-none" id="avatarUploadHint" style="font-size:12px;">JPG, PNG up to 2MB</p>
                </div>
              </div>

              <form method="POST" action="actions/settings_actions.php" enctype="multipart/form-data" id="profileForm">
                <input type="hidden" name="action" value="update_profile">
                <input type="hidden" name="avatar_data" id="avatarData">

                <div class="row g-3 mb-3">
                  <div class="col-12">
                    <label class="form-label small fw-medium">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required maxlength="120"
                      value="<?= htmlspecialchars($_SESSION['full_name']) ?>"
                      style="border-radius:10px;border-color:var(--border-color);" disabled>
                  </div>
                  <div class="col-12">
                    <label class="form-label small fw-medium">Email Address</label>
                    <input type="email" name="email" class="form-control" required maxlength="160"
                      value="<?= htmlspecialchars($user['email']) ?>"
                      style="border-radius:10px;border-color:var(--border-color);" disabled>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label small fw-medium">Student ID</label>
                    <input type="text" name="student_id" class="form-control" maxlength="30"
                      value="<?= htmlspecialchars($user['student_id']) ?>"
                      style="border-radius:10px;border-color:var(--border-color);" disabled>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label small fw-medium">Program</label>
                    <select name="course" class="form-select" disabled>
                      <optgroup label="Current Program" disabled>
                        <option selected>
                          <?= isset($_SESSION['program']) ? htmlspecialchars($_SESSION['program']) : '' ?>
                        </option>
                      </optgroup>
                      <optgroup>
                        <option value="BSIT" <?= ($_POST['course'] ?? '') === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
                        <option value="BSCS" <?= ($_POST['course'] ?? '') === 'BSCS' ? 'selected' : '' ?>>BSCS</option>
                        <option value="BSIS" <?= ($_POST['course'] ?? '') === 'BSIS' ? 'selected' : '' ?>>BSIS</option>
                        <option value="ACT" <?= ($_POST['course'] ?? '') === 'ACT' ? 'selected' : '' ?>>ACT</option>
                      </optgroup>
                    </select>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label small fw-medium">Year</label>
                    <input type="text" name="year" class="form-control" maxlength="40"
                      value="<?= htmlspecialchars($_SESSION['year'] ?? '') ?>" placeholder="1"
                      style="border-radius:10px;border-color:var(--border-color);" disabled>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label small fw-medium">Section</label>
                    <input type="text" name="section" class="form-control" maxlength="40"
                      value="<?= htmlspecialchars($user_section) ?>" placeholder="e.g. BSIT 2-1"
                      style="border-radius:10px;border-color:var(--border-color);" disabled>
                  </div>
                </div>

                <button type="submit" class="btn-save d-none" id="saveProfileBtn">Save Changes</button>
              </form>
            </div>

            <div class="settings-section" id="tab-password">
              <p class="settings-section-title">Change Password</p>
              <p class="settings-section-desc">Make sure your new password is at least 8 characters.</p>

              <?php if ($success === 'password'): ?>
                <div class="alert-success-inline"><i class="bi bi-check-circle me-1"></i>Password changed successfully.</div>
              <?php endif; ?>
              <?php if ($error === 'password'): ?>
                <div class="alert-error mb-3">Failed to change password. Please check your current password.</div>
              <?php endif; ?>

              <form method="POST" action="actions/settings_actions.php">
                <input type="hidden" name="action" value="change_password">

                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label small fw-medium">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required
                      placeholder="Enter current password" style="border-radius:10px;border-color:var(--border-color);">
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label small fw-medium">New Password</label>
                    <input type="password" name="new_password" class="form-control" required minlength="8"
                      placeholder="Min 8 characters" style="border-radius:10px;border-color:var(--border-color);">
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label small fw-medium">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required
                      placeholder="Repeat new password" style="border-radius:10px;border-color:var(--border-color);">
                  </div>
                  <div class="col-12 mt-1">
                    <button type="submit" class="btn-save">Change Password</button>
                  </div>
                </div>
              </form>
            </div>

          </div>
        </div>

      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    let isEditingProfile = false;

    function toggleProfileEdit() {
      isEditingProfile = !isEditingProfile;

      const form = document.getElementById('profileForm');
      const editBtn = document.getElementById('editProfileBtn');
      const saveBtn = document.getElementById('saveProfileBtn');
      const uploadLabel = document.getElementById('avatarUploadLabel');
      const uploadHint = document.getElementById('avatarUploadHint');

      // Find all interactive elements inside the profile form
      const fields = form.querySelectorAll('input, select, textarea');

      if (isEditingProfile) {
        // Unlock Form Elements
        fields.forEach(field => field.removeAttribute('disabled'));

        // Update Button Appearance to acting Cancel/Lock mode
        editBtn.innerHTML = '<i class="bi bi-x-lg me-1"></i> Cancel';
        editBtn.classList.replace('btn-upload-avatar', 'btn-light');

        // Reveal functional actions
        saveBtn.classList.remove('d-none');
        uploadLabel.classList.remove('d-none');
        uploadHint.classList.remove('d-none');
      } else {
        // Lock Form Elements
        fields.forEach(field => field.setAttribute('disabled', 'true'));

        // Reset Button UI
        editBtn.innerHTML = '<i class="bi bi-pencil me-1"></i> Edit Profile';
        editBtn.classList.replace('btn-light', 'btn-upload-avatar');

        // Hide functional updates
        saveBtn.classList.add('d-none');
        uploadLabel.classList.add('d-none');
        uploadHint.classList.add('d-none');

        // Optional: Resets changes back to default saved states if user cancels out
        form.reset();
      }
    }
    document.addEventListener('DOMContentLoaded', function () {
      const toggleBtn = document.getElementById('sidebarToggle');
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebarOverlay');
      if (toggleBtn) toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); overlay?.classList.toggle('show'); });
      if (overlay) overlay.addEventListener('click', () => { sidebar.classList.remove('show'); overlay.classList.remove('show'); });

      // Auto-show correct tab if redirected with success/error
      const params = new URLSearchParams(window.location.search);
      const tab = params.get('tab');
      if (tab) {
        const btn = document.querySelector(`.settings-nav-item[onclick*="${tab}"]`);
        if (btn) switchTab(tab, btn);
      }
    });

    function switchTab(tab, el) {
      document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
      document.querySelectorAll('.settings-nav-item').forEach(b => b.classList.remove('active'));
      document.getElementById('tab-' + tab).classList.add('active');
      el.classList.add('active');
    }

    function previewAvatar(input) {
      const file = input.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = function (e) {
        const preview = document.getElementById('avatarPreview');
        preview.innerHTML = `<img src="${e.target.result}" alt="Avatar">`;
        document.getElementById('avatarData').value = e.target.result;
      };
      reader.readAsDataURL(file);
    }
  </script>
</body>

</html>