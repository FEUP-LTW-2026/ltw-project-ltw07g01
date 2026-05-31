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
        $stmt = $db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.username, u.profile_photo,
                   t.bio, t.certifications,
                   GROUP_CONCAT(DISTINCT gl.name) AS gyms,
                   GROUP_CONCAT(DISTINCT ct.name) AS class_types
            FROM trainers t
            JOIN users u ON u.id = t.user_id
            LEFT JOIN trainer_locations tl ON tl.trainer_id = t.user_id
            LEFT JOIN gym_locations gl ON gl.id = tl.gym_id
            LEFT JOIN trainer_specializations ts ON ts.trainer_id = t.user_id
            LEFT JOIN class_types ct ON ct.id = ts.class_type_id
                AND ct.name IN ('Pilates', 'Cycling', 'Personal Training')
            WHERE u.id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$id]);
        $trainer = $stmt->fetch();
        if (!$trainer) {
            http_response_code(404);
            die(json_encode(['error' => 'Trainer not found']));
        }
        $s = $db->prepare('SELECT class_type_id FROM trainer_specializations WHERE trainer_id = ?');
        $s->execute([$id]);
        $trainer['specialization_ids'] = $s->fetchAll(PDO::FETCH_COLUMN);

        $s = $db->prepare('SELECT gym_id FROM trainer_locations WHERE trainer_id = ?');
        $s->execute([$id]);
        $trainer['gym_ids'] = $s->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode($trainer);
    } else {
        $stmt = $db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.username, u.profile_photo,
                   t.bio, t.certifications,
                   GROUP_CONCAT(DISTINCT gl.name) AS gyms,
                   GROUP_CONCAT(DISTINCT ct.name) AS class_types
            FROM trainers t
            JOIN users u ON u.id = t.user_id
            LEFT JOIN trainer_locations tl ON tl.trainer_id = t.user_id
            LEFT JOIN gym_locations gl ON gl.id = tl.gym_id
            LEFT JOIN trainer_specializations ts ON ts.trainer_id = t.user_id
            LEFT JOIN class_types ct ON ct.id = ts.class_type_id
                AND ct.name IN ('Pilates', 'Cycling', 'Personal Training')
            GROUP BY u.id
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute();
        $trainers = $stmt->fetchAll();

        if (($_GET['include'] ?? '') === 'meta') {
            $classTypes = $db->query("SELECT id, name FROM class_types WHERE name IN ('Pilates', 'Cycling', 'Personal Training') ORDER BY name")->fetchAll();
            $gymList    = $db->query('SELECT id, name, city FROM gym_locations ORDER BY city, name')->fetchAll();
            echo json_encode(['trainers' => $trainers, 'classTypes' => $classTypes, 'gymList' => $gymList]);
        } else {
            echo json_encode($trainers);
        }
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

function saveTrainerPhoto(PDO $db, int $userId): void {
    $pf = $_FILES['photo'] ?? null;
    if (!$pf || $pf['error'] !== UPLOAD_ERR_OK || $pf['size'] > 5 * 1024 * 1024) return;
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($pf['tmp_name']);
    if (!isset($ext[$mime])) return;
    $dir = __DIR__ . '/../images/profile_photos/';
    @mkdir($dir, 0755, true);
    $fn = 'user_' . $userId . '_' . time() . '.' . $ext[$mime];
    if (move_uploaded_file($pf['tmp_name'], $dir . $fn)) {
        $db->prepare('UPDATE users SET profile_photo=? WHERE id=?')
           ->execute(['../images/profile_photos/' . $fn, $userId]);
    }
}

if ($method === 'POST') {
    requireAdmin($session, $db);

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $username  = trim($_POST['username']   ?? '');
    $password  = $_POST['password']        ?? '';
    $bio       = mb_substr(trim($_POST['bio']            ?? ''), 0, 500);
    $certs     = mb_substr(trim($_POST['certifications'] ?? ''), 0, 500);

    if (!$firstName || !$lastName || !$email || !$username || strlen($password) < 6) {
        http_response_code(422);
        die(json_encode(['error' => 'All fields required. Password min 6 chars.']));
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        die(json_encode(['error' => 'Invalid email.']));
    }
    if (!preg_match('/^[\w.]{3,30}$/', $username)) {
        http_response_code(422);
        die(json_encode(['error' => 'Username: 3-30 chars, letters/numbers/underscore/dot.']));
    }

    $s = $db->prepare('SELECT 1 FROM users WHERE email = ? OR username = ?');
    $s->execute([$email, $username]);
    if ($s->fetch()) {
        http_response_code(409);
        die(json_encode(['error' => 'Email or username already taken.']));
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare('INSERT INTO users (username, email, password_hash, first_name, last_name) VALUES (?,?,?,?,?)')
       ->execute([$username, $email, $hash, $firstName, $lastName]);
    $newId = (int)$db->lastInsertId();

    $db->prepare('INSERT INTO trainers (user_id, bio, certifications) VALUES (?,?,?)')
       ->execute([$newId, $bio ?: null, $certs ?: null]);

    foreach (array_map('intval', (array)($_POST['specializations'] ?? [])) as $ctId) {
        if ($ctId > 0) $db->prepare('INSERT OR IGNORE INTO trainer_specializations (trainer_id, class_type_id) VALUES (?,?)')->execute([$newId, $ctId]);
    }
    foreach (array_map('intval', (array)($_POST['gyms'] ?? [])) as $gId) {
        if ($gId > 0) $db->prepare('INSERT OR IGNORE INTO trainer_locations (trainer_id, gym_id) VALUES (?,?)')->execute([$newId, $gId]);
    }

    saveTrainerPhoto($db, $newId);

    http_response_code(201);
    echo json_encode(['id' => $newId, 'msg' => 'Trainer created.']);
    exit;
}

if ($method === 'PUT') {
    requireAdmin($session, $db);

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing trainer id in URL (?id=X)']));
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $bio       = mb_substr(trim($_POST['bio']            ?? ''), 0, 500);
    $certs     = mb_substr(trim($_POST['certifications'] ?? ''), 0, 500);

    if (!$firstName || !$lastName || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        die(json_encode(['error' => 'Invalid data.']));
    }

    $s = $db->prepare('SELECT 1 FROM users WHERE email = ? AND id != ?');
    $s->execute([$email, $id]);
    if ($s->fetch()) {
        http_response_code(409);
        die(json_encode(['error' => 'Email already in use.']));
    }

    $db->prepare('UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?')
       ->execute([$firstName, $lastName, $email, $id]);
    $db->prepare('UPDATE trainers SET bio=?, certifications=? WHERE user_id=?')
       ->execute([$bio ?: null, $certs ?: null, $id]);

    $db->prepare('DELETE FROM trainer_specializations WHERE trainer_id=?')->execute([$id]);
    foreach (array_map('intval', (array)($_POST['specializations'] ?? [])) as $ctId) {
        if ($ctId > 0) $db->prepare('INSERT OR IGNORE INTO trainer_specializations (trainer_id, class_type_id) VALUES (?,?)')->execute([$id, $ctId]);
    }

    $db->prepare('DELETE FROM trainer_locations WHERE trainer_id=?')->execute([$id]);
    foreach (array_map('intval', (array)($_POST['gyms'] ?? [])) as $gId) {
        if ($gId > 0) $db->prepare('INSERT OR IGNORE INTO trainer_locations (trainer_id, gym_id) VALUES (?,?)')->execute([$id, $gId]);
    }

    saveTrainerPhoto($db, $id);

    echo json_encode(['msg' => 'Trainer updated.']);
    exit;
}

if ($method === 'DELETE') {
    requireAdmin($session, $db);

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing trainer id in URL (?id=X)']));
    }

    $db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    http_response_code(204);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
