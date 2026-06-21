<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$user_id  = $_SESSION['user_id'];
$after_id = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;

function notifLink(string $type, int $ref_id): string {
    if ($type === 'announcement') return 'announcements.php';
    if ($type === 'event')        return 'calendar.php';
    if ($type === 'forum_reply')  return 'forum_post.php?id=' . $ref_id;
    return '#';
}

function notifIcon(string $type): string {
    if ($type === 'announcement') return 'bi-megaphone-fill text-success';
    if ($type === 'event')        return 'bi-calendar-event-fill text-primary';
    if ($type === 'forum_reply')  return 'bi-chat-fill text-warning';
    return 'bi-bell-fill';
}

function notifTimeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)   return 'just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return date('M j', strtotime($datetime));
}

// Get unread count (all unread, for badge)
$count_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$unread_count = (int)$count_stmt->get_result()->fetch_assoc()['cnt'];

// Get new notifications since after_id
$stmt = $conn->prepare("
    SELECT id, type, reference_id, message, is_read, created_at
    FROM notifications
    WHERE user_id = ? AND id > ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->bind_param("ii", $user_id, $after_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$notifs = [];
foreach ($rows as $r) {
    $notifs[] = [
        'id'           => (int)$r['id'],
        'type'         => $r['type'],
        'message'      => $r['message'],
        'is_read'      => (bool)$r['is_read'],
        'time_ago'     => notifTimeAgo($r['created_at']),
        'link'         => notifLink($r['type'], (int)$r['reference_id']),
        'icon'         => notifIcon($r['type']),
    ];
}

echo json_encode(['success' => true, 'notifications' => $notifs, 'unread_count' => $unread_count]);