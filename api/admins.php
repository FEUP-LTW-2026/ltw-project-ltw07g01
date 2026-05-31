<?php
declare(strict_types=1);
require_once __DIR__ . '/../database/connection.db.php';
require_once __DIR__ . '/../utils/session.php';

header('Content-Type: application/json');

$session = new Session();
$db = getDatabaseConnection();

$method = strtoupper($_SERVER['REQUEST_METHOD']);
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare(
            'SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.profile_photo
             FROM admins a
             JOIN users u ON u.id = a.user_id
             WHERE a.user_id = ?'
        );
        $stmt->execute([$id]);
        $admin = $stmt->fetch();
        if (!$admin) {
            http_response_code(404);
            die(json_encode(['error' => 'Admin not found']));
        }
        echo json_encode($admin);
    } else {
        $stmt = $db->query(
            'SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.profile_photo
             FROM admins a
             JOIN users u ON u.id = a.user_id
             ORDER BY u.first_name, u.last_name'
        );
        echo json_encode($stmt->fetchAll());
    }
    exit;
}
function requireAdmin(Session $session, PDO $db): void
{
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
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $body['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    if (!$session->validateCsrfToken($token)) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }
}

function getBody(): array
{
    $body = json_decode(file_get_contents('php://input'), true);
    return is_array($body) ? $body : $_POST;
}

if ($method === 'POST') {
    requireAdmin($session, $db);

    $body = getBody();
    $userId = (int)($body['user_id'] ?? 0);

    if (!$userId) {
        http_response_code(422);
        die(json_encode(['error' => 'user_id is required']));
    }

    $s = $db->prepare('SELECT 1 FROM users WHERE id = ?');
    $s->execute([$userId]);
    if (!$s->fetch()) {
        http_response_code(404);
        die(json_encode(['error' => 'User not found']));
    }

    $s = $db->prepare('SELECT 1 FROM admins WHERE user_id = ?');
    $s->execute([$userId]);
    if ($s->fetch()) {
        http_response_code(409);
        die(json_encode(['error' => 'User is already an admin']));
    }

    $s = $db->prepare('SELECT 1 FROM trainers WHERE user_id = ?');
    $s->execute([$userId]);
    if ($s->fetch()) {
        $db->prepare('DELETE FROM trainer_specializations WHERE trainer_id=?')->execute([$userId]);
        $db->prepare('DELETE FROM trainer_locations WHERE trainer_id=?')->execute([$userId]);
        $db->prepare('DELETE FROM trainers WHERE user_id=?')->execute([$userId]);
    }

    $s = $db->prepare('SELECT 1 FROM clients WHERE user_id = ?');
    $s->execute([$userId]);
    if ($s->fetch()) {
        $db->prepare('DELETE FROM memberships WHERE client_id=?')->execute([$userId]);
        $db->prepare('DELETE FROM client_classes WHERE client_id=?')->execute([$userId]);
        $db->prepare('DELETE FROM clients WHERE user_id=?')->execute([$userId]);
    }

    $db->prepare('INSERT INTO admins (user_id) VALUES (?)')->execute([$userId]);

    http_response_code(201);
    echo json_encode(['msg' => 'User promoted to admin.']);
    exit;
}

if ($method === 'DELETE') {
    requireAdmin($session, $db);

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing admin id in URL (?id=X)']));
    }

    if ($id === (int)$session->getId()) {
        http_response_code(403);
        die(json_encode(['error' => 'Cannot remove your own admin role']));
    }

    $s = $db->prepare('SELECT 1 FROM admins WHERE user_id = ?');
    $s->execute([$id]);
    if (!$s->fetch()) {
        http_response_code(404);
        die(json_encode(['error' => 'Admin not found']));
    }

    $db->prepare('DELETE FROM admins WHERE user_id=?')->execute([$id]);

    http_response_code(204);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
