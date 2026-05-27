<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();
require_once('../database/connection.db.php');
require_once('../templates/layout/common.tpl.php');
require_once('../templates/pages/admin-profile.tpl.php');

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

$stmt = $db->prepare('SELECT username, email, first_name, last_name, profile_photo FROM users WHERE id = ?');
$stmt->execute([$adminId]);
$user = $stmt->fetch();

$profilePhoto = $user['profile_photo'] ?? '../images/profile_pic.webp';
$fullName     = $user['first_name'] . ' ' . $user['last_name'];

drawDashHeader($session, $db, 'profile', [], 'profile-body admin-theme');
drawAdminProfilePage($session, $db, $user, $profilePhoto, $fullName);
