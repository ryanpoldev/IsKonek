<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

/* ── Mock / DB data ── */
$user_name = $_SESSION['full_name'] ?? 'Student';
$first_name = explode(' ', $user_name)[0];

$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
// Implement it to get it from database
$user_id = $_SESSION['user_id'];

$subjects_query = $conn->prepare("
    SELECT s.* FROM subjects s
    JOIN enrollments e ON e.subject_id = s.id
    WHERE e.user_id = ?
    ORDER BY s.code
");
$subjects_query->bind_param("i", $user_id);
$subjects_query->execute();
$subjects = $subjects_query->get_result()->fetch_all(MYSQLI_ASSOC);

$ann_query = $conn->prepare("
    SELECT a.title, a.created_at, s.code AS subject, s.color
    FROM announcements a
    JOIN subjects s ON s.id = a.subject_id
    JOIN enrollments e ON e.subject_id = a.subject_id AND e.user_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
");
$ann_query->bind_param("i", $user_id);
$ann_query->execute();
$ann_rows = $ann_query->get_result()->fetch_all(MYSQLI_ASSOC);

$announcements = array_map(function($a) {
    $diff = time() - strtotime($a['created_at']);
    if ($diff < 60)          $time = 'just now';
    elseif ($diff < 3600)    $time = floor($diff / 60) . 'm ago';
    elseif ($diff < 86400)   $time = floor($diff / 3600) . 'h ago';
    else                     $time = date('M j', strtotime($a['created_at']));
    return ['title' => $a['title'], 'subject' => $a['subject'], 'color' => $a['color'], 'time' => $time];
}, $ann_rows);

$stats = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard – Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
</head>
<body>
<div class="app-wrapper">

  <!-- Sidebar overlay (mobile) -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Sidebar -->
  <?php include 'includes/sidebar.php'; ?>

  <!-- Main -->
  <div class="main-content">

    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Page body -->
    <div class="page-body">

      <!-- Hero -->
      <div class="mb-4">
        <h2 class="hero-greeting mb-1"><?= $greeting ?>, <?= htmlspecialchars($first_name) ?>! 👋</h2>
        <p class="hero-sub text-muted">Here's your academic overview for this semester.</p>
      </div>

      <!-- Stats row -->
      <div class="row g-3 mb-4">
        <?php foreach ($stats as $s): ?>
        <div class="col-12 col-sm-6 col-xl-3">
          <div class="stat-card h-100">
            <p class="stat-label mb-1"><?= $s['label'] ?></p>
            <p class="stat-value <?= $s['color'] ?> mb-1"><?= $s['value'] ?></p>
            <p class="stat-sub mb-0"><?= $s['sub'] ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Active Subjects -->
      <div class="d-flex align-items-center justify-content-between mb-1">
        <p class="section-header mb-0">Active Subjects</p>
        <a href="#" class="small text-decoration-none" style="color:var(--accent-green);font-size:13px;">View all</a>
      </div>
      <hr class="section-divider">

      <div class="row g-3 mb-2">
        <?php foreach ($subjects as $subj): ?>
        <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
          <a href="subject.php?code=<?= urlencode($subj['code']) ?>"
             class="subject-card d-flex flex-column text-decoration-none"
             data-search="<?= htmlspecialchars($subj['code'] . ' ' . $subj['name'] . ' ' . $subj['instructor']) ?>">
            <div class="d-flex align-items-start justify-content-between mb-2">
              <p class="subject-code mb-0"><?= htmlspecialchars($subj['code']) ?></p>
              <span class="badge subject-badge text-white" style="background-color:<?= $subj['color'] ?>;"><?= $subj['units'] ?> units</span>
            </div>
            <p class="subject-name mb-auto"><?= htmlspecialchars($subj['name']) ?></p>
            <p class="subject-instructor mb-0 mt-2">
              <i class="bi bi-person me-1"></i><?= htmlspecialchars($subj['instructor']) ?>
            </p>
          </a>
        </div>
        <?php endforeach; ?>

        <!-- Enroll card -->
        <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
          <a href="#" class="enroll-card" data-bs-toggle="modal" data-bs-target="#enrollModal">
            <i class="bi bi-plus-circle"></i>
            <span>Enroll another subject</span>
          </a>
        </div>
      </div>

      <!-- Bottom row: Recent Announcements + Quick Actions -->
      <div class="row g-3 mt-2">

        <!-- Recent Announcements -->
        <div class="col-12 col-lg-7">
          <div class="stat-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <p class="section-header mb-0">Recent Announcements</p>
              <a href="announcements.php" class="small text-decoration-none" style="color:var(--accent-green);font-size:13px;">View all</a>
            </div>
            <?php foreach ($announcements as $a): ?>
            <div class="d-flex align-items-start gap-3 py-2 border-bottom" style="border-color:var(--border-color)!important;">
              <span class="badge text-white mt-1" style="background-color:<?= $a['color'] ?>;font-size:11px;padding:3px 7px;border-radius:6px;white-space:nowrap;">
                <?= htmlspecialchars($a['subject']) ?>
              </span>
              <div class="flex-grow-1">
                <p class="mb-0" style="font-size:14px;"><?= htmlspecialchars($a['title']) ?></p>
              </div>
              <span class="text-muted small" style="white-space:nowrap;font-size:12px;"><?= $a['time'] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-12 col-lg-5">
          <div class="stat-card h-100">
            <p class="section-header mb-3">Quick Actions</p>
            <div class="d-flex flex-column gap-2">
              <a href="forums.php" class="btn btn-outline-secondary text-start d-flex align-items-center gap-2" style="border-radius:10px;border-color:var(--border-color);font-size:14px;">
                <i class="bi bi-chat-dots-fill" style="color:var(--accent-green);"></i> Go to Forums
              </a>
              <a href="pages/kanban.php" class="btn btn-outline-secondary text-start d-flex align-items-center gap-2" style="border-radius:10px;border-color:var(--border-color);font-size:14px;">
                <i class="bi bi-kanban-fill" style="color:#4a90d9;"></i> Open Kanban Board
              </a>
              <a href="calendar.php" class="btn btn-outline-secondary text-start d-flex align-items-center gap-2" style="border-radius:10px;border-color:var(--border-color);font-size:14px;">
                <i class="bi bi-calendar3" style="color:#f5a623;"></i> Campus Calendar
              </a>
              <a href="files.php" class="btn btn-outline-secondary text-start d-flex align-items-center gap-2" style="border-radius:10px;border-color:var(--border-color);font-size:14px;">
                <i class="bi bi-folder-fill" style="color:#9b59b6;"></i> File Repository
              </a>
            </div>
          </div>
        </div>

      </div>
    </div><!-- /page-body -->
  </div><!-- /main-content -->
</div><!-- /app-wrapper -->

<!-- Enroll Modal -->
<div class="modal fade" id="enrollModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:20px;border:1px solid var(--border-color);">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-medium" style="font-family:'Poppins',sans-serif;">Enroll in a Subject</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-3">
        <div id="enrollError" class="alert-error mb-3" style="display:none;"></div>
        <form id="enrollForm" method="POST" action="actions/enroll_actions.php">
          <div class="mb-3">
            <label class="form-label small fw-medium">Subject Code</label>
            <input type="text" name="subject_code" class="form-control" placeholder="e.g. ITEC 109" required
                   style="border-radius:10px;border-color:var(--border-color);">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium">Enrollment Key</label>
            <input type="text" name="enroll_key" class="form-control" placeholder="Enter key provided by instructor" required
                   style="border-radius:10px;border-color:var(--border-color);">
          </div>
          <button type="submit" class="btn-login mt-1">Enroll</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Toast container -->
<div id="toastContainer" class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  /* Mobile sidebar */
  const toggleBtn = document.getElementById('sidebarToggle');
  const sidebar   = document.getElementById('sidebar');
  const overlay   = document.getElementById('sidebarOverlay');

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('show');
      overlay && overlay.classList.toggle('show');
    });
  }
  if (overlay) {
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('show');
      overlay.classList.remove('show');
    });
  }

  /* Global search */
  const searchInput = document.getElementById('globalSearch');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      const q = this.value.toLowerCase().trim();
      document.querySelectorAll('.subject-card[data-search]').forEach(card => {
        const text = card.dataset.search.toLowerCase();
        card.closest('.col').style.display = (!q || text.includes(q)) ? '' : 'none';
      });
    });
  }

  /* Enroll form AJAX */
  const enrollForm = document.getElementById('enrollForm');
  if (enrollForm) {
    enrollForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const fd = new FormData(this);
      fetch('actions/enroll_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('enrollModal')).hide();
            showToast('Enrolled successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
          } else {
            const err = document.getElementById('enrollError');
            err.textContent = data.message;
            err.style.display = 'block';
          }
        });
    });
  }

  /* Toast */
  window.showToast = function (msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    const id = 'toast-' + Date.now();
    container.insertAdjacentHTML('beforeend', `
      <div id="${id}" class="toast align-items-center text-bg-${type} border-0" role="alert">
        <div class="d-flex">
          <div class="toast-body">${msg}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>`);
    const el = document.getElementById(id);
    new bootstrap.Toast(el, { delay: 3500 }).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
  };
});
</script>
</body>
</html>