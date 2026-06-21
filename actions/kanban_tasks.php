<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit();
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user session.']);
    exit();
}

ensureKanbanTable($conn);

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    echo json_encode(['success' => true, 'tasks' => fetchTasks($conn, $userId)]);
    exit();
}

if ($method !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$input = parseInput();
$action = trim((string) ($input['action'] ?? ''));

switch ($action) {
    case 'create':
        createTask($conn, $userId, $input);
        break;
    case 'update_status':
        updateTaskStatus($conn, $userId, $input);
        break;
    case 'delete':
        deleteTask($conn, $userId, $input);
        break;
    case 'reset':
        resetTasks($conn, $userId);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}

function ensureKanbanTable(mysqli $conn): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS kanban_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(120) NOT NULL,
            subject VARCHAR(80) NOT NULL DEFAULT 'General',
            priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
            status ENUM('todo', 'progress', 'review', 'done') NOT NULL DEFAULT 'todo',
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_kanban_user_status (user_id, status),
            CONSTRAINT fk_kanban_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $conn->query($sql);

    ensureColumnExists($conn, 'kanban_tasks', 'due_date', 'DATE DEFAULT NULL AFTER priority');
}

function parseInput(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

function fetchTasks(mysqli $conn, int $userId): array
{
    $stmt = $conn->prepare(
        "SELECT id, title, subject, priority, status, due_date
         FROM kanban_tasks
         WHERE user_id = ?
         ORDER BY FIELD(status, 'todo', 'progress', 'review', 'done'), sort_order ASC, id DESC"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tasks = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $tasks;
}

function createTask(mysqli $conn, int $userId, array $input): void
{
    $title = trim((string) ($input['title'] ?? ''));
    $subject = trim((string) ($input['subject'] ?? ''));
    $priority = trim((string) ($input['priority'] ?? 'medium'));
    $dueDate = trim((string) ($input['due_date'] ?? ''));

    if ($title === '') {
        echo json_encode(['success' => false, 'message' => 'Task title is required.']);
        return;
    }

    if (!in_array($priority, ['low', 'medium', 'high'], true)) {
        $priority = 'medium';
    }

    if ($subject === '') {
        $subject = 'General';
    }

    $status = 'todo';
    $sortOrder = nextSortOrder($conn, $userId, $status);

    if ($dueDate === '') {
        $stmt = $conn->prepare(
            'INSERT INTO kanban_tasks (user_id, title, subject, priority, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issssi', $userId, $title, $subject, $priority, $status, $sortOrder);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO kanban_tasks (user_id, title, subject, priority, status, due_date, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssssi', $userId, $title, $subject, $priority, $status, $dueDate, $sortOrder);
    }
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'Failed to create task.']);
        return;
    }

    echo json_encode(['success' => true, 'tasks' => fetchTasks($conn, $userId)]);
}

function updateTaskStatus(mysqli $conn, int $userId, array $input): void
{
    $taskId = (int) ($input['id'] ?? 0);
    $status = trim((string) ($input['status'] ?? ''));

    if ($taskId <= 0 || !in_array($status, ['todo', 'progress', 'review', 'done'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid task update payload.']);
        return;
    }

    $sortOrder = nextSortOrder($conn, $userId, $status);

    $stmt = $conn->prepare('UPDATE kanban_tasks SET status = ?, sort_order = ? WHERE id = ? AND user_id = ?');
    $stmt->bind_param('siii', $status, $sortOrder, $taskId, $userId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'Failed to update task status.']);
        return;
    }

    echo json_encode(['success' => true, 'tasks' => fetchTasks($conn, $userId)]);
}

function deleteTask(mysqli $conn, int $userId, array $input): void
{
    $taskId = (int) ($input['id'] ?? 0);
    if ($taskId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid task id.']);
        return;
    }

    $stmt = $conn->prepare('DELETE FROM kanban_tasks WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $taskId, $userId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete task.']);
        return;
    }

    echo json_encode(['success' => true, 'tasks' => fetchTasks($conn, $userId)]);
}

function resetTasks(mysqli $conn, int $userId): void
{
    $deleteStmt = $conn->prepare('DELETE FROM kanban_tasks WHERE user_id = ?');
    $deleteStmt->bind_param('i', $userId);
    $deleteStmt->execute();
    $deleteStmt->close();

    echo json_encode(['success' => true, 'tasks' => fetchTasks($conn, $userId)]);
}

function ensureColumnExists(mysqli $conn, string $table, string $column, string $definition): void
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS column_count
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?'
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $exists = ((int) ($row['column_count'] ?? 0)) > 0;
    $stmt->close();

    if (!$exists) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function nextSortOrder(mysqli $conn, int $userId, string $status): int
{
    $stmt = $conn->prepare('SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM kanban_tasks WHERE user_id = ? AND status = ?');
    $stmt->bind_param('is', $userId, $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return ((int) ($row['max_sort'] ?? 0)) + 1;
}
