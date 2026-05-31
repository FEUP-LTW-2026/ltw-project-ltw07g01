<?php
declare(strict_types=1);
require_once __DIR__ . '/../database/connection.db.php';
require_once __DIR__ . '/../utils/session.php';

header('Content-Type: application/json');

$session = new Session();
$db      = getDatabaseConnection();

$method = strtoupper($_POST['_method'] ?? $_SERVER['REQUEST_METHOD']);
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare(
            'SELECT e.id, e.name, e.body_part, e.is_available, e.gym_id,
                    gl.name AS gym_name, gl.city AS gym_city
             FROM equipment e
             JOIN gym_locations gl ON gl.id = e.gym_id
             WHERE e.id = ?'
        );
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) {
            http_response_code(404);
            die(json_encode(['error' => 'Equipment not found']));
        }
        echo json_encode($item);
    } else {
        $stmt = $db->query(
            'SELECT e.id, e.name, e.body_part, e.is_available, e.gym_id,
                    gl.name AS gym_name, gl.city AS gym_city
             FROM equipment e
             JOIN gym_locations gl ON gl.id = e.gym_id
             ORDER BY gl.city, gl.name, e.body_part, e.name'
        );
        echo json_encode($stmt->fetchAll());
    }
    exit;
}

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

if ($method === 'POST') {
    requireAdmin($session, $db);

    $name     = trim($_POST['name']      ?? '');
    $gymId    = (int)($_POST['gym_id']   ?? 0);
    $bodyPart = trim($_POST['body_part'] ?? '');

    if (!$name || !$gymId || !$bodyPart) {
        http_response_code(422);
        die(json_encode(['error' => 'name, gym_id and body_part are required.']));
    }

    $db->prepare('INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES (?,?,?,1)')
       ->execute([$name, $gymId, $bodyPart]);

    http_response_code(201);
    echo json_encode(['id' => (int)$db->lastInsertId(), 'msg' => 'Equipment added.']);
    exit;
}

if ($method === 'PUT') {
    requireAdmin($session, $db);

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing equipment id in URL (?id=X)']));
    }

    $name     = trim($_POST['name']      ?? '');
    $gymId    = (int)($_POST['gym_id']   ?? 0);
    $bodyPart = trim($_POST['body_part'] ?? '');

    if (!$name || !$gymId || !$bodyPart) {
        http_response_code(422);
        die(json_encode(['error' => 'name, gym_id and body_part are required.']));
    }

    $db->prepare('UPDATE equipment SET name=?, gym_id=?, body_part=? WHERE id=?')
       ->execute([$name, $gymId, $bodyPart, $id]);

    echo json_encode(['msg' => 'Equipment updated.']);
    exit;
}

if ($method === 'PATCH') {
    requireAdmin($session, $db);

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing equipment id in URL (?id=X)']));
    }

    $db->prepare('UPDATE equipment SET is_available = NOT is_available WHERE id=?')->execute([$id]);

    $s = $db->prepare('SELECT is_available FROM equipment WHERE id=?');
    $s->execute([$id]);
    $row = $s->fetch();

    echo json_encode(['is_available' => (bool)$row['is_available']]);
    exit;
}

if ($method === 'DELETE') {
    requireAdmin($session, $db);

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing equipment id in URL (?id=X)']));
    }

    $db->prepare('DELETE FROM equipment WHERE id=?')->execute([$id]);

    http_response_code(204);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
