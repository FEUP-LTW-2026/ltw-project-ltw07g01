<?php
declare(strict_types=1);
require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../utils/equipment-data.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/layout/common.tpl.php');
require_once(__DIR__ . '/../templates/pages/equipment.tpl.php');

$session = new Session();

if (!$session->isLoggedIn()) {
    header('Location: /actions/login.php');
    exit();
}

$db = getDatabaseConnection();
$userId = (int)$session->getId();
$isAdmin = false;
$s = $db->prepare('SELECT 1 FROM admins WHERE user_id = :id');
$s->execute([':id' => $userId]);
$isAdmin = (bool)$s->fetch();

$msg = '';
$error = '';

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax']) && ($_POST['_action'] ?? '') === 'toggle') {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    $targetId = (int)($_POST['target_id'] ?? 0);
    if ($targetId) {
        $db->prepare('UPDATE equipment SET is_available = NOT is_available WHERE id=?')->execute([$targetId]);
        $s = $db->prepare('SELECT is_available FROM equipment WHERE id=?');
        $s->execute([$targetId]);
        $row = $s->fetch();
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'is_available' => (bool)$row['is_available']]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false]);
    }
    exit;
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $gymId = (int)($_POST['gym_id'] ?? 0);
        $bodyPart = trim($_POST['body_part'] ?? '');
        if (!$name || !$gymId || !$bodyPart) {
            $error = 'All fields are required.';
        } else {
            $db->prepare('INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES (?,?,?,1)')
                    ->execute([$name, $gymId, $bodyPart]);
            header('Location: /pages/equipment.php?msg=Equipment+added.');
            exit;
        }

    } elseif ($action === 'update') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $gymId = (int)($_POST['gym_id'] ?? 0);
        $bodyPart = trim($_POST['body_part'] ?? '');
        if (!$targetId || !$name || !$gymId || !$bodyPart) {
            $error = 'Invalid data.';
        } else {
            $db->prepare('UPDATE equipment SET name=?, gym_id=?, body_part=? WHERE id=?')
                    ->execute([$name, $gymId, $bodyPart, $targetId]);
            header('Location: /pages/equipment.php?msg=Equipment+updated.');
            exit;
        }

    } elseif ($action === 'toggle') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            $db->prepare('UPDATE equipment SET is_available = NOT is_available WHERE id=?')->execute([$targetId]);
            header('Location: /pages/equipment.php?msg=Status+updated.');
            exit;
        }

    } elseif ($action === 'delete') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            $db->prepare('DELETE FROM equipment WHERE id=?')->execute([$targetId]);
            header('Location: /pages/equipment.php?msg=Equipment+removed.');
            exit;
        }
    }
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

if ($isAdmin) {
    $editItem = null;
    if (isset($_GET['edit'])) {
        $editId = (int)$_GET['edit'];
        $s = $db->prepare('SELECT id, name, gym_id, body_part, is_available FROM equipment WHERE id=?');
        $s->execute([$editId]);
        $editItem = $s->fetch() ?: null;
    }

    $gymList = $db->query('SELECT id, name, city FROM gym_locations ORDER BY city, name')->fetchAll(PDO::FETCH_ASSOC);

    $allEquip = $db->query(
            'SELECT e.id, e.name, e.body_part, e.is_available, e.gym_id,
                gl.name AS gym_name, gl.city AS gym_city
         FROM equipment e
         JOIN gym_locations gl ON gl.id = e.gym_id
         ORDER BY gl.city, gl.name, e.body_part, e.name'
    )->fetchAll(PDO::FETCH_ASSOC);

    $byGym = [];
    foreach ($allEquip as $eq) {
        $key = $eq['gym_id'];
        if (!isset($byGym[$key])) {
            $byGym[$key] = ['gym_name' => $eq['gym_name'], 'gym_city' => $eq['gym_city'], 'items' => []];
        }
        $byGym[$key]['items'][] = $eq;
    }

    drawDashHeader($session, $db, 'equipment', ['equipment']);
    drawAdminEquipment($allEquip, $byGym, $gymList, $editItem, $msg, $error);
    drawFooter();

} else {
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
}
