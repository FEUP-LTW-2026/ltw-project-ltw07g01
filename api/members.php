<?php
declare(strict_types=1);
require_once __DIR__ . '/../database/connection.db.php';
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../database/Auth.php';
require_once __DIR__ . '/../database/User.php';

header('Content-Type: application/json');

$session = new Session();
$db      = getDatabaseConnection();

$method = strtoupper($_POST['_method'] ?? $_SERVER['REQUEST_METHOD']);
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? '';

function requireAdmin(Session $session, PDO $db): void {
    Auth::requireAdmin($session, $db);
}

function savePhoto(PDO $db, int $userId): void {
    User::saveProfilePhoto($db, $userId, __DIR__ . '/../images/profile_photos/');
}

function parseDMY(string $date): ?string {
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return $date ?: null;
}

if ($method === 'POST' && $action === 'promote_trainer') {
    requireAdmin($session, $db);
    $targetId = (int)($_POST['target_id'] ?? 0);
    if (!$targetId) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing target_id']));
    }
    $s = $db->prepare('SELECT 1 FROM clients WHERE user_id = ?');
    $s->execute([$targetId]);
    if (!$s->fetch()) {
        http_response_code(404);
        die(json_encode(['error' => 'Member not found.']));
    }
    $db->prepare('DELETE FROM memberships WHERE client_id = ?')->execute([$targetId]);
    $db->prepare('DELETE FROM client_classes WHERE client_id = ?')->execute([$targetId]);
    $db->prepare('DELETE FROM clients WHERE user_id = ?')->execute([$targetId]);
    $db->prepare('INSERT OR IGNORE INTO trainers (user_id) VALUES (?)')->execute([$targetId]);
    echo json_encode(['ok' => true, 'msg' => 'Member promoted to trainer.']);
    exit;
}

if ($method === 'POST') {
    requireAdmin($session, $db);

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $username  = trim($_POST['username']   ?? '');
    $password  = $_POST['password']        ?? '';

    if (!$firstName || !$lastName || !$email || !$username || strlen($password) < 6) {
        http_response_code(422);
        die(json_encode(['error' => 'All fields are required. Password must be at least 6 characters.']));
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        die(json_encode(['error' => 'Invalid email address.']));
    }
    if (!preg_match('/^[\w.]{3,30}$/', $username)) {
        http_response_code(422);
        die(json_encode(['error' => 'Username must be 3-30 characters (letters, numbers, underscores, dots).']));
    }

    $s = $db->prepare('SELECT 1 FROM users WHERE email = ? OR username = ?');
    $s->execute([$email, $username]);
    if ($s->fetch()) {
        http_response_code(409);
        die(json_encode(['error' => 'Email or username is already taken.']));
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare('INSERT INTO users (username, email, password_hash, first_name, last_name) VALUES (?,?,?,?,?)')
       ->execute([$username, $email, $hash, $firstName, $lastName]);
    $newId = (int)$db->lastInsertId();
    $db->prepare('INSERT INTO clients (user_id) VALUES (?)')->execute([$newId]);

    $plan    = $_POST['gym_plan'] ?? 'none';
    $credits = max(0, (int)($_POST['classes_remaining'] ?? 0));
    if ($plan !== 'none' && $plan !== '') {
        $start = parseDMY(trim($_POST['gym_start'] ?? '')) ?? date('Y-m-d');
        $end   = parseDMY(trim($_POST['gym_end']   ?? ''));
        $db->prepare('INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (?,?,?,?,?)')
           ->execute([$newId, $plan, $start, $end, $credits]);
    }

    savePhoto($db, $newId);

    http_response_code(201);
    echo json_encode(['id' => $newId, 'msg' => 'Member created successfully.']);
    exit;
}

if ($method === 'PUT') {
    requireAdmin($session, $db);

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing id in URL (?id=X)']));
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $username  = trim($_POST['username']   ?? '');
    $password  = $_POST['password']        ?? '';
    $plan      = $_POST['gym_plan']        ?? '';
    $start     = parseDMY(trim($_POST['gym_start'] ?? '')) ?? date('Y-m-d');
    $end       = parseDMY(trim($_POST['gym_end']   ?? ''));
    $credits   = max(0, (int)($_POST['classes_remaining'] ?? 0));

    if (!$firstName || !$lastName || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        die(json_encode(['error' => 'Invalid data.']));
    }
    if (!preg_match('/^[\w.]{3,30}$/', $username)) {
        http_response_code(422);
        die(json_encode(['error' => 'Username must be 3-30 characters (letters, numbers, underscores, dots).']));
    }
    if ($password !== '' && strlen($password) < 6) {
        http_response_code(422);
        die(json_encode(['error' => 'Password must be at least 6 characters.']));
    }

    $s = $db->prepare('SELECT 1 FROM users WHERE (email = ? OR username = ?) AND id != ?');
    $s->execute([$email, $username, $id]);
    if ($s->fetch()) {
        http_response_code(409);
        die(json_encode(['error' => 'Email or username is already in use.']));
    }

    if ($password !== '') {
        $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, username = ?, password_hash = ? WHERE id = ?')
           ->execute([$firstName, $lastName, $email, $username, password_hash($password, PASSWORD_DEFAULT), $id]);
    } else {
        $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, username = ? WHERE id = ?')
           ->execute([$firstName, $lastName, $email, $username, $id]);
    }

    if ($plan === 'none' || $plan === '') {
        $db->prepare('DELETE FROM memberships WHERE client_id = ?')->execute([$id]);
    } else {
        $start = $start ?: date('Y-m-d');
        $db->prepare(
            'INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining)
             VALUES (?,?,?,?,?)
             ON CONFLICT(client_id) DO UPDATE SET gym_plan=excluded.gym_plan, gym_start=excluded.gym_start, gym_end=excluded.gym_end, classes_remaining=excluded.classes_remaining'
        )->execute([$id, $plan, $start, $end, $credits]);
    }

    savePhoto($db, $id);

    echo json_encode(['msg' => 'Member updated.']);
    exit;
}

if ($method === 'DELETE') {
    requireAdmin($session, $db);

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing id in URL (?id=X)']));
    }

    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    http_response_code(204);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
