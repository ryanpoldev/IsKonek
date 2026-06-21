<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$code = $_GET['code'] ?? '';

if (empty($code)) {
    header('Location: dashboard.php');
    exit();
}

// Fetch subject + verify enrollment
$stmt = $conn->prepare("
    SELECT s.* FROM subjects s
    JOIN enrollments e ON e.subject_id = s.id
    WHERE s.code = ? AND e.user_id = ?
");
$stmt->bind_param("si", $code, $user_id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$subject) {
    header('Location: dashboard.php?error=not_enrolled');
    exit();
}

$subject_id = $subject['id'];

// Fetch Announcements
$ann_stmt = $conn->prepare("
    SELECT a.*, u.first_name, u.last_name, u.role 
    FROM announcements a
    JOIN users u ON a.created_by = u.id
    WHERE a.subject_id = ?
    ORDER BY a.created_at DESC
");
$ann_stmt->bind_param("i", $subject_id);
$ann_stmt->execute();
$announcements = $ann_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ann_stmt->close();

// Fetch People (Classmates)
$ppl_stmt = $conn->prepare("
    SELECT u.first_name, u.last_name, u.role 
    FROM users u
    JOIN enrollments e ON e.user_id = u.id
    WHERE e.subject_id = ?
    ORDER BY u.role ASC, u.last_name ASC
");
$ppl_stmt->bind_param("i", $subject_id);
$ppl_stmt->execute();
$people = $ppl_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ppl_stmt->close();

function timeAgo(string $datetime): string {
    $diff = max(0, time() - strtotime($datetime));
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($subject['code']) ?> - Iskonek</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .subject-header {
      background: <?= htmlspecialchars($subject['color']) ?>;
      color: white;
      padding: 30px 24px;
      border-radius: 20px;
      margin-bottom: 24px;
    }
    .nav-tabs .nav-link {
      color: var(--text-secondary);
      font-weight: 500;
      border: none;
      border-bottom: 2px solid transparent;
      padding: 10px 16px;
      margin-right: 8px;
    }
    .nav-tabs .nav-link.active {
      color: <?= htmlspecialchars($subject['color']) ?>;
      border-bottom-color: <?= htmlspecialchars($subject['color']) ?>;
      background: none;
    }
    .nav-tabs { border-bottom: 1px solid var(--border-color); margin-bottom: 20px; }
    .tab-card {
      background: white;
      border: 1px solid var(--border-color);
      border-radius: 16px;
      padding: 20px;
    }
  </style>
</head>
<body>
<div class="app-wrapper">
  <?php include 'includes/sidebar.php'; ?>

  <div class="main-content">
    <?php include 'includes/navbar.php'; ?>

    <div class="page-body">
      
      <div class="subject-header">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h2 class="mb-1 fw-bold" style="font-family:'Poppins',sans-serif;"><?= htmlspecialchars($subject['code']) ?></h2>
            <p class="mb-0 fs-5"><?= htmlspecialchars($subject['name']) ?></p>
            <p class="mb-0 mt-2 opacity-75 small">
              <i class="bi bi-person me-1"></i> <?= htmlspecialchars($subject['instructor']) ?> &bull; 
              <?= htmlspecialchars($subject['units']) ?> Units
            </p>
          </div>
        </div>
      </div>

      <ul class="nav nav-tabs" id="subjectTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#announcements" type="button">Announcements</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#files" type="button">Files</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tasks" type="button">Tasks</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#people" type="button">People</button>
        </li>
      </ul>

      <div class="tab-content">
        
        <div class="tab-pane fade show active" id="announcements">
          <?php if (empty($announcements)): ?>
            <div class="tab-card text-center text-muted py-5">
              <i class="bi bi-megaphone fs-1 mb-2 opacity-50 d-block"></i>
              No announcements yet.
            </div>
          <?php else: ?>
            <div class="tab-card p-0">
              <div class="list-group list-group-flush" style="border-radius:16px; overflow:hidden;">
                <?php foreach ($announcements as $ann): ?>
                  <div class="list-group-item p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <strong class="text-dark"><?= htmlspecialchars($ann['title']) ?></strong>
                      <small class="text-muted"><?= timeAgo($ann['created_at']) ?></small>
                    </div>
                    <p class="mb-2 text-secondary small"><?= nl2br(htmlspecialchars($ann['body'])) ?></p>
                    <small class="text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']) ?> (<?= htmlspecialchars($ann['role']) ?>)</small>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="files">
          <div class="tab-card text-center text-muted py-5">
            <i class="bi bi-folder fs-1 mb-2 opacity-50 d-block"></i>
            Files module coming soon.
          </div>
        </div>

        <div class="tab-pane fade" id="tasks">
          <div class="tab-card text-center text-muted py-5">
            <i class="bi bi-check2-square fs-1 mb-2 opacity-50 d-block"></i>
            Tasks module coming soon.
          </div>
        </div>

        <div class="tab-pane fade" id="people">
          <div class="tab-card">
            <h6 class="mb-3 fw-bold">Class Roster (<?= count($people) ?>)</h6>
            <div class="row g-3">
              <?php foreach ($people as $person): ?>
                <div class="col-12 col-md-6 col-lg-4">
                  <div class="d-flex align-items-center gap-3 p-2 border rounded">
                    <div class="avatar-circle-sm bg-light text-dark d-flex align-items-center justify-content-center">
                      <?= strtoupper(substr($person['first_name'], 0, 1)) ?>
                    </div>
                    <div>
                      <p class="mb-0 fw-medium small"><?= htmlspecialchars($person['first_name'] . ' ' . $person['last_name']) ?></p>
                      <small class="text-muted" style="font-size:11px;"><?= htmlspecialchars($person['role']) ?></small>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const toggleBtn = document.getElementById('sidebarToggle');
  const sidebar   = document.getElementById('sidebar');
  if (toggleBtn) toggleBtn.addEventListener('click', () => sidebar.classList.toggle('show'));
});
</script>
</body>
</html>