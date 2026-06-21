<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

function isAdmin(): bool
{
    return strtolower($_SESSION["role"] ?? "") === "admin";
}

function json_success(array $data = []): void
{
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}

function json_error(string $message): void
{
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)
        return 'just now';
    if ($diff < 3600)
        return floor($diff / 60) . 'm ago';
    if ($diff < 86400)
        return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)
        return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}

if ($action === 'create_announcement') {
    if (!isAdmin()) {
        json_error('Only admins can post announcements.');
    }

    $subject_id = (int) ($_POST['subject_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if (!$subject_id || empty($title)) {
        json_error('Subject and title are required.');
    }

    $stmt = $conn->prepare("INSERT INTO announcements (subject_id, title, body, created_by) VALUES (?,?,?,?)");
    $stmt->bind_param("issi", $subject_id, $title, $body, $user_id);
    if (!$stmt->execute()) {
        json_error('Failed to post announcement.');
    }
    $new_id = $conn->insert_id;
    $stmt->close();

    // Insert notif for all enrolled students except poster
    $notif = $conn->prepare("
    INSERT INTO notifications (user_id, type, reference_id, message) 
    SELECT user_id, 'announcement', ?, ? 
    FROM enrollments 
    WHERE subject_id = ? AND user_id != ?
");
    $notif_msg = "New announcement: " . $title;
    $notif->bind_param("isii", $new_id, $notif_msg, $subject_id, $user_id);
    $notif->execute();
    json_success(['announcement_id' => $new_id]);
}

if ($action === 'delete_announcement') {
    if (!isAdmin()) {
        json_error('Only admins can delete announcements.');
    }

    $ann_id = (int) ($_POST['announcement_id'] ?? 0);
    if (!$ann_id)
        json_error('Invalid announcement.');

    $del = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $del->bind_param("i", $ann_id);
    if (!$del->execute() || $del->affected_rows === 0) {
        json_error('Could not delete announcement.');
    }
    $del->close();

    json_success();
}

json_error('Unknown action.');