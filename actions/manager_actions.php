<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$role_lower = strtolower($_SESSION['role'] ?? '');
$is_admin_user = in_array($role_lower, ['admin', 'sup_admin'], true);
$current_user_id = (int)($_SESSION['user_id'] ?? 0);

$action = $_POST['action'] ?? '';

function json_success(array $extra = []): void {
    echo json_encode(array_merge(['success' => true], $extra));
    exit();
}

function json_error(string $msg): void {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

function findManagerUserId(mysqli $conn, int $section_id): ?int {
    // Prefer the managers table; fall back to sections.manager_id for legacy data.
    $stmt = $conn->prepare("SELECT user_id FROM managers WHERE section_id = ?");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return (int)$row['user_id'];

    $stmt = $conn->prepare("SELECT manager_id FROM sections WHERE id = ?");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && !empty($row['manager_id'])) return (int)$row['manager_id'];

    return null;
}

function assignManager(mysqli $conn, int $section_id, int $user_id): void {
    // Sync sections.manager_id
    $upd = $conn->prepare("UPDATE sections SET manager_id = ? WHERE id = ?");
    $upd->bind_param("ii", $user_id, $section_id);
    $upd->execute();
    $upd->close();

    // Sync managers table (one row per section)
    $exists = $conn->prepare("SELECT id FROM managers WHERE section_id = ?");
    $exists->bind_param("i", $section_id);
    $exists->execute();
    $found = $exists->get_result()->fetch_assoc();
    $exists->close();

    if ($found) {
        $upd2 = $conn->prepare("UPDATE managers SET user_id = ? WHERE section_id = ?");
        $upd2->bind_param("ii", $user_id, $section_id);
        $upd2->execute();
        $upd2->close();
    } else {
        $ins = $conn->prepare("INSERT INTO managers (section_id, user_id) VALUES (?, ?)");
        $ins->bind_param("ii", $section_id, $user_id);
        $ins->execute();
        $ins->close();
    }
}

function clearManager(mysqli $conn, int $section_id): void {
    $upd = $conn->prepare("UPDATE sections SET manager_id = NULL WHERE id = ?");
    $upd->bind_param("i", $section_id);
    $upd->execute();
    $upd->close();

    $del = $conn->prepare("DELETE FROM managers WHERE section_id = ?");
    $del->bind_param("i", $section_id);
    $del->execute();
    $del->close();
}

if ($action === 'assign_manager') {
    $section_id = (int)($_POST['section_id'] ?? 0);
    $user_id    = (int)($_POST['user_id']    ?? 0);

    if (!$section_id || !$user_id) json_error('Section and user are required.');

    $sec = $conn->prepare("SELECT id FROM sections WHERE id = ?");
    $sec->bind_param("i", $section_id);
    $sec->execute();
    if ($sec->get_result()->num_rows === 0) json_error('Section not found.');
    $sec->close();

    $usr = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $usr->bind_param("i", $user_id);
    $usr->execute();
    if ($usr->get_result()->num_rows === 0) json_error('User not found.');
    $usr->close();

    assignManager($conn, $section_id, $user_id);
    json_success(['message' => 'Manager assigned.']);
}

if ($action === 'change_manager') {
    $section_id  = (int)($_POST['section_id']  ?? 0);
    $new_user_id = (int)($_POST['user_id']     ?? 0);

    if (!$section_id || !$new_user_id) json_error('Section and user are required.');

    $sec = $conn->prepare("SELECT id FROM sections WHERE id = ?");
    $sec->bind_param("i", $section_id);
    $sec->execute();
    if ($sec->get_result()->num_rows === 0) json_error('Section not found.');
    $sec->close();

    $current = findManagerUserId($conn, $section_id);
    if ($current === null) json_error('No current manager to change. Use Assign instead.');
    if ($current === $new_user_id) json_error('This user is already the manager.');

    $usr = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $usr->bind_param("i", $new_user_id);
    $usr->execute();
    if ($usr->get_result()->num_rows === 0) json_error('User not found.');
    $usr->close();

    assignManager($conn, $section_id, $new_user_id);
    json_success(['message' => 'Manager changed.']);
}

if ($action === 'remove_manager') {
    $section_id = (int)($_POST['section_id'] ?? 0);
    if (!$section_id) json_error('Section is required.');

    $sec = $conn->prepare("SELECT id FROM sections WHERE id = ?");
    $sec->bind_param("i", $section_id);
    $sec->execute();
    if ($sec->get_result()->num_rows === 0) json_error('Section not found.');
    $sec->close();

    clearManager($conn, $section_id);
    json_success(['message' => 'Manager removed.']);
}

/**
 * Pass the current user's managership of their section to another member of the same section.
 * Allowed for: admins OR the current manager of the target section.
 */
if ($action === 'pass_managership') {
    $section_id = (int)($_POST['section_id'] ?? 0);
    $new_user_id = (int)($_POST['new_user_id'] ?? 0);

    if (!$section_id || !$new_user_id) json_error('Section and recipient are required.');

    $sec = $conn->prepare("SELECT id, name FROM sections WHERE id = ?");
    $sec->bind_param("i", $section_id);
    $sec->execute();
    $sec_row = $sec->get_result()->fetch_assoc();
    $sec->close();
    if (!$sec_row) json_error('Section not found.');

    $current = findManagerUserId($conn, $section_id);
    if ($current === null) {
        // No current manager — only admins may pass (which acts like an assign).
        if (!$is_admin_user) json_error('No manager to pass for this section.');
    } else {
        if (!$is_admin_user && $current !== $current_user_id) {
            json_error('Only the current manager or an admin can pass managership.');
        }
    }

    // Recipient must belong to this section.
    $chk = $conn->prepare("SELECT id FROM users WHERE id = ? AND section_id = ?");
    $chk->bind_param("ii", $new_user_id, $section_id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        json_error('Recipient must be a current member of this section.');
    }
    $chk->close();

    if ($current !== null && $current === $new_user_id) {
        json_error('That person is already the manager.');
    }

    assignManager($conn, $section_id, $new_user_id);
    json_success(['message' => 'Managership passed to the selected member.']);
}

json_error('Unknown action.');