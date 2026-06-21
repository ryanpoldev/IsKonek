<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? '';

if ($action === 'update_profile') {
    $section_name = trim($_POST['section'] ?? '');
    $avatar_data  = $_POST['avatar_data']  ?? '';

    // 1. Look up the section_id from the sections table
    $section_id = null;
    if (!empty($section_name)) {
        $sec_stmt = $conn->prepare("SELECT id FROM sections WHERE name = ? LIMIT 1");
        $sec_stmt->bind_param("s", $section_name);
        $sec_stmt->execute();
        $sec_res = $sec_stmt->get_result()->fetch_assoc();
        if ($sec_res) {
            $section_id = $sec_res['id'];
        }
        $sec_stmt->close();
    }

    // 2. Handle avatar upload
    $avatar_path = null;
    if (!empty($avatar_data) && strpos($avatar_data, 'data:image') === 0) {
        $upload_dir = '../assets/avatars/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $filename = 'avatar_' . $user_id . '_' . time() . '.jpg';
        $data = preg_replace('/^data:image\/\w+;base64,/', '', $avatar_data);
        file_put_contents($upload_dir . $filename, base64_decode($data));
        $avatar_path = 'assets/avatars/' . $filename;
    }

    // 3. Update the users table using section_id instead of section
    if ($avatar_path) {
        // "isi" = integer (section_id), string (avatar_path), integer (user_id)
        $stmt = $conn->prepare("UPDATE users SET section_id=?, avatar=? WHERE id=?");
        $stmt->bind_param("isi", $section_id, $avatar_path, $user_id);
    } else {
        // "ii" = integer (section_id), integer (user_id)
        $stmt = $conn->prepare("UPDATE users SET section_id=? WHERE id=?");
        $stmt->bind_param("ii", $section_id, $user_id);
    }

    if ($stmt->execute()) {
        $_SESSION['section'] = $section_name; // Keep session updated for the UI
        if ($avatar_path) {
            $_SESSION['avatar'] = $avatar_path; // Refresh avatar in session so sidebar/navbar reflect it
        }
        header('Location: ../settings.php?success=profile&tab=profile');
    } else {
        header('Location: ../settings.php?error=profile&tab=profile');
    }
    $stmt->close();
    exit();
}

if ($action === 'change_password') {
    $current  = $_POST['current_password']  ?? '';
    $new_pass = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    if (strlen($new_pass) < 8 || $new_pass !== $confirm) {
        header('Location: ../settings.php?error=password&tab=password');
        exit();
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!password_verify($current, $row['password'])) {
        header('Location: ../settings.php?error=password&tab=password');
        exit();
    }

    $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
    $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $upd->bind_param("si", $hashed, $user_id);

    if ($upd->execute()) {
        header('Location: ../settings.php?success=password&tab=password');
    } else {
        header('Location: ../settings.php?error=password&tab=password');
    }
    $upd->close();
    exit();
}

header('Location: ../settings.php');
exit();