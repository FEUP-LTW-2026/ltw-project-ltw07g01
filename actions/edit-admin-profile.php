<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();
require_once('../database/connection.db.php');
require_once('../templates/common.tpl.php');
require_once(__DIR__ . '/../templates/edit-admin-profile.tpl.php');

if (!$session->isLoggedIn()) {
    header('Location: /actions/login.php');
    exit;
}

$db      = getDatabaseConnection();
$adminId = (int)$session->getId();

$s = $db->prepare('SELECT 1 FROM admins WHERE user_id = :id');
$s->execute([':id' => $adminId]);
if (!$s->fetch()) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error = '';

$stmt = $db->prepare('SELECT username, email, first_name, last_name, profile_photo, created_at FROM users WHERE id = ?');
$stmt->execute([$adminId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName       = trim($_POST['first_name']      ?? '');
    $lastName        = trim($_POST['last_name']       ?? '');
    $username        = trim($_POST['username']        ?? '');
    $currentPassword = $_POST['current_password']     ?? '';
    $newPassword     = $_POST['new_password']          ?? '';
    $confirmPassword = $_POST['confirm_password']      ?? '';

    if (!$firstName || !$lastName || !$username) {
        $error = 'Name and username are required.';
    } elseif (!preg_match('/^[\w.]{3,30}$/', $username)) {
        $error = 'Username must be 3–30 characters (letters, numbers, underscores, dots).';
    } else {
        $s = $db->prepare('SELECT 1 FROM users WHERE username = ? AND id != ?');
        $s->execute([$username, $adminId]);
        if ($s->fetch()) {
            $error = 'That username is already taken.';
        }
    }

    if (!$error && $newPassword !== '') {
        $row = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $row->execute([$adminId]);
        $hash = $row->fetchColumn();
        if (!password_verify($currentPassword, $hash)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        }
    }

    $newPhotoPath = null;
    if (!$error && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($_FILES['profile_photo']['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            $error = 'Photo must be JPEG, PNG, WebP or GIF.';
        } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
            $error = 'Photo must be under 5 MB.';
        } else {
            $ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
            $filename = 'user_' . $adminId . '_' . time() . '.' . $ext;
            $destDir  = __DIR__ . '/../images/profile_photos/';
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destDir . $filename)) {
                $newPhotoPath = '../images/profile_photos/' . $filename;
            } else {
                $error = 'Could not save the photo. Please try again.';
            }
        }
    }

    if (!$error) {
        $fields = 'first_name=?, last_name=?, username=?';
        $params = [$firstName, $lastName, $username];

        if ($newPhotoPath !== null) {
            $fields .= ', profile_photo=?';
            $params[] = $newPhotoPath;
        }
        if ($newPassword !== '') {
            $fields .= ', password_hash=?';
            $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $params[] = $adminId;
        $db->prepare("UPDATE users SET {$fields} WHERE id=?")->execute($params);

        header('Location: /actions/edit-admin-profile.php');
        exit;
    }

    // em erro mantem os valores submetidos
    $user['first_name'] = $firstName;
    $user['last_name']  = $lastName;
    $user['username']   = $username;
}

$profilePhoto = $user['profile_photo'] ?? '../images/profile_pic.webp';
$fullName     = $user['first_name'] . ' ' . $user['last_name'];

drawDashHeader($session, $db, 'edit-profile', [], 'profile-body admin-theme');
drawEditAdminProfilePage($session, $db, $user, $profilePhoto, $fullName, $error);
