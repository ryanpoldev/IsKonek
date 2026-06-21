<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

$subject_code = trim($_POST['subject_code'] ?? '');
$enroll_key   = trim($_POST['enroll_key'] ?? '');
$user_id      = $_SESSION['user_id'];

if (empty($subject_code) || empty($enroll_key)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

/* Verify subject and enrollment key */
$stmt = $conn->prepare("SELECT id FROM subjects WHERE code = ? AND enroll_key = ?");
$stmt->bind_param("ss", $subject_code, $enroll_key);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid subject code or enrollment key.']);
    exit();
}

$subject = $result->fetch_assoc();
$subject_id = $subject['id'];
$stmt->close();

/* Check if already enrolled */
$stmt2 = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND subject_id = ?");
$stmt2->bind_param("ii", $user_id, $subject_id);
$stmt2->execute();
$existing = $stmt2->get_result();

if ($existing->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You are already enrolled in this subject.']);
    exit();
}
$stmt2->close();

/* Enroll */
$stmt3 = $conn->prepare("INSERT INTO enrollments (user_id, subject_id, enrolled_at) VALUES (?, ?, NOW())");
$stmt3->bind_param("ii", $user_id, $subject_id);

if ($stmt3->execute()) {
    echo json_encode(['success' => true, 'message' => 'Enrolled successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
$stmt3->close();
