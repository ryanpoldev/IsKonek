<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? '';

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

function json_success(array $data = []): void {
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}

function json_error(string $message): void {
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// ── Create Post ──────────────────────────────────────────────
if ($action === 'create_post') {
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $title      = trim($_POST['title'] ?? '');
    $body       = trim($_POST['body']  ?? '');

    if (!$subject_id || empty($title)) {
        json_error('Subject and title are required.');
    }

    // Verify enrollment
    $check = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND subject_id = ?");
    $check->bind_param("ii", $user_id, $subject_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        json_error('You are not enrolled in this subject.');
    }

    $stmt = $conn->prepare("INSERT INTO forum_posts (subject_id, user_id, title, body) VALUES (?,?,?,?)");
    $stmt->bind_param("iiss", $subject_id, $user_id, $title, $body);
    if (!$stmt->execute()) {
        json_error('Failed to create topic.');
    }

    json_success(['post_id' => $conn->insert_id]);
}

// ── Delete Post ──────────────────────────────────────────────
if ($action === 'delete_post') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    if (!$post_id) json_error('Invalid post.');

    $stmt = $conn->prepare("DELETE FROM forum_posts WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $post_id, $user_id);
    if (!$stmt->execute() || $stmt->affected_rows === 0) {
        json_error('Could not delete post.');
    }
    json_success();
}

// ── Create Reply ─────────────────────────────────────────────
if ($action === 'create_reply') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    $body    = trim($_POST['body'] ?? '');

    if (!$post_id || empty($body)) {
        json_error('Reply body is required.');
    }

    // Verify user is enrolled in the subject of this post
    $enroll = $conn->prepare("
        SELECT e.id FROM enrollments e
        JOIN forum_posts fp ON fp.subject_id = e.subject_id
        WHERE fp.id = ? AND e.user_id = ?
    ");
    $enroll->bind_param("ii", $post_id, $user_id);
    $enroll->execute();
    if ($enroll->get_result()->num_rows === 0) {
        json_error('Not authorized.');
    }

    $stmt = $conn->prepare("INSERT INTO forum_replies (post_id, user_id, body) VALUES (?,?,?)");
    $stmt->bind_param("iis", $post_id, $user_id, $body);
    if (!$stmt->execute()) {
        json_error('Failed to post reply.');
    }

    $reply_id  = $conn->insert_id;
    $full_name = $_SESSION['full_name'] ?? 'Student';
    $role      = $_SESSION['role']      ?? 'Student';

    // Get post title for notification message
    $post_info = $conn->prepare("SELECT title, user_id FROM forum_posts WHERE id = ?");
    $post_info->bind_param("i", $post_id);
    $post_info->execute();
    $post_row = $post_info->get_result()->fetch_assoc();

    if ($post_row) {
        $post_title = $post_row['title'];
        $post_author_id = (int)$post_row['user_id'];
        $short_title = mb_strlen($post_title) > 60 ? mb_substr($post_title, 0, 57) . '...' : $post_title;
        $notif_msg = htmlspecialchars($full_name) . ' replied to: "' . $short_title . '"';

        // Notify all followers (excluding the replier)
        $followers = $conn->prepare("SELECT user_id FROM forum_followers WHERE post_id = ? AND user_id != ?");
        $followers->bind_param("ii", $post_id, $user_id);
        $followers->execute();
        $follower_rows = $followers->get_result()->fetch_all(MYSQLI_ASSOC);

        // Also notify post author if not already a follower and not the replier
        $notified_users = array_column($follower_rows, 'user_id');
        if ($post_author_id !== $user_id && !in_array($post_author_id, $notified_users)) {
            $follower_rows[] = ['user_id' => $post_author_id];
        }

        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, message) VALUES (?, 'forum_reply', ?, ?)");
        foreach ($follower_rows as $f) {
            $fuid = (int)$f['user_id'];
            $notif_stmt->bind_param("iis", $fuid, $post_id, $notif_msg);
            $notif_stmt->execute();
        }
    }

    json_success([
        'reply' => [
            'id'           => $reply_id,
            'body'         => $body,
            'full_name'    => $full_name,
            'role'         => $role,
            'avatar_color' => avatarColor($full_name),
            'initials'     => initials($full_name),
        ]
    ]);
}

// ── Delete Reply ─────────────────────────────────────────────
if ($action === 'delete_reply') {
    $reply_id = (int)($_POST['reply_id'] ?? 0);
    if (!$reply_id) json_error('Invalid reply.');

    $stmt = $conn->prepare("DELETE FROM forum_replies WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reply_id, $user_id);
    if (!$stmt->execute() || $stmt->affected_rows === 0) {
        json_error('Could not delete reply.');
    }
    json_success();
}

// ── Follow / Unfollow Thread ─────────────────────────────────
if ($action === 'follow_thread') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    if (!$post_id) json_error('Invalid post.');

    // Check if already following
    $check = $conn->prepare("SELECT id FROM forum_followers WHERE user_id = ? AND post_id = ?");
    $check->bind_param("ii", $user_id, $post_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        // Unfollow
        $del = $conn->prepare("DELETE FROM forum_followers WHERE user_id = ? AND post_id = ?");
        $del->bind_param("ii", $user_id, $post_id);
        $del->execute();
        json_success(['following' => false]);
    } else {
        // Follow
        $ins = $conn->prepare("INSERT INTO forum_followers (user_id, post_id) VALUES (?,?)");
        $ins->bind_param("ii", $user_id, $post_id);
        $ins->execute();
        json_success(['following' => true]);
    }
}

// ── Check Follow Status ──────────────────────────────────────
if ($action === 'check_follow') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    if (!$post_id) json_error('Invalid post.');
    $check = $conn->prepare("SELECT id FROM forum_followers WHERE user_id = ? AND post_id = ?");
    $check->bind_param("ii", $user_id, $post_id);
    $check->execute();
    $following = $check->get_result()->num_rows > 0;
    json_success(['following' => $following]);
}

json_error('Unknown action.');