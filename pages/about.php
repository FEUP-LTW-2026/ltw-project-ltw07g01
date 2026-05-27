<?php
declare(strict_types=1);

require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/layout/common.tpl.php');
require_once(__DIR__ . '/../templates/pages/about.tpl.php');

$session = new Session();
$db = getDatabaseConnection();

$session->isLoggedIn() ? drawDashHeader($session, $db, 'about', ['about']) : drawHeader($session, ['about']);
drawAboutUs($session);
drawFooter();
