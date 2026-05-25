<?php
declare(strict_types=1);

require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');
require_once(__DIR__ . '/../templates/locations.tpl.php');

$session = new Session();
$db = getDatabaseConnection();

$stmt = $db->prepare('SELECT id, name, city, address FROM gym_locations ORDER BY city, name');
$stmt->execute();
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);


$session->isLoggedIn()
    ? drawDashHeader($session, $db, 'locations', ['locations'])
    : drawHeader($session, ['locations']);

drawLocations($session, $locations);
drawFooter();
