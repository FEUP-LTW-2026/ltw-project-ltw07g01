<?php
declare(strict_types=1);
require_once('../utils/session.php');
require_once('../database/connection.db.php');
require_once('../templates/common.tpl.php');
require_once('../templates/confirm-purchase.tpl.php');

$session = new Session();
$db = getDatabaseConnection();

if (!$session->isLoggedIn()) {
    header('Location: /actions/login.php');
    exit();
}

$userId = (int)$session->getId();
$stmt = $db->prepare('SELECT 1 FROM clients WHERE user_id = ?');
$stmt->execute([$userId]);
if (!$stmt->fetch()) {
    header('Location: /pages/membership.php');
    exit();
}

$planKey = $_GET['plan'] ?? '';

$plans = [
    'gym-1'      => ['name' => 'Gym Basic',        'price' => '29 €', 'period' => '/month', 'desc' => 'Weight room, free schedule access, training app included.', 'credits' => null],
    'gym-2'      => ['name' => 'Gym Pro',          'price' => '39 €', 'period' => '/month', 'desc' => 'All locations, premium classes, nutritional consulting.',    'credits' => null],
    'gym-3'      => ['name' => 'Gym Ultra',        'price' => '49 €', 'period' => '/month', 'desc' => '24/7 access, unlimited group classes, monthly assessment.', 'credits' => null],
    'classes-1'  => ['name' => '1 Class Credit',   'price' => '10 €', 'period' => '',       'desc' => '1 credit usable in any group class.',                       'credits' => 1],
    'classes-5'  => ['name' => '5 Class Credits',  'price' => '40 €', 'period' => '',       'desc' => '5 credits usable in any group class.',                      'credits' => 5],
    'classes-10' => ['name' => '10 Class Credits', 'price' => '75 €', 'period' => '',       'desc' => '10 credits usable in any group class.',                     'credits' => 10],
];

if (!isset($plans[$planKey])) {
    header('Location: /pages/membership.php');
    exit();
}

drawDashHeader($session, $db, 'membership', ['membership']);
drawConfirmPurchase($plans[$planKey], $planKey);
drawFooter();
