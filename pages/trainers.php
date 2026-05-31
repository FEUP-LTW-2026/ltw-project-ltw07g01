<?php
declare(strict_types=1);
require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/layout/common.tpl.php');
require_once(__DIR__ . '/../templates/pages/trainers.tpl.php');

$session = new Session();
$db      = getDatabaseConnection();

$userId  = $session->isLoggedIn() ? (int)$session->getId() : 0;
$isAdmin = false;
if ($userId) {
    $s = $db->prepare('SELECT 1 FROM admins WHERE user_id = :id');
    $s->execute([':id' => $userId]);
    $isAdmin = (bool)$s->fetch();
}

$msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';

if ($session->isLoggedIn()) {
    drawDashHeader($session, $db, 'trainers', ['trainers']);
} else {
    drawHeader($session, ['trainers']);
}

drawTrainersPage($session, $isAdmin, $msg);
drawFooter();
