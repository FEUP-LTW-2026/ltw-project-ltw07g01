<?php
declare(strict_types=1);

require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/layout/common.tpl.php');
require_once(__DIR__ . '/../templates/pages/locations.tpl.php');

$session = new Session();
$db = getDatabaseConnection();

$isAdmin = false;
if ($session->isLoggedIn()) {
    $s = $db->prepare('SELECT 1 FROM admins WHERE user_id = :id');
    $s->execute([':id' => (int)$session->getId()]);
    $isAdmin = (bool)$s->fetch();
}

$msg   = '';
$error = '';

function saveLocationPhoto(PDO $db, int $id, ?array $file): void {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) return;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime    = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) return;
    $destDir = __DIR__ . '/../images/location_photos/';
    @mkdir($destDir, 0755, true);
    $fn = 'loc_' . $id . '_' . time() . '.' . $allowed[$mime];
    if (move_uploaded_file($file['tmp_name'], $destDir . $fn)) {
        $db->prepare('UPDATE gym_locations SET photo=? WHERE id=?')
           ->execute(['../images/location_photos/' . $fn, $id]);
    }
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $name    = trim($_POST['name']    ?? '');
        $city    = trim($_POST['city']    ?? '');
        $address = trim($_POST['address'] ?? '');
        if (!$name || !$city) {
            $error = 'Name and city are required.';
        } else {
            $db->prepare('INSERT INTO gym_locations (name, city, address) VALUES (?,?,?)')
               ->execute([$name, $city, $address ?: null]);
            $newId = (int)$db->lastInsertId();
            saveLocationPhoto($db, $newId, $_FILES['photo'] ?? null);
            header('Location: /pages/locations.php?msg=Location+added.'); exit;
        }

    } elseif ($action === 'update') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $name     = trim($_POST['name']    ?? '');
        $city     = trim($_POST['city']    ?? '');
        $address  = trim($_POST['address'] ?? '');
        if (!$targetId || !$name || !$city) {
            $error = 'Invalid data.';
        } else {
            $db->prepare('UPDATE gym_locations SET name=?, city=?, address=? WHERE id=?')
               ->execute([$name, $city, $address ?: null, $targetId]);
            saveLocationPhoto($db, $targetId, $_FILES['photo'] ?? null);
            header('Location: /pages/locations.php?msg=Location+updated.'); exit;
        }

    } elseif ($action === 'delete') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            $db->prepare('DELETE FROM gym_locations WHERE id=?')->execute([$targetId]);
            header('Location: /pages/locations.php?msg=Location+removed.'); exit;
        }
    }
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

$stmt = $db->prepare('SELECT id, name, city, address, photo FROM gym_locations ORDER BY city, name');
$stmt->execute();
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($isAdmin) {
    $editItem = null;
    if (isset($_GET['edit'])) {
        $editId = (int)$_GET['edit'];
        $s = $db->prepare('SELECT id, name, city, address, photo FROM gym_locations WHERE id=?');
        $s->execute([$editId]);
        $editItem = $s->fetch() ?: null;
    }

    drawDashHeader($session, $db, 'locations', ['locations']);
    drawAdminLocations($locations, $editItem, $msg, $error);
    drawFooter();

} else {
    $session->isLoggedIn()
        ? drawDashHeader($session, $db, 'locations', ['locations'])
        : drawHeader($session, ['locations']);

    drawLocations($session, $locations);
    drawFooter();
}
