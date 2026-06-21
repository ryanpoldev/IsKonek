<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$post_id   = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
$after_id  = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;

if (!$post_id) {
    echo json_encode(['success' => false]);
    exit();
}

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

$stmt = $conn->prepare("
    SELECT fr.id, fr.body, fr.created_at,
           u.id AS user_id,
           CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS full_name,
           u.role
    FROM forum_replies fr
    JOIN users u ON u.id = fr.user_id
    WHERE fr.post_id = ? AND fr.id > ?
    ORDER BY fr.created_at ASC
");
$stmt->bind_param("ii", $post_id, $after_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$current_user_id = $_SESSION['user_id'];
$replies = [];
foreach ($rows as $r) {
    $replies[] = [
        'id'           => (int)$r['id'],
        'body'         => $r['body'],
        'full_name'    => $r['full_name'],
        'role'         => $r['role'],
        'avatar_color' => avatarColor($r['full_name']),
        'initials'     => initials($r['full_name']),
        'time_ago'     => timeAgo($r['created_at']),
        'is_mine'      => (int)$r['user_id'] === $current_user_id,
        'user_id'      => (int)$r['user_id'],
    ];
}

echo json_encode(['success' => true, 'replies' => $replies]);
