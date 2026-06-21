<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$post_id) {
    header('Location: forums.php');
    exit();
}

// Increment view count
if (!isset($_SESSION['viewed_posts'])) {
    $_SESSION['viewed_posts'] = [];
}
if (!in_array($post_id, $_SESSION['viewed_posts'])) {
    $conn->query("UPDATE forum_posts SET views = views + 1 WHERE id = $post_id");
    $_SESSION['viewed_posts'][] = $post_id;
}
// Get post + author info
$post_query = $conn->prepare("
    SELECT fp.id, fp.title, fp.body, fp.views, fp.created_at,
           fp.user_id AS author_id,
           CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS full_name, u.role,
           s.id AS subject_id, s.code AS subject_code, s.name AS subject_name, s.color AS subject_color
    FROM forum_posts fp
    JOIN users u ON u.id = fp.user_id
    JOIN subjects s ON s.id = fp.subject_id
    WHERE fp.id = ?
");
$post_query->bind_param("i", $post_id);
$post_query->execute();
$post = $post_query->get_result()->fetch_assoc();

if (!$post) {
    header('Location: forums.php');
    exit();
}

// Check user is enrolled in the subject
$enroll_check = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND subject_id = ?");
$enroll_check->bind_param("ii", $user_id, $post['subject_id']);
$enroll_check->execute();
if ($enroll_check->get_result()->num_rows === 0) {
    header('Location: forums.php');
    exit();
}

// Get replies
$replies_query = $conn->prepare("
    SELECT fr.id, fr.body, fr.created_at,
           u.id AS user_id, CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS full_name, u.role
    FROM forum_replies fr
    JOIN users u ON u.id = fr.user_id
    WHERE fr.post_id = ?
    ORDER BY fr.created_at ASC
");
$replies_query->bind_param("i", $post_id);
$replies_query->execute();
$replies = $replies_query->get_result()->fetch_all(MYSQLI_ASSOC);

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
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($post['title']) ?> – Iskonek Forums</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/forum_post.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
  
</head>
<body>
<div class="app-wrapper">
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  <?php include 'includes/sidebar.php'; ?>

  <div class="main-content">
    <?php include 'includes/navbar.php'; ?>

    <div class="page-body">
      <div class="post-container mx-auto">

        <!-- Back link -->
        <a href="forums.php?subject=<?= $post['subject_id'] ?>" class="back-link">
          <i class="bi bi-arrow-left"></i> Back to Forums
        </a>

        <!-- Post card -->
        <div class="post-card">
          <span class="post-subject-badge" style="background:<?= htmlspecialchars($post['subject_color']) ?>;">
            <?= htmlspecialchars($post['subject_code']) ?>
          </span>

          <h1 class="post-title"><?= htmlspecialchars($post['title']) ?></h1>

          <div class="post-author-row">
            <div class="post-avatar" style="background:<?= avatarColor($post['full_name']) ?>;">
              <?= initials($post['full_name']) ?>
            </div>
            <div>
              <p class="mb-0 fw-medium" style="font-size:14px;"><?= htmlspecialchars($post['full_name']) ?></p>
              <p class="mb-0 text-muted" style="font-size:12px;"><?= htmlspecialchars($post['role']) ?> &middot; <?= timeAgo($post['created_at']) ?></p>
            </div>
            <?php if ((int)$post['author_id'] === $user_id): ?>
            <button class="ms-auto reply-delete" id="deletePostBtn" data-id="<?= $post_id ?>">
              <i class="bi bi-trash me-1"></i>Delete post
            </button>
            <?php endif; ?>
          </div>

          <?php if (!empty(trim($post['body'] ?? ''))): ?>
          <div class="post-body"><?= htmlspecialchars($post['body']) ?></div>
          <?php else: ?>
          <p class="text-muted fst-italic" style="font-size:14px;">No description provided.</p>
          <?php endif; ?>

          <div class="post-stats">
            <span><i class="bi bi-chat me-1"></i><span id="replyCount"><?= count($replies) ?></span> <?= count($replies) === 1 ? 'reply' : 'replies' ?></span>
            <span><i class="bi bi-eye me-1"></i><?= (int)$post['views'] ?> views</span>
            <button id="followBtn" class="ms-auto btn btn-sm" style="border-radius:10px;font-size:12px;font-weight:500;padding:5px 14px;border:1px solid var(--border-color);background:white;color:var(--text-secondary);">
              <i class="bi bi-bell me-1" id="followIcon"></i><span id="followLabel">Follow Thread</span>
            </button>
          </div>
        </div>

        <!-- Replies -->
        <?php if (!empty($replies)): ?>
        <p class="replies-header"><?= count($replies) ?> <?= count($replies) === 1 ? 'Reply' : 'Replies' ?></p>
        <div id="repliesContainer">
          <?php foreach ($replies as $reply): ?>
          <div class="reply-card" id="reply-<?= $reply['id'] ?>">
            <div class="d-flex align-items-center gap-2">
              <div class="reply-avatar" style="background:<?= avatarColor($reply['full_name']) ?>;">
                <?= initials($reply['full_name']) ?>
              </div>
              <div class="flex-grow-1">
                <p class="mb-0 fw-medium" style="font-size:13px;"><?= htmlspecialchars($reply['full_name']) ?></p>
                <p class="mb-0 text-muted" style="font-size:11px;"><?= htmlspecialchars($reply['role']) ?> &middot; <?= timeAgo($reply['created_at']) ?></p>
              </div>
              <?php if ((int)$reply['user_id'] === $user_id): ?>
              <button class="reply-delete delete-reply-btn" data-id="<?= $reply['id'] ?>">
                <i class="bi bi-trash"></i>
              </button>
              <?php endif; ?>
            </div>
            <div class="reply-body"><?= htmlspecialchars($reply['body']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div id="repliesContainer"></div>
        <?php endif; ?>

        <!-- Reply composer -->
        <div class="reply-composer">
          <p class="mb-2 fw-medium" style="font-size:13px;">Add a Reply</p>
          <div id="replyError" class="alert-error mb-2" style="display:none;"></div>
          <textarea class="form-control mb-2" id="replyBody" placeholder="Write your reply here..." rows="3"></textarea>
          <div class="d-flex justify-content-end">
            <button class="btn btn-sm" id="submitReply"
                    style="background:var(--accent-green);color:white;border-radius:10px;padding:7px 20px;font-size:13px;font-weight:500;">
              Post Reply
            </button>
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
  const overlay   = document.getElementById('sidebarOverlay');
  if (toggleBtn) toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); overlay?.classList.toggle('show'); });
  if (overlay)   overlay.addEventListener('click', () => { sidebar.classList.remove('show'); overlay.classList.remove('show'); });

  const POST_ID      = <?= $post_id ?>;
  const CURRENT_USER = <?= $user_id ?>;

  // ── Track last reply id for polling ─────────────────────────
  let lastReplyId = 0;
  document.querySelectorAll('.reply-card[id^="reply-"]').forEach(el => {
    const id = parseInt(el.id.replace('reply-', ''));
    if (id > lastReplyId) lastReplyId = id;
  });

  // ── Live polling (every 4 seconds) ──────────────────────────
  function pollReplies() {
    fetch(`actions/forum_poll.php?post_id=${POST_ID}&after_id=${lastReplyId}`)
      .then(r => r.json())
      .then(data => {
        if (data.success && data.replies.length > 0) {
          data.replies.forEach(reply => {
            if (!document.getElementById('reply-' + reply.id)) {
              appendReply(reply);
              if (reply.id > lastReplyId) lastReplyId = reply.id;
            }
          });
          updateReplyCount();
        }
      })
      .catch(() => {});
  }

  setInterval(pollReplies, 4000);

  // ── Reply count updater ──────────────────────────────────────
  function updateReplyCount() {
    const count = document.querySelectorAll('.reply-card').length;
    const span = document.getElementById('replyCount');
    if (span) span.textContent = count;
    const header = document.querySelector('.replies-header');
    if (header) {
      header.textContent = count + (count === 1 ? ' Reply' : ' Replies');
    }
  }

  // ── Submit reply ─────────────────────────────────────────────
  document.getElementById('submitReply').addEventListener('click', function () {
    const body = document.getElementById('replyBody').value.trim();
    if (!body) return;

    this.disabled = true;
    const fd = new FormData();
    fd.append('action', 'create_reply');
    fd.append('post_id', POST_ID);
    fd.append('body', body);

    fetch('actions/forum_actions.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          document.getElementById('replyBody').value = '';
          const reply = data.reply;
          reply.is_mine = true;
          reply.time_ago = 'just now';
          if (!document.getElementById('reply-' + reply.id)) {
            appendReply(reply);
            if (reply.id > lastReplyId) lastReplyId = reply.id;
          }
          updateReplyCount();
        } else {
          const err = document.getElementById('replyError');
          err.textContent = data.message || 'Failed to post reply.';
          err.style.display = 'block';
        }
      })
      .finally(() => { this.disabled = false; });
  });

  // ── Append reply to DOM ──────────────────────────────────────
  function appendReply(reply) {
    const container = document.getElementById('repliesContainer');
    const div = document.createElement('div');
    div.className = 'reply-card';
    div.id = 'reply-' + reply.id;
    div.innerHTML = `
      <div class="d-flex align-items-center gap-2">
        <div class="reply-avatar" style="background:${reply.avatar_color};">${escHtml(reply.initials)}</div>
        <div class="flex-grow-1">
          <p class="mb-0 fw-medium" style="font-size:13px;">${escHtml(reply.full_name)}</p>
          <p class="mb-0 text-muted" style="font-size:11px;">${escHtml(reply.role)} · ${escHtml(reply.time_ago)}</p>
        </div>
        ${reply.is_mine ? `<button class="reply-delete delete-reply-btn" data-id="${reply.id}"><i class="bi bi-trash"></i></button>` : ''}
      </div>
      <div class="reply-body">${escHtml(reply.body)}</div>`;
    container.appendChild(div);

    // Show replies header if first
    if (!document.querySelector('.replies-header')) {
      const h = document.createElement('p');
      h.className = 'replies-header';
      h.textContent = '1 Reply';
      container.before(h);
    }

    div.querySelector('.delete-reply-btn')?.addEventListener('click', deleteReplyHandler);
    div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  // ── Delete reply ─────────────────────────────────────────────
  function deleteReplyHandler() {
    const id = this.dataset.id;
    if (!confirm('Delete this reply?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_reply');
    fd.append('reply_id', id);
    fetch('actions/forum_actions.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          document.getElementById('reply-' + id)?.remove();
          updateReplyCount();
        }
      });
  }

  document.querySelectorAll('.delete-reply-btn').forEach(btn => {
    btn.addEventListener('click', deleteReplyHandler);
  });

  // ── Delete post ───────────────────────────────────────────────
  const deletePostBtn = document.getElementById('deletePostBtn');
  if (deletePostBtn) {
    deletePostBtn.addEventListener('click', function () {
      if (!confirm('Delete this topic? All replies will be removed.')) return;
      const fd = new FormData();
      fd.append('action', 'delete_post');
      fd.append('post_id', POST_ID);
      fetch('actions/forum_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if (data.success) window.location.href = 'forums.php'; });
    });
  }

  // ── Follow Thread ─────────────────────────────────────────────
  const followBtn   = document.getElementById('followBtn');
  const followIcon  = document.getElementById('followIcon');
  const followLabel = document.getElementById('followLabel');

  function setFollowUI(following) {
    if (following) {
      followBtn.style.background  = 'var(--accent-green)';
      followBtn.style.color       = 'white';
      followBtn.style.borderColor = 'var(--accent-green)';
      followIcon.className        = 'bi bi-bell-fill me-1';
      followLabel.textContent     = 'Following';
    } else {
      followBtn.style.background  = 'white';
      followBtn.style.color       = 'var(--text-secondary)';
      followBtn.style.borderColor = 'var(--border-color)';
      followIcon.className        = 'bi bi-bell me-1';
      followLabel.textContent     = 'Follow Thread';
    }
  }

  // Load initial follow state
  (function () {
    const fd = new FormData();
    fd.append('action', 'check_follow');
    fd.append('post_id', POST_ID);
    fetch('actions/forum_actions.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => { if (data.success) setFollowUI(data.following); });
  })();

  followBtn.addEventListener('click', function () {
    const fd = new FormData();
    fd.append('action', 'follow_thread');
    fd.append('post_id', POST_ID);
    fetch('actions/forum_actions.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => { if (data.success) setFollowUI(data.following); });
  });

  // ── Utility ───────────────────────────────────────────────────
  function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
});
</script>
</body>
</html>