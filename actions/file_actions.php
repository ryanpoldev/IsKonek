<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

$upload_dir = __DIR__ . '/../uploads/repo/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

function json_success(array $data = []): void {
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}
function json_error(string $msg): void {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

// ── Upload File ──────────────────────────────────────────────
if ($action === 'upload') {
    global $conn;
    $subject_id = (int)($_POST['subject_id'] ?? 0);

    if (empty($_FILES['file'])) json_error('No file received.');

    $file        = $_FILES['file'];
    $original    = basename($file['name']);
    $size        = (int)$file['size'];
    $mime        = $file['type'];
    $max_bytes   = 10 * 1024 * 1024; // 10MB

    if ($size > $max_bytes) json_error('File exceeds 10MB limit.');

    $allowed_exts = ['pdf','docx','doc','pptx','ppt','xlsx','xls','zip','png','jpg','jpeg','gif','txt','mp4','mp3'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts)) json_error('File type not allowed.');

    $stored_name = uniqid('repo_', true) . '.' . $ext;
    $dest        = $upload_dir . $stored_name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) json_error('Failed to save file.');

    // Get uploader full name
    $uq = $conn->prepare("SELECT CONCAT_WS(' ', first_name, mid_name, last_name) AS full_name FROM users WHERE id = ?");
    $uq->bind_param("i", $user_id);
    $uq->execute();
    $urow = $uq->get_result()->fetch_assoc();
    $full_name = $urow['full_name'] ?? 'Unknown';

    $stmt = $conn->prepare("INSERT INTO files (subject_id, user_id, filename, original_name, file_size, file_type) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("iissis", $subject_id, $user_id, $stored_name, $original, $size, $mime);
    if (!$stmt->execute()) {
        unlink($dest);
        json_error('Database error.');
    }

    $file_id = $conn->insert_id;
    json_success([
        'file' => [
            'id'            => $file_id,
            'original_name' => $original,
            'file_size'     => $size,
            'uploaded_by'   => $full_name,
            'uploaded_at'   => date('Y-m-d H:i:s'),
            'download_url'  => 'actions/file_actions.php?action=download&id=' . $file_id,
        ]
    ]);
}

// ── Get Files ────────────────────────────────────────────────
if ($action === 'get_files') {
    global $conn;
    $subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

    if ($subject_id > 0) {
        $stmt = $conn->prepare("
            SELECT f.id, f.original_name, f.file_size, f.file_type, f.uploaded_at,
                   CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS uploaded_by,
                   f.user_id
            FROM files f
            JOIN users u ON u.id = f.user_id
            WHERE f.subject_id = ?
            ORDER BY f.uploaded_at DESC
        ");
        $stmt->bind_param("i", $subject_id);
    } else {
        // All files from enrolled subjects
        $stmt = $conn->prepare("
            SELECT f.id, f.original_name, f.file_size, f.file_type, f.uploaded_at,
                   CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS uploaded_by,
                   f.user_id, s.code AS subject_code, s.color AS subject_color
            FROM files f
            JOIN users u ON u.id = f.user_id
            JOIN subjects s ON s.id = f.subject_id
            JOIN enrollments e ON e.subject_id = f.subject_id AND e.user_id = ?
            ORDER BY f.uploaded_at DESC
        ");
        $stmt->bind_param("i", $user_id);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $files = [];
    foreach ($rows as $r) {
        $files[] = [
            'id'            => (int)$r['id'],
            'original_name' => $r['original_name'],
            'file_size'     => (int)$r['file_size'],
            'file_type'     => $r['file_type'],
            'uploaded_by'   => $r['uploaded_by'],
            'uploaded_at'   => $r['uploaded_at'],
            'subject_code'  => $r['subject_code'] ?? null,
            'subject_color' => $r['subject_color'] ?? null,
            'is_mine'       => (int)$r['user_id'] === $user_id,
            'download_url'  => 'actions/file_actions.php?action=download&id=' . $r['id'],
        ];
    }
    json_success(['files' => $files]);
}

// ── Download File ────────────────────────────────────────────
if ($action === 'download') {
    global $conn;
    $file_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$file_id) { http_response_code(400); echo 'Invalid file.'; exit(); }

    // Verify enrollment access
    $stmt = $conn->prepare("
        SELECT f.filename, f.original_name, f.file_type
        FROM files f
        JOIN enrollments e ON e.subject_id = f.subject_id AND e.user_id = ?
        WHERE f.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $user_id, $file_id);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();

    if (!$file) { http_response_code(403); echo 'Access denied or file not found.'; exit(); }

    $path = $upload_dir . $file['filename'];
    if (!file_exists($path)) { http_response_code(404); echo 'File not found on disk.'; exit(); }

    header('Content-Type: ' . ($file['file_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . addslashes($file['original_name']) . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-cache');
    readfile($path);
    exit();
}

// ── Delete File ──────────────────────────────────────────────
if ($action === 'delete') {
    global $conn;
    $file_id = (int)($_POST['file_id'] ?? 0);
    if (!$file_id) json_error('Invalid file.');

    $stmt = $conn->prepare("SELECT filename FROM files WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $file_id, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) json_error('File not found or not yours.');

    $path = $upload_dir . $row['filename'];
    if (file_exists($path)) unlink($path);

    $del = $conn->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
    $del->bind_param("ii", $file_id, $user_id);
    $del->execute();
    json_success();
}

json_error('Unknown action.');
