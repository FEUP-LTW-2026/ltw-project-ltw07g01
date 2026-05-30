<?php
declare(strict_types=1);
require_once __DIR__ . '/../database/connection.db.php';
require_once __DIR__ . '/../utils/session.php';

header('Content-Type: application/json');

$session = new Session();
$db      = getDatabaseConnection();

$method = strtoupper($_POST['_method'] ?? $_SERVER['REQUEST_METHOD']);
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── GET ──────────────────────────────────────────────────────────────────────

if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare('SELECT id, name, city, address, photo FROM gym_locations WHERE id = ?');
        $stmt->execute([$id]);
        $loc = $stmt->fetch();
        if (!$loc) {
            http_response_code(404);
            die(json_encode(['error' => 'Location not found']));
        }
        echo json_encode($loc);
    } else {
        $stmt = $db->query('SELECT id, name, city, address, photo FROM gym_locations ORDER BY city, name');
        echo json_encode($stmt->fetchAll());
    }
    exit;
}

// ── Admin-only methods below ──────────────────────────────────────────────────

function requireAdmin(Session $session, PDO $db): void {
    if (!$session->isLoggedIn()) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized']));
    }
    $s = $db->prepare('SELECT 1 FROM admins WHERE user_id = ?');
    $s->execute([$session->getId()]);
    if (!$s->fetch()) {
        http_response_code(403);
        die(json_encode(['error' => 'Forbidden']));
    }
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }
}

function savePhoto(PDO $db, int $locId): void {
    $pf = $_FILES['photo'] ?? null;
    if (!$pf || $pf['error'] !== UPLOAD_ERR_OK || $pf['size'] > 5 * 1024 * 1024) return;
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($pf['tmp_name']);
    if (!isset($ext[$mime])) return;
    $dir = __DIR__ . '/../images/location_photos/';
    @mkdir($dir, 0755, true);
    $fn = 'loc_' . $locId . '_' . time() . '.' . $ext[$mime];
    if (move_uploaded_file($pf['tmp_name'], $dir . $fn)) {
        $db->prepare('UPDATE gym_locations SET photo=? WHERE id=?')
           ->execute(['../images/location_photos/' . $fn, $locId]);
    }
}

// ── POST — create location ────────────────────────────────────────────────────

if ($method === 'POST') {
    requireAdmin($session, $db);

    $name    = trim($_POST['name']    ?? '');
    $city    = trim($_POST['city']    ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!$name || !$city) {
        http_response_code(422);
        die(json_encode(['error' => 'Name and city are required.']));
    }

    $db->prepare('INSERT INTO gym_locations (name, city, address) VALUES (?,?,?)')
       ->execute([$name, $city, $address ?: null]);
    $newId = (int)$db->lastInsertId();
    savePhoto($db, $newId);

    http_response_code(201);
    echo json_encode(['id' => $newId, 'msg' => 'Location added.']);
    exit;
}

// ── PUT — update location ─────────────────────────────────────────────────────

if ($method === 'PUT') {
    requireAdmin($session, $db);

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing location id in URL (?id=X)']));
    }

    $name    = trim($_POST['name']    ?? '');
    $city    = trim($_POST['city']    ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!$name || !$city) {
        http_response_code(422);
        die(json_encode(['error' => 'Name and city are required.']));
    }

    $db->prepare('UPDATE gym_locations SET name=?, city=?, address=? WHERE id=?')
       ->execute([$name, $city, $address ?: null, $id]);
    savePhoto($db, $id);

    echo json_encode(['msg' => 'Location updated.']);
    exit;
}

// ── DELETE — remove location ──────────────────────────────────────────────────

if ($method === 'DELETE') {
    requireAdmin($session, $db);

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing location id in URL (?id=X)']));
    }

    $db->prepare('DELETE FROM gym_locations WHERE id=?')->execute([$id]);
    http_response_code(204);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
