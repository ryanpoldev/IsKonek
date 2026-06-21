<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get enrolled subjects for categories
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

// Active filter
$filter_subject_id = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;

// Get posts
if ($filter_subject_id > 0) {
    $posts_query = $conn->prepare("
        SELECT fp.id, fp.title, fp.views, fp.created_at,
               CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS full_name, u.role,
               s.code AS subject_code, s.color AS subject_color,
               COUNT(DISTINCT fr.id) AS reply_count
        FROM forum_posts fp
        JOIN users u ON u.id = fp.user_id
        JOIN subjects s ON s.id = fp.subject_id
        LEFT JOIN forum_replies fr ON fr.post_id = fp.id
        WHERE fp.subject_id = ?
        GROUP BY fp.id
        ORDER BY fp.created_at DESC
    ");
    $posts_query->bind_param("i", $filter_subject_id);
} else {
    // All posts from enrolled subjects only
    $posts_query = $conn->prepare("
        SELECT fp.id, fp.title, fp.views, fp.created_at,
               CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS full_name, u.role,
               s.code AS subject_code, s.color AS subject_color,
               COUNT(DISTINCT fr.id) AS reply_count
        FROM forum_posts fp
        JOIN users u ON u.id = fp.user_id
        JOIN subjects s ON s.id = fp.subject_id
        JOIN enrollments e ON e.subject_id = fp.subject_id AND e.user_id = ?
        LEFT JOIN forum_replies fr ON fr.post_id = fp.id
        GROUP BY fp.id
        ORDER BY fp.created_at DESC
    ");
    $posts_query->bind_param("i", $user_id);
}
$posts_query->execute();
$posts = $posts_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Total post count for badge
$total_posts = count($posts);

// Avatar color palette
function avatarColor(string $name): string {
    $colors = ['#33b77a','#4a90d9','#d23c3c','#f5a623','#9b59b6','#1abc9c','#e67e22','#2980b9'];
    return $colors[abs(crc32($name)) % count($colors)];
}

function initials(string $name): string {
    $parts = explode(' ', trim($name));
    if (empty($parts) || $parts[0] === '') return '?';
    $i = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1 && !empty(end($parts))) $i .= strtoupper(substr(end($parts), 0, 1));
    return $i;
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M j', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forums – Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/forums.css" rel="stylesheet">
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
        <h2 class="hero-greeting mb-1">Threaded Forums 💬</h2>
        <p class="hero-sub text-muted">Organized discussions for every subject and topic.</p>
      </div>

      <div class="forum-layout">

        <aside class="forum-categories">
          <p class="categories-label">CATEGORIES</p>

          <a href="forums.php" class="category-item <?= $filter_subject_id === 0 ? 'active' : '' ?>">
            <span class="d-flex align-items-center">
              <span class="category-dot" style="background:#33b77a;"></span> All Topics
            </span>
            <?php if ($filter_subject_id === 0): ?>
              <span class="badge rounded-pill text-white" style="background:#d23c3c;font-size:10px;"><?= $total_posts ?></span>
            <?php endif; ?>
          </a>

          <?php foreach ($enrolled_subjects as $subj): ?>
          <a href="forums.php?subject=<?= $subj['id'] ?>" class="category-item <?= $filter_subject_id === (int)$subj['id'] ? 'active' : '' ?>">
            <span class="d-flex align-items-center gap-2">
              <span class="category-dot" style="background:<?= htmlspecialchars($subj['color']) ?>;"></span>
              <?= htmlspecialchars($subj['code']) ?>
            </span>
          </a>
          <?php endforeach; ?>
        </aside>

        <div class="forum-panel">
          <div class="forum-panel-header">
            <p class="forum-panel-title">
              <?php if ($filter_subject_id > 0):
                $active_subj = array_filter($enrolled_subjects, fn($s) => (int)$s['id'] === $filter_subject_id);
                $active_subj = array_values($active_subj);
                echo htmlspecialchars($active_subj[0]['code'] ?? 'Subject') . ' — Discussions';
              else: ?>
                All Discussion
              <?php endif; ?>
            </p>
            <button class="btn-new-topic" data-bs-toggle="modal" data-bs-target="#newTopicModal">
              <i class="bi bi-plus-lg"></i> New Topic
            </button>
          </div>

          <?php if (empty($posts)): ?>
            <div class="forum-empty">
              <i class="bi bi-chat-square-dots"></i>
              <p class="mb-1 fw-medium" style="font-size:15px;">No discussions yet</p>
              <p class="text-muted" style="font-size:13px;">Be the first to start a conversation.</p>
            </div>
          <?php else: ?>
            <div class="thread-table-header">
              <span>Topics</span>
              <span class="text-center">Replies</span>
              <span class="text-center">Views</span>
            </div>

            <?php foreach ($posts as $post): ?>
            <a href="forum_post.php?id=<?= $post['id'] ?>" class="thread-row">
              <div class="d-flex align-items-center">
                <div class="thread-avatar" style="background:<?= avatarColor($post['full_name']) ?>;">
                  <?= initials($post['full_name']) ?>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                  <p class="thread-title text-truncate mb-1"><?= htmlspecialchars($post['title']) ?></p>
                  <div class="thread-meta">
                    <?php 
                      $n_parts = explode(' ', $post['full_name']);
                      $short_name = $n_parts[0] . (isset($n_parts[1]) ? ' ' . $n_parts[1][0] . '.' : '');
                    ?>
                    <span><?= htmlspecialchars($short_name) ?> &middot; <?= htmlspecialchars($post['role']) ?></span>
                    <?php if ($filter_subject_id === 0): ?>
                      <span class="thread-subject-badge" style="background:<?= htmlspecialchars($post['subject_color']) ?>;"><?= htmlspecialchars($post['subject_code']) ?></span>
                    <?php endif; ?>
                    <span><?= timeAgo($post['created_at']) ?></span>
                  </div>
                </div>
              </div>

              <div class="thread-stat">
                <div class="thread-stat-value"><?= (int)$post['reply_count'] ?></div>
                <div class="thread-stat-label">Replies</div>
              </div>

              <div class="thread-stat">
                <div class="thread-stat-value" style="color:var(--text-muted);font-size:16px;"><?= (int)$post['views'] ?></div>
                <div class="thread-stat-label">Views</div>
              </div>
            </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="newTopicModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-medium" style="font-family:var(--font-heading);">New Topic</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-3">
        <div id="topicError" class="alert-error mb-3" style="display:none;"></div>
        <form id="newTopicForm">
          <div class="mb-3">
            <label class="form-label small fw-medium">Subject</label>
            <select name="subject_id" class="form-select" required style="border-radius:10px;border-color:var(--border-color);">
              <option value="">— Select subject —</option>
              <?php foreach ($enrolled_subjects as $subj): ?>
                <option value="<?= $subj['id'] ?>" <?= $filter_subject_id === (int)$subj['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($subj['code'] . ' – ' . $subj['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium">Topic Title</label>
            <input type="text" name="title" class="form-control" required maxlength="200" placeholder="e.g. What is the difference between TCP and UDP?" style="border-radius:10px;border-color:var(--border-color);">
          </div>
          <div class="mb-4">
            <label class="form-label small fw-medium">Body <span class="text-muted fw-normal">(optional)</span></label>
            <textarea name="body" class="form-control" rows="4" placeholder="Add more context, questions, or details..." style="border-radius:10px;border-color:var(--border-color);resize:vertical;"></textarea>
          </div>
          <button type="submit" class="btn-login w-100">Post Topic</button>
        </form>
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

  document.getElementById('newTopicForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'create_post');

    fetch('actions/forum_actions.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          window.location.href = 'forum_post.php?id=' + data.post_id;
        } else {
          const err = document.getElementById('topicError');
          err.textContent = data.message || 'Failed to create topic.';
          err.style.display = 'block';
        }
      })
      .catch(() => {
        const err = document.getElementById('topicError');
        err.textContent = 'Network error. Please try again.';
        err.style.display = 'block';
      });
  });
});
</script>
</body>
</html>