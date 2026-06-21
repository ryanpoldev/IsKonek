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

if ($action === 'add_user') {
    $first_name = trim($_POST['first_name'] ?? '');
    $mid_name   = trim($_POST['mid_name']   ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $email      = trim($_POST['email']      ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $role       = trim($_POST['role']       ?? 'Student');
    $password   = $_POST['password']        ?? '';

    if (!$first_name || !$last_name || !$email || !$student_id || !$password) {
        json_error('All fields including password are required.');
    }

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) json_error('Email already exists.');
    $check->close();

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO users (first_name, mid_name, last_name, email, student_id, role, password) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssss", $first_name, $mid_name, $last_name, $email, $student_id, $role, $hashed);
    if (!$stmt->execute()) json_error('Failed to add user.');
    $stmt->close();

    json_success();
}

if ($action === 'edit_user') {
    $id         = (int)($_POST['user_id']    ?? 0);
    $first_name = trim($_POST['first_name']  ?? '');
    $mid_name   = trim($_POST['mid_name']    ?? '');
    $last_name  = trim($_POST['last_name']   ?? '');
    $email      = trim($_POST['email']       ?? '');
    $student_id = trim($_POST['student_id']  ?? '');
    $role       = trim($_POST['role']        ?? 'Student');
    $password   = $_POST['password']         ?? '';

    if (!$id || !$first_name || !$last_name || !$email || !$student_id) {
        json_error('Name, email, and student ID are required.');
    }

    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->bind_param("si", $email, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) json_error('Email already used by another user.');
    $check->close();

    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET first_name=?, mid_name=?, last_name=?, email=?, student_id=?, role=?, password=? WHERE id=?");
        $stmt->bind_param("sssssssi", $first_name, $mid_name, $last_name, $email, $student_id, $role, $hashed, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET first_name=?, mid_name=?, last_name=?, email=?, student_id=?, role=? WHERE id=?");
        $stmt->bind_param("ssssssi", $first_name, $mid_name, $last_name, $email, $student_id, $role, $id);
    }

    if (!$stmt->execute()) json_error('Failed to update user.');
    $stmt->close();

    json_success();
}

if ($action === 'delete_user') {
    $id = (int)($_POST['user_id'] ?? 0);
    if (!$id) json_error('Invalid user.');
    if ($id === (int)$_SESSION['user_id']) json_error('You cannot delete your own account.');

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    if (!$stmt->execute() || $stmt->affected_rows === 0) json_error('Failed to delete user.');
    $stmt->close();

    json_success();
}

json_error('Unknown action.');