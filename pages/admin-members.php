<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();
require_once('../database/connection.db.php');
require_once('../templates/layout/common.tpl.php');
require_once('../templates/pages/admin-members.tpl.php');

if (!$session->isLoggedIn()) {
    header('Location: /actions/login.php');
    exit;
}

$db = getDatabaseConnection();
$adminId = (int)$session->getId();

$s = $db->prepare('SELECT 1 FROM admins WHERE user_id = :id');
$s->execute([':id' => $adminId]);
if (!$s->fetch()) {
    header('Location: /pages/dashboard.php');
    exit;
}

$msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';

$members = $db->query(
    'SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.profile_photo, u.created_at,
            m.gym_plan, m.gym_start, m.gym_end, COALESCE(m.classes_remaining, 0) AS classes_remaining
     FROM users u
     JOIN clients c ON c.user_id = u.id
     LEFT JOIN memberships m ON m.client_id = u.id
     ORDER BY u.created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);

drawDashHeader($session, $db, 'admin-members', ['admin-members']);
drawAdminMembersPage($session, $db, $members, $msg, '');
