<?php
declare(strict_types=1);

require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');
require_once(__DIR__ . '/../templates/trainers.tpl.php');

$session = new Session();
$db = getDatabaseConnection();

$stmt = $db->prepare('
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.username,
        u.profile_photo,
        t.bio,
        t.certifications,
        GROUP_CONCAT(DISTINCT gl.name) AS gyms,
        GROUP_CONCAT(DISTINCT ct.name) AS class_types
    FROM trainers t
    JOIN users u ON u.id = t.user_id
    LEFT JOIN trainer_locations tl ON tl.trainer_id = t.user_id
    LEFT JOIN gym_locations gl ON gl.id = tl.gym_id
    LEFT JOIN trainer_specializations ts ON ts.trainer_id = t.user_id
    LEFT JOIN class_types ct ON ct.id = ts.class_type_id
    GROUP BY u.id
    ORDER BY u.first_name, u.last_name
');

$stmt->execute();
$trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($session->isLoggedIn()) {
    drawDashHeader($session, $db, 'trainers', ['trainers']);
} else {
    drawHeader($session, ['trainers']);
}

drawTrainersPage($session, $trainers);
drawFooter();