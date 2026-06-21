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

function syncSubjects(mysqli $conn, int $section_id, array $subject_ids): void {
    $del = $conn->prepare("DELETE FROM section_subjects WHERE section_id = ?");
    $del->bind_param("i", $section_id);
    $del->execute();
    $del->close();

    if (!empty($subject_ids)) {
        $ins = $conn->prepare("INSERT IGNORE INTO section_subjects (section_id, subject_id) VALUES (?,?)");
        foreach ($subject_ids as $sid) {
            $sid = (int)$sid;
            $ins->bind_param("ii", $section_id, $sid);
            $ins->execute();
        }
        $ins->close();
    }
}

// Auto-enroll all users in this section into the section's subjects
function autoEnrollSectionUsers(mysqli $conn, int $section_id): void {
    $subj_q = $conn->prepare("SELECT subject_id FROM section_subjects WHERE section_id = ?");
    $subj_q->bind_param("i", $section_id);
    $subj_q->execute();
    $res = $subj_q->get_result();
    $subject_ids = [];
    while ($row = $res->fetch_assoc()) {
        $subject_ids[] = (int)$row['subject_id'];
    }
    $subj_q->close();

    if (empty($subject_ids)) return;

    $sec_q = $conn->prepare("SELECT name FROM sections WHERE id = ?");
    $sec_q->bind_param("i", $section_id);
    $sec_q->execute();
    $sec_row = $sec_q->get_result()->fetch_assoc();
    $sec_q->close();
    if (!$sec_row) return;
    $section_name = $sec_row['name'];

    $users_q = $conn->prepare("SELECT id FROM users WHERE section = ?");
    $users_q->bind_param("s", $section_name);
    $users_q->execute();
    $users_res = $users_q->get_result();
    $user_ids = [];
    while ($row = $users_res->fetch_assoc()) {
        $user_ids[] = (int)$row['id'];
    }
    $users_q->close();

    if (empty($user_ids)) return;

    $ins = $conn->prepare("INSERT IGNORE INTO enrollments (user_id, subject_id) VALUES (?,?)");
    foreach ($user_ids as $uid) {
        foreach ($subject_ids as $sid) {
            $ins->bind_param("ii", $uid, $sid);
            $ins->execute();
        }
    }
    $ins->close();
}

if ($action === 'add_section') {
    $name       = trim($_POST['name']        ?? '');
    $course     = trim($_POST['course']      ?? '');
    $manager_id = (int)($_POST['manager_id'] ?? 0);
    $subjects   = $_POST['subjects']         ?? [];

    if (!$name || !$course) json_error('Section name and course are required.');

    $check = $conn->prepare("SELECT id FROM sections WHERE name = ?");
    $check->bind_param("s", $name);
    $check->execute();
    if ($check->get_result()->num_rows > 0) json_error('Section already exists.');
    $check->close();

    if ($manager_id > 0) {
        $stmt = $conn->prepare("INSERT INTO sections (name, course, manager_id) VALUES (?,?,?)");
        $stmt->bind_param("ssi", $name, $course, $manager_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO sections (name, course) VALUES (?,?)");
        $stmt->bind_param("ss", $name, $course);
    }
    if (!$stmt->execute()) json_error('Failed to add section.');
    $section_id = $conn->insert_id;
    $stmt->close();

    syncSubjects($conn, $section_id, $subjects);
    autoEnrollSectionUsers($conn, $section_id);
    json_success();
}

if ($action === 'edit_section') {
    $id         = (int)($_POST['section_id']  ?? 0);
    $name       = trim($_POST['name']         ?? '');
    $course     = trim($_POST['course']       ?? '');
    $manager_id = (int)($_POST['manager_id']  ?? 0);
    $subjects   = $_POST['subjects']          ?? [];

    if (!$id || !$name || !$course) json_error('Section name and course are required.');

    $check = $conn->prepare("SELECT id FROM sections WHERE name = ? AND id != ?");
    $check->bind_param("si", $name, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) json_error('Section name already used.');
    $check->close();

    if ($manager_id > 0) {
        $stmt = $conn->prepare("UPDATE sections SET name=?, course=?, manager_id=? WHERE id=?");
        $stmt->bind_param("ssii", $name, $course, $manager_id, $id);
    } else {
        $stmt = $conn->prepare("UPDATE sections SET name=?, course=?, manager_id=NULL WHERE id=?");
        $stmt->bind_param("ssi", $name, $course, $id);
    }
    if (!$stmt->execute()) json_error('Failed to update section.');
    $stmt->close();

    syncSubjects($conn, $id, $subjects);
    autoEnrollSectionUsers($conn, $id);
    json_success();
}

if ($action === 'delete_section') {
    $id = (int)($_POST['section_id'] ?? 0);
    if (!$id) json_error('Invalid section.');

    $stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
    $stmt->bind_param("i", $id);
    if (!$stmt->execute() || $stmt->affected_rows === 0) json_error('Failed to delete section.');
    $stmt->close();

    json_success();
}

if ($action === 'add_member') {
    $section_id = (int)($_POST['section_id'] ?? 0);
    $user_id    = (int)($_POST['user_id']    ?? 0);
    if (!$section_id || !$user_id) json_error('Invalid section or user.');

    $sec_q = $conn->prepare("SELECT name FROM sections WHERE id = ?");
    $sec_q->bind_param("i", $section_id);
    $sec_q->execute();
    $sec_row = $sec_q->get_result()->fetch_assoc();
    $sec_q->close();
    if (!$sec_row) json_error('Section not found.');

    $upd = $conn->prepare("UPDATE users SET section=? WHERE id=?");
    $upd->bind_param("si", $sec_row['name'], $user_id);
    if (!$upd->execute()) json_error('Failed to add member.');
    $upd->close();

    autoEnrollSectionUsers($conn, $section_id);
    json_success();
}

json_error('Unknown action.');