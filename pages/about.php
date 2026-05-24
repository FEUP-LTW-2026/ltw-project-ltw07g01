<?php
declare(strict_types=1);

require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');
require_once(__DIR__ . '/../templates/about.tpl.php');

$session = new Session();
$db = getDatabaseConnection();

if ($session->isLoggedIn()) {
    drawDashHeader($session, $db, 'about', ['about']);
} else {
    drawHeader($session, ['about']);
}

drawAboutUs($session);
drawFooter();
