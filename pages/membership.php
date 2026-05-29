<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();
require_once('../database/connection.db.php');
require_once('../templates/layout/common.tpl.php');
require_once('../templates/pages/memberships.tpl.php');
$db = getDatabaseConnection();

$classesRemaining = null;
if ($session->isLoggedIn()) {
    $s = $db->prepare('SELECT
    gym_plan,
    gym_start,
    gym_end,
    classes_remaining
FROM memberships
WHERE client_id = :id');
    $s->execute(['id' => $session->getId()]);
    $mem = $s->fetch();
    if ($mem) {
        $classesRemaining = (int)$mem['classes_remaining'];
    }
    drawDashHeader($session, $db, 'membership', ['membership']);

} else {
    drawHeader($session, ['membership']);
}
drawMemberships($session, $classesRemaining, isset($_GET['subscribed']));
drawFooter();
