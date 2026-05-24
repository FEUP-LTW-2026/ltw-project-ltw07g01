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
    'pilates-1'  => ['type' => 'pilates', 'qty' => 1],
    'pilates-5'  => ['type' => 'pilates', 'qty' => 5],
    'pilates-10' => ['type' => 'pilates', 'qty' => 10],
    'cycling-1'  => ['type' => 'cycling', 'qty' => 1],
    'cycling-5'  => ['type' => 'cycling', 'qty' => 5],
    'cycling-10' => ['type' => 'cycling', 'qty' => 10],
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
            INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, pilates_classes, cycling_classes)
            VALUES (?, ?, ?, ?, 0, 0)
        ');
        $stmt->execute([$userId, $gymPlan, $gymStart, $gymEnd]);
    }

} elseif (isset($classPacks[$plan])) {
    $pack = $classPacks[$plan];
    $col  = $pack['type'] === 'pilates' ? 'pilates_classes' : 'cycling_classes';
    $qty  = $pack['qty'];

    $stmt = $db->prepare('SELECT client_id FROM memberships WHERE client_id = ?');
    $stmt->execute([$userId]);

    if ($stmt->fetch()) {
        $stmt = $db->prepare("UPDATE memberships SET $col = $col + ? WHERE client_id = ?");
        $stmt->execute([$qty, $userId]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, pilates_classes, cycling_classes)
            VALUES (?, NULL, NULL, NULL, ?, ?)
        ");
        $pilates = $pack['type'] === 'pilates' ? $qty : 0;
        $cycling = $pack['type'] === 'cycling' ? $qty : 0;
        $stmt->execute([$userId, $pilates, $cycling]);
    }
} else {
    header('Location: /pages/membership.php');
    exit();
}

header('Location: /pages/membership.php?subscribed=1');
exit();
