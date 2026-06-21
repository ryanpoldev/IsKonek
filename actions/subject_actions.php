<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$is_admin = strtolower($_SESSION['role'] ?? '') === 'admin';
if (!$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$action = $_POST['action'] ?? '';

function json_success(): void {
    echo json_encode(['success' => true]);
    exit();
}

function json_error(string $msg): void {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

if ($action === 'add_subject') {
    $code       = trim($_POST['code']       ?? '');
    $name       = trim($_POST['name']       ?? '');
    $instructor = trim($_POST['instructor'] ?? '');
    $units      = (int)($_POST['units']     ?? 3);
    $color      = trim($_POST['color']      ?? '#33b77a');
    $semester   = trim($_POST['semester']   ?? '');

    if (!$code || !$name) json_error('Code and name are required.');

    $check = $conn->prepare("SELECT id FROM subjects WHERE code = ?");
    $check->bind_param("s", $code);
    $check->execute();
    if ($check->get_result()->num_rows > 0) json_error('Subject code already exists.');
    $check->close();

    $enroll_key = 'KEY-' . strtoupper(bin2hex(random_bytes(4)));

    $stmt = $conn->prepare("INSERT INTO subjects (code, name, instructor, units, enroll_key, color, semester) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("sssisss", $code, $name, $instructor, $units, $enroll_key, $color, $semester);
    if (!$stmt->execute()) json_error('Failed to add subject.');
    $stmt->close();

    json_success();
}

if ($action === 'edit_subject') {
    $id         = (int)($_POST['subject_id'] ?? 0);
    $code       = trim($_POST['code']        ?? '');
    $name       = trim($_POST['name']        ?? '');
    $instructor = trim($_POST['instructor']  ?? '');
    $units      = (int)($_POST['units']      ?? 3);
    $color      = trim($_POST['color']       ?? '#33b77a');
    $semester   = trim($_POST['semester']    ?? '');

    if (!$id || !$code || !$name) json_error('Code and name are required.');

    $check = $conn->prepare("SELECT id FROM subjects WHERE code = ? AND id != ?");
    $check->bind_param("si", $code, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) json_error('Subject code already used by another subject.');
    $check->close();

    $stmt = $conn->prepare("UPDATE subjects SET code=?, name=?, instructor=?, units=?, color=?, semester=? WHERE id=?");
    $stmt->bind_param("ssisssi", $code, $name, $instructor, $units, $color, $semester, $id);
    if (!$stmt->execute()) json_error('Failed to update subject.');
    $stmt->close();

    json_success();
}

if ($action === 'delete_subject') {
    $id = (int)($_POST['subject_id'] ?? 0);
    if (!$id) json_error('Invalid subject.');

    $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
    $stmt->bind_param("i", $id);
    if (!$stmt->execute() || $stmt->affected_rows === 0) json_error('Failed to delete subject.');
    $stmt->close();

    json_success();
}

json_error('Unknown action.');