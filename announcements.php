<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user_id  = $_SESSION['user_id'];
$is_admin = strtolower($_SESSION["role"] ?? "") === "admin";

$subjects_query = $conn->prepare("
    SELECT s.id, s.code, s.name, s.color
    FROM subjects s
    JOIN enrollments e ON e.subject_id = s.id
    WHERE e.user_id = ?
    ORDER BY s.code
");
$subjects_query->bind_param("i", $user_id);
$subjects_query->execute();
$enrolled_subjects = $subjects_query->get_result()->fetch_all(MYSQLI_ASSOC);

$filter_subject_id = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;

if ($filter_subject_id > 0) {
    $ann_query = $conn->prepare("
        SELECT a.id, a.title, a.body, a.created_at,
               CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS full_name, u.role AS poster_role,
               s.code AS subject_code, s.color AS subject_color, s.id AS subject_id
        FROM announcements a
        JOIN users u    ON u.id = a.created_by
        JOIN subjects s ON s.id = a.subject_id
        JOIN enrollments e ON e.subject_id = a.subject_id AND e.user_id = ?
        WHERE a.subject_id = ?
        ORDER BY a.created_at DESC
    ");
    $ann_query->bind_param("ii", $user_id, $filter_subject_id);
} else {
    $ann_query = $conn->prepare("
        SELECT a.id, a.title, a.body, a.created_at,
               CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS full_name, u.role AS poster_role,
               s.code AS subject_code, s.color AS subject_color, s.id AS subject_id
        FROM announcements a
        JOIN users u    ON u.id = a.created_by
        JOIN subjects s ON s.id = a.subject_id
        JOIN enrollments e ON e.subject_id = a.subject_id AND e.user_id = ?
        ORDER BY a.created_at DESC
    ");
    $ann_query->bind_param("i", $user_id);
}
$ann_query->execute();
$announcements = $ann_query->get_result()->fetch_all(MYSQLI_ASSOC);
$total_count   = count($announcements);

function avatarColor(string $name): string {
    $colors = ['#33b77a','#4a90d9','#d23c3c','#f5a623','#9b59b6','#1abc9c','#e67e22','#2980b9'];
    return $colors[abs(crc32($name)) % count($colors)];
}

function initials(string $name): string {
    $parts = explode(' ', trim($name));
    $i = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $i .= strtoupper(substr(end($parts), 0, 1));
    return $i;
}

function timeAgo(string $datetime): string {
    // max(0, ...) stop negative numbers
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
  <title>Announcements – Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
  <style>
    /* ── Announcement Layout (mirrors .forum-layout) ── */
    .ann-layout {
      display: grid;
      grid-template-columns: 200px 1fr;
      gap: 24px;
      align-items: start;
    }

    /* ── Categories Sidebar ── */
    .ann-categories {
      position: sticky;
      top: calc(var(--navbar-height) + 24px);
    }

    .categories-label {
      font-family: var(--font-heading);
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 1px;
      color: var(--text-muted);
      margin-bottom: 10px;
      padding: 0 4px;
    }

    .category-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 8px 12px;
      border-radius: 10px;
      font-size: 14px;
      color: var(--text-secondary);
      text-decoration: none;
      transition: background-color 0.15s ease;
      cursor: pointer;
      border: none;
      background: none;
      width: 100%;
      text-align: left;
    }

    .category-item:hover  { background-color: var(--hover-bg); color: var(--text-primary); }
    .category-item.active { background-color: var(--hover-bg); color: var(--text-primary); font-weight: 500; }

    .category-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      flex-shrink: 0;
      margin-right: 8px;
    }

    /* ── Announcement Panel (mirrors .forum-panel) ── */
    .ann-panel {
      background: white;
      border: 1px solid var(--border-color);
      border-radius: 20px;
      overflow: hidden;
    }

    .ann-panel-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 18px 24px;
      border-bottom: 1px solid var(--border-color);
    }

    .ann-panel-title {
      font-family: var(--font-heading);
      font-size: 15px;
      font-weight: 600;
      color: var(--text-primary);
      margin: 0;
    }

    /* ── Post Button (mirrors .btn-new-topic) ── */
    .btn-new-ann {
      background-color: var(--accent-green);
      color: white;
      border: none;
      border-radius: 10px;
      padding: 8px 18px;
      font-size: 14px;
      font-weight: 500;
      font-family: var(--font-heading);
      transition: background-color 0.15s;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .btn-new-ann:hover { background-color: #28a06a; color: white; }

    /* ── Announcement Card ── */
    .ann-card {
      display: flex;
      gap: 14px;
      padding: 18px 24px;
      border-bottom: 1px solid var(--border-color);
      transition: background-color 0.15s ease;
    }

    .ann-card:last-child { border-bottom: none; }
    .ann-card:hover { background-color: #f9fffe; }

    .ann-avatar {
      width: 38px; height: 38px; min-width: 38px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      color: white;
      font-weight: 600; font-size: 13px;
      font-family: var(--font-heading);
      flex-shrink: 0;
    }

    .ann-content { flex: 1; min-width: 0; }

    .ann-meta {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 6px;
    }

    .ann-poster-name { font-weight: 600; font-size: 13px; color: var(--text-primary); }
    .ann-poster-role { font-size: 12px; color: var(--text-muted); }

    .ann-subject-badge {
      font-size: 10px;
      padding: 1px 7px;
      border-radius: 20px;
      font-weight: 600;
      color: white;
    }

    .ann-time { font-size: 12px; color: var(--text-muted); margin-left: auto; }

    .ann-title {
      font-family: var(--font-heading);
      font-weight: 600;
      font-size: 15px;
      color: var(--text-primary);
      margin-bottom: 6px;
      line-height: 1.3;
    }

    .ann-body {
      font-size: 14px;
      color: var(--text-secondary);
      line-height: 1.6;
      white-space: pre-wrap;
      word-break: break-word;
      margin: 0;
    }

    .ann-body-collapsed {
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .btn-read-more {
      background: none; border: none; padding: 0;
      color: var(--accent-green);
      font-size: 13px; font-weight: 500;
      cursor: pointer; margin-top: 4px;
      display: inline-block;
    }

    .btn-read-more:hover { text-decoration: underline; }

    .btn-delete-ann {
      background: none; border: none;
      padding: 3px 8px;
      color: var(--text-muted);
      font-size: 12px; cursor: pointer;
      border-radius: 6px;
      transition: color 0.15s, background 0.15s;
      margin-top: 8px;
    }



    .btn-delete-ann:hover { color: var(--accent-red); background: #fff0f0; }

    /* ── Empty State ── */
    .ann-empty {
      padding: 60px 24px;
      text-align: center;
      color: var(--text-muted);
    }

    .ann-empty i { font-size: 40px; margin-bottom: 12px; display: block; opacity: 0.4; }


    /* ── Modal ── */
    .modal-content {
      border-radius: 20px !important;
      border: 1px solid var(--border-color) !important;
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--accent-green);
      box-shadow: 0 0 0 3px rgba(51,183,122,0.15);
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
      .ann-layout { grid-template-columns: 1fr; }
      .ann-categories {
        position: static;
        display: flex; gap: 8px; flex-wrap: wrap;
      }
      .category-item { padding: 6px 12px; width: auto; border: 1px solid var(--border-color); }
      .categories-label { display: none; }
      .ann-card { padding: 14px 16px; }
      .ann-panel-header { padding: 14px 16px; }
    }
  </style>
</head>
<body>
<div class="app-wrapper">
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  <?php include 'includes/sidebar.php'; ?>

  <div class="main-content">
    <?php include 'includes/navbar.php'; ?>

    <div class="page-body">

      <!-- Hero -->
      <div class="mb-4">
        <h2 class="hero-greeting mb-1">Announcements 📢</h2>
        <p class="hero-sub text-muted">Stay up to date with important updates from your subjects.</p>
      </div>

      <div class="ann-layout">

        <!-- Subject Filter Sidebar -->
        <aside class="ann-categories">
          <p class="categories-label">CATEGORIES</p>

          <a href="announcements.php"
             class="category-item <?= $filter_subject_id === 0 ? 'active' : '' ?>">
            <span class="d-flex align-items-center">
              <span class="category-dot" style="background:#33b77a;"></span>
              All Subjects
            </span>
            <?php if ($filter_subject_id === 0): ?>
              <span class="badge rounded-pill text-white" style="background:#d23c3c;font-size:10px;"><?= $total_count ?></span>
            <?php endif; ?>
          </a>

          <?php foreach ($enrolled_subjects as $subj): ?>
          <a href="announcements.php?subject=<?= $subj['id'] ?>"
             class="category-item <?= $filter_subject_id === (int)$subj['id'] ? 'active' : '' ?>">
            <span class="d-flex align-items-center">
              <span class="category-dot" style="background:<?= htmlspecialchars($subj['color']) ?>;"></span>
              <?= htmlspecialchars($subj['code']) ?>
            </span>
          </a>
          <?php endforeach; ?>
        </aside>

        <!-- Main Panel -->
        <div class="ann-panel">

          <div class="ann-panel-header">
            <p class="ann-panel-title">
              <?php if ($filter_subject_id > 0):
                $active_subj = array_values(array_filter($enrolled_subjects, fn($s) => (int)$s['id'] === $filter_subject_id));
                echo htmlspecialchars($active_subj[0]['code'] ?? 'Subject') . ' — Announcements';
              else: ?>
                All Announcements
              <?php endif; ?>
            </p>

            <?php if ($is_admin): ?>
              <button class="btn-new-ann" data-bs-toggle="modal" data-bs-target="#newAnnModal">
                <i class="bi bi-plus-lg"></i> Post Announcement
              </button>
            <?php endif; ?>
          </div>

          <!-- List -->
          <?php if (empty($announcements)): ?>
            <div class="ann-empty">
              <i class="bi bi-megaphone"></i>
              <p class="mb-1 fw-medium" style="font-size:15px;">No announcements yet</p>
              <p class="text-muted" style="font-size:13px;">
                <?= $is_admin ? 'Post the first announcement for your class.' : 'Check back later for updates.' ?>
              </p>
            </div>
          <?php else: ?>
            <?php foreach ($announcements as $ann): ?>
              <?php $is_long = mb_strlen($ann['body'] ?? '') > 280; ?>
              <div class="ann-card" id="ann-<?= $ann['id'] ?>">

                <div class="ann-avatar" style="background:<?= avatarColor($ann['full_name']) ?>;">
                  <?= initials($ann['full_name']) ?>
                </div>

                <div class="ann-content">
                  <div class="ann-meta">
                    <span class="ann-poster-name"><?= htmlspecialchars($ann['full_name']) ?></span>
                    <span class="ann-poster-role"><?= htmlspecialchars($ann['poster_role']) ?></span>
                    <?php if ($filter_subject_id === 0): ?>
                      <span class="ann-subject-badge" style="background:<?= htmlspecialchars($ann['subject_color']) ?>;">
                        <?= htmlspecialchars($ann['subject_code']) ?>
                      </span>
                    <?php endif; ?>
                    <span class="ann-time"><?= timeAgo($ann['created_at']) ?></span>
                  </div>

                  <p class="ann-title"><?= htmlspecialchars($ann['title']) ?></p>

                  <?php if (!empty($ann['body'])): ?>
                    <p class="ann-body <?= $is_long ? 'ann-body-collapsed' : '' ?>"
                       id="body-<?= $ann['id'] ?>"><?= htmlspecialchars($ann['body']) ?></p>
                    <?php if ($is_long): ?>
                      <button class="btn-read-more" onclick="toggleBody(<?= $ann['id'] ?>, this)">Read more</button>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php if ($is_admin): ?>
                    <button class="btn-delete-ann" onclick="deleteAnnouncement(<?= $ann['id'] ?>)">
                      <i class="bi bi-trash3 me-1"></i>Delete
                    </button>
                  <?php endif; ?>
                </div>

              </div>
            <?php endforeach; ?>
          <?php endif; ?>

        </div><!-- /.ann-panel -->

      </div><!-- /.ann-layout -->
    </div><!-- /.page-body -->
  </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->


<?php if ($is_admin): ?>
<div class="modal fade" id="newAnnModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-medium" style="font-family:var(--font-heading);">Post Announcement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-3">
        <div id="annError" class="alert-error mb-3" style="display:none;"></div>
        <form id="newAnnForm">
          <div class="mb-3">
            <label class="form-label small fw-medium">Subject</label>
            <select name="subject_id" class="form-select" required
                    style="border-radius:10px;border-color:var(--border-color);">
              <option value="">— Select subject —</option>
              <?php foreach ($enrolled_subjects as $subj): ?>
                <option value="<?= $subj['id'] ?>"
                  <?= $filter_subject_id === (int)$subj['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($subj['code'] . ' – ' . $subj['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium">Title</label>
            <input type="text" name="title" class="form-control" required maxlength="255"
                   placeholder="e.g. Quiz 3 scheduled for next Wednesday"
                   style="border-radius:10px;border-color:var(--border-color);">
          </div>
          <div class="mb-4">
            <label class="form-label small fw-medium">Message <span class="text-muted fw-normal">(optional)</span></label>
            <textarea name="body" class="form-control" rows="4"
                      placeholder="Add details, reminders, or instructions..."
                      style="border-radius:10px;border-color:var(--border-color);resize:vertical;"></textarea>
          </div>
          <button type="submit" class="btn-login">Post Announcement</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>


<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-medium" style="font-family:var(--font-heading);">Delete Announcement?</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2 pb-1">
        <p class="text-muted small mb-0">This action cannot be undone.</p>
      </div>
      <div class="modal-footer border-0 pt-2">
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-danger" id="confirmDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const toggleBtn = document.getElementById('sidebarToggle');
  const sidebar   = document.getElementById('sidebar');
  const overlay   = document.getElementById('sidebarOverlay');
  if (toggleBtn) toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); overlay?.classList.toggle('show'); });
  if (overlay)   overlay.addEventListener('click', () => { sidebar.classList.remove('show'); overlay.classList.remove('show'); });

  window.toggleBody = function (id, btn) {
    const el = document.getElementById('body-' + id);
    const isCollapsed = el.classList.contains('ann-body-collapsed');
    el.classList.toggle('ann-body-collapsed', !isCollapsed);
    btn.textContent = isCollapsed ? 'Show less' : 'Read more';
  };

  <?php if ($is_admin): ?>
  document.getElementById('newAnnForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'create_announcement');
    const errEl = document.getElementById('annError');
    errEl.style.display = 'none';

    fetch('actions/announcement_actions.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          bootstrap.Modal.getInstance(document.getElementById('newAnnModal')).hide();
          window.location.reload();
        } else {
          errEl.textContent = data.message || 'Failed to post announcement.';
          errEl.style.display = 'block';
        }
      })
      .catch(() => {
        errEl.textContent = 'Network error. Please try again.';
        errEl.style.display = 'block';
      });
  });
  <?php endif; ?>

  let pendingDeleteId = null;
  const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));

  window.deleteAnnouncement = function (id) {
    pendingDeleteId = id;
    deleteModal.show();
  };

  document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
    if (!pendingDeleteId) return;
    const fd = new FormData();
    fd.append('action', 'delete_announcement');
    fd.append('announcement_id', pendingDeleteId);

    fetch('actions/announcement_actions.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        deleteModal.hide();
        if (data.success) {
          const card = document.getElementById('ann-' + pendingDeleteId);
          if (card) {
            card.style.transition = 'opacity 0.3s';
            card.style.opacity = '0';
            setTimeout(() => card.remove(), 300);
          }
        } else {
          alert(data.message || 'Failed to delete.');
        }
        pendingDeleteId = null;
      })
      .catch(() => {
        deleteModal.hide();
        alert('Network error. Please try again.');
        pendingDeleteId = null;
      });
  });
});
</script>
</body>
</html>