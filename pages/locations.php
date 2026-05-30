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


$msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';

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
