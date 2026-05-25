<?php
declare(strict_types=1);

require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');

$session = new Session();

if (!$session->isLoggedIn()) {
    header('Location: /actions/login.php');
    exit();
}

$db = getDatabaseConnection();
$userId = (int)$session->getId();

$stmt = $db->prepare('SELECT 1 FROM clients WHERE user_id = ?');
$stmt->execute([$userId]);
if (!$stmt->fetch()) {
    header('Location: /pages/membership.php');
    exit();
}

$plan = $_GET['plan'] ?? '';

$gymPlans = [
    'gym-basic' => 'basic',
    'gym-pro'   => 'pro',
    'gym-ultra' => 'ultra',
];

$classPacks = [
    'pilates-1'  => 1,
    'pilates-5'  => 5,
    'pilates-10' => 10,
    'cycling-1'  => 1,
    'cycling-5'  => 5,
    'cycling-10' => 10,
];

if (isset($gymPlans[$plan])) {
    $gymPlan = $gymPlans[$plan];
    $gymStart = date('Y-m-d H:i:s');
    $gymEnd   = date('Y-m-d H:i:s', strtotime('+1 month'));

    $stmt = $db->prepare('SELECT client_id FROM memberships WHERE client_id = ?');
    $stmt->execute([$userId]);

    if ($stmt->fetch()) {
        $stmt = $db->prepare('
            UPDATE memberships
            SET gym_plan = ?, gym_start = ?, gym_end = ?
            WHERE client_id = ?
        ');
        $stmt->execute([$gymPlan, $gymStart, $gymEnd, $userId]);
    } else {
        $stmt = $db->prepare('
            INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining)
            VALUES (?, ?, ?, ?, 0)
        ');
        $stmt->execute([$userId, $gymPlan, $gymStart, $gymEnd]);
    }

} elseif (isset($classPacks[$plan])) {
    $qty = $classPacks[$plan];

    $stmt = $db->prepare('SELECT client_id FROM memberships WHERE client_id = ?');
    $stmt->execute([$userId]);

    if ($stmt->fetch()) {
        $stmt = $db->prepare('UPDATE memberships SET classes_remaining = classes_remaining + ? WHERE client_id = ?');
        $stmt->execute([$qty, $userId]);
    } else {
        $stmt = $db->prepare('
            INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining)
            VALUES (?, NULL, NULL, NULL, ?)
        ');
        $stmt->execute([$userId, $qty]);
    }
} else {
    header('Location: /pages/membership.php');
    exit();
}

header('Location: /pages/membership.php?subscribed=1');
exit();
