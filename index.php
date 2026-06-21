<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
  header('Location: dashboard.php');
  exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if (empty($email) || empty($password)) {
    $error = 'Please fill in all fields.';
  } else {
    $stmt = $conn->prepare("
        SELECT u.id, u.first_name, u.mid_name, u.last_name, u.email, u.password, u.role, u.section_id, u.avatar,
               s.program, s.year, s.section
        FROM users u
        LEFT JOIN sections s ON u.section_id = s.id
        WHERE u.email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
      $user = $result->fetch_assoc();

      if (password_verify($password, $user['password'])) {
        $full_name = trim($user['first_name'] . ' ' . ($user['mid_name'] ? $user['mid_name'] . ' ' : '') . $user['last_name']);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $full_name;
        $_SESSION['fname'] = $user['first_name'];
        $_SESSION['mname'] = $user['mid_name'];
        $_SESSION['lname'] = $user['last_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['program'] = $user['program'];
        $_SESSION['year'] = $user['year'];
        $_SESSION['section_num'] = $user['section'];
        $_SESSION['avatar'] = $user['avatar'];

        $prog = $user['program'] ?? '';

        if ($prog !== '') {
          $_SESSION['section_clean'] = $prog . ' ' . ($user['year'] ?? '') . '-' . ($user['section'] ?? '');
        } else {
          $_SESSION['section_clean'] = null;
        }

        if (!empty($user['section_id'])) {
          $subs = $conn->prepare("SELECT subject_id FROM section_subjects WHERE section_id = ?");
          $subs->bind_param("i", $user['section_id']);
          $subs->execute();
          $sub_rows = $subs->get_result()->fetch_all(MYSQLI_ASSOC);
          $subs->close();

          if (!empty($sub_rows)) {
            $enroll = $conn->prepare("INSERT IGNORE INTO enrollments (user_id, subject_id) VALUES (?,?)");
            foreach ($sub_rows as $sub) {
              $enroll->bind_param("ii", $user['id'], $sub['subject_id']);
              $enroll->execute();
            }
            $enroll->close();
          }
        }

        header('Location: dashboard.php');
        exit();
      } else {
        $error = 'Invalid email or password.';
      }
    } else {
      $error = 'Invalid email or password.';
    }

    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iskonek – Sign In</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
</head>

<body>
  <div class="login-wrapper">
    <div class="login-card">
      <div class="d-flex align-items-center gap-3 mb-4">
        <div class="login-logo d-flex align-items-center justify-content-center">
          <span class="fw-bold fs-4" style="font-family:'Poppins',sans-serif;">IK</span>
        </div>
        <div>
          <h5 class="mb-0 fw-medium" style="font-family:'Poppins',sans-serif;">Iskonek</h5>
          <p class="mb-0 text-muted" style="font-size:13px;">Student Portal</p>
        </div>
      </div>

      <h4 class="fw-medium mb-1" style="font-family:'Poppins',sans-serif;">Welcome back!</h4>
      <p class="text-muted mb-4" style="font-size:14px;">Sign in to your account to continue.</p>

      <?php if (isset($_GET['registered'])): ?>
        <div class="mb-3 p-3 rounded-3" style="background:#f0faf5;border:1px solid #b3e6cc;color:#1a7a4c;font-size:13px;">
          <i class="bi bi-check-circle me-1"></i>Registration successful! You can now sign in.
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert-error mb-3"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="mb-3">
          <label class="form-label small fw-medium">Email address</label>
          <input type="email" name="email" class="form-control" placeholder="student@school.edu.ph"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label small fw-medium mb-0">Password</label>
            <a href="forgot_password.php" class="small text-decoration-none" style="color:var(--accent-green);">Forgot
              password?</a>
          </div>
          <div class="input-group">
            <input type="password" name="password" class="form-control" id="passwordInput" placeholder="••••••••"
              required>
            <button class="btn btn-outline-secondary" type="button" id="togglePassword"
              style="border-color:var(--border-color);border-radius:0 10px 10px 0;">
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="btn-login">Sign In</button>
      </form>

      <p class="text-center text-muted mt-4 mb-0" style="font-size:13px;">
        Don't have an account? <a href="register.php" class="text-decoration-none fw-medium"
          style="color:var(--accent-green);">Register</a>
      </p>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      /* Toggle Password Visibility */
      const toggleBtn = document.getElementById('togglePassword');
      if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
          const inp = document.getElementById('passwordInput');
          const icon = document.getElementById('eyeIcon');
          if (inp.type === 'password') {
            inp.type = 'text';
            icon.className = 'bi bi-eye-slash';
          } else {
            inp.type = 'password';
            icon.className = 'bi bi-eye';
          }
        });
      }

      /* Real-time Validation */
      const emailInput = document.querySelector('input[name="email"]');
      const passInput = document.getElementById('passwordInput');

      function validateField(input, condition, errorMsg) {
        let feedback = input.closest('.input-group') 
          ? input.closest('.input-group').nextElementSibling 
          : input.nextElementSibling;

        if (!feedback || !feedback.classList.contains('invalid-feedback')) {
          feedback = document.createElement('div');
          feedback.className = 'invalid-feedback';
          if (input.closest('.input-group')) {
            input.closest('.input-group').parentNode.appendChild(feedback);
          } else {
            input.parentNode.appendChild(feedback);
          }
        }

        if (condition) {
          input.classList.remove('is-invalid');
          input.classList.add('is-valid');
          feedback.textContent = '';
        } else {
          input.classList.remove('is-valid');
          input.classList.add('is-invalid');
          feedback.textContent = errorMsg;
        }
      }

      if (emailInput) {
        emailInput.addEventListener('input', function () {
          const val = this.value.trim();
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          validateField(this, emailRegex.test(val), 'Enter valid email address');
        });
      }

      if (passInput) {
        passInput.addEventListener('input', function () {
          const val = this.value;
          validateField(this, val.length >= 1, 'Password cannot be empty');
        });
      }
    });
  </script>
</body>

</html>