<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? '';

if ($action === 'mark_all_read') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit();
}

if ($action === 'mark_read') {
    $notif_id = (int)($_POST['notif_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['success' => false]);
