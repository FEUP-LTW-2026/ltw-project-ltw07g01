<?php
declare(strict_types=1);

require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');
require_once(__DIR__ . '/../templates/equipment.tpl.php');

$session = new Session();

if (!$session->isLoggedIn()) {
    header('Location: /actions/login.php');
    exit();
}

$db = getDatabaseConnection();

$stmt = $db->prepare('
    SELECT equipment.name, equipment.body_part, equipment.is_available, gym_locations.name AS gym_name
    FROM equipment
    JOIN gym_locations ON equipment.gym_id = gym_locations.id
    ORDER BY gym_locations.name, equipment.body_part, equipment.name
');
$stmt->execute();
$equipment = $stmt->fetchAll();

drawDashHeader($session, $db, 'equipment', ['equipment']);
drawEquipment($equipment);
drawFooter();
