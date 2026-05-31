<?php
declare(strict_types=1);
require_once __DIR__ . '/../database/connection.db.php';
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../database/Auth.php';
require_once __DIR__ . '/../database/Membership.php';

header('Content-Type: application/json');

$session = new Session();
$db      = getDatabaseConnection();

$method = strtoupper($_POST['_method'] ?? $_SERVER['REQUEST_METHOD']);
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? '';

$userId = $session->isLoggedIn() ? (int)$session->getId() : 0;
$role   = $userId ? Auth::getRole($db, $userId) : null;

function requireAuth(?string $role): void {
    if (!$role) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized']));
    }
}

function requireCsrf(Session $session): void {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }
}

function requireAdminOrTrainer(?string $role): void {
    if (!in_array($role, ['admin', 'trainer'], true)) {
        http_response_code(403);
        die(json_encode(['error' => 'Forbidden']));
    }
}

function saveClassPhoto(PDO $db, int $classId): void {
    $pf = $_FILES['class_photo'] ?? null;
    if (!$pf || $pf['error'] !== UPLOAD_ERR_OK || $pf['size'] > 2 * 1024 * 1024) return;
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($pf['tmp_name']);
    if (!isset($ext[$mime])) return;
    $dir = __DIR__ . '/../images/class_photos/';
    @mkdir($dir, 0755, true);
    $fn = 'class_' . $classId . '_' . time() . '.' . $ext[$mime];
    if (move_uploaded_file($pf['tmp_name'], $dir . $fn)) {
        $db->prepare('UPDATE classes SET photo=? WHERE id=?')
           ->execute(['../images/class_photos/' . $fn, $classId]);
    }
}

if ($method === 'GET' && $action === 'enrollments') {
    requireAdminOrTrainer($role);
    $classId = $id ?? (int)($_GET['class_id'] ?? 0);
    if (!$classId) {
        http_response_code(400);
        die(json_encode(['ok' => false, 'error' => 'Missing class id']));
    }
    if ($role === 'trainer') {
        $s = $db->prepare('SELECT 1 FROM classes WHERE id=? AND trainer_id=?');
        $s->execute([$classId, $userId]);
        if (!$s->fetch()) {
            http_response_code(403);
            die(json_encode(['ok' => false, 'error' => 'Forbidden']));
        }
    }
    $s = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.username, u.profile_photo
         FROM client_classes cc
         JOIN users u ON u.id = cc.client_id
         WHERE cc.class_id = ?
         ORDER BY u.first_name, u.last_name'
    );
    $s->execute([$classId]);
    echo json_encode(['ok' => true, 'enrollments' => $s->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($method === 'POST' && $action === 'enroll') {
    requireAuth($role);
    requireCsrf($session);
    if ($role !== 'client') {
        http_response_code(403);
        die(json_encode(['ok' => false, 'error' => 'Only clients can book classes']));
    }
    $classId = (int)($_POST['class_id'] ?? 0);
    if (!$classId) {
        http_response_code(400);
        die(json_encode(['ok' => false, 'error' => 'Missing class id']));
    }

    $s = $db->prepare('SELECT COUNT(*) FROM client_classes WHERE client_id=? AND class_id=?');
    $s->execute([$userId, $classId]);
    if ($s->fetchColumn()) {
        http_response_code(409);
        die(json_encode(['ok' => false, 'error' => 'already']));
    }

    $s = $db->prepare('SELECT capacity, (SELECT COUNT(*) FROM client_classes WHERE class_id=c.id) AS enrolled FROM classes c WHERE id=?');
    $s->execute([$classId]);
    $cl = $s->fetch();
    if (!$cl || $cl['enrolled'] >= $cl['capacity']) {
        http_response_code(409);
        die(json_encode(['ok' => false, 'error' => 'full']));
    }

    if (Membership::getTotalCredits($db, $userId) <= 0) {
        http_response_code(409);
        die(json_encode(['ok' => false, 'error' => 'no_credits']));
    }

    $db->prepare('INSERT INTO client_classes (client_id, class_id) VALUES (?,?)')->execute([$userId, $classId]);
    $db->prepare('UPDATE memberships SET classes_remaining = classes_remaining - 1 WHERE client_id = ?')->execute([$userId]);

    $newEnrolled = (int)$cl['enrolled'] + 1;
    $newCredits  = Membership::getTotalCredits($db, $userId);
    echo json_encode([
        'ok'       => true,
        'enrolled' => $newEnrolled,
        'capacity' => (int)$cl['capacity'],
        'spots'    => (int)$cl['capacity'] - $newEnrolled,
        'credits'  => $newCredits,
    ]);
    exit;
}

if ($method === 'POST' && $action === 'cancel') {
    requireAuth($role);
    requireCsrf($session);
    if ($role !== 'client') {
        http_response_code(403);
        die(json_encode(['ok' => false, 'error' => 'Forbidden']));
    }
    $classId = (int)($_POST['class_id'] ?? 0);
    if (!$classId) {
        http_response_code(400);
        die(json_encode(['ok' => false, 'error' => 'Missing class id']));
    }

    $s = $db->prepare('SELECT c.schedule FROM client_classes cc JOIN classes c ON c.id = cc.class_id WHERE cc.client_id=? AND cc.class_id=?');
    $s->execute([$userId, $classId]);
    $enrollment = $s->fetch();
    if (!$enrollment) {
        http_response_code(404);
        die(json_encode(['ok' => false, 'error' => 'not_enrolled']));
    }
    if ($enrollment['schedule'] <= date('Y-m-d H:i:s')) {
        http_response_code(409);
        die(json_encode(['ok' => false, 'error' => 'past_class']));
    }

    $db->beginTransaction();
    $db->prepare('DELETE FROM client_classes WHERE client_id=? AND class_id=?')->execute([$userId, $classId]);
    $db->prepare('UPDATE memberships SET classes_remaining = classes_remaining + 1 WHERE client_id = ?')->execute([$userId]);
    $db->commit();

    $s = $db->prepare('SELECT capacity, (SELECT COUNT(*) FROM client_classes WHERE class_id=c.id) AS enrolled FROM classes c WHERE id=?');
    $s->execute([$classId]);
    $cl = $s->fetch();
    $newEnrolled = (int)$cl['enrolled'];
    $newCredits  = Membership::getTotalCredits($db, $userId);
    echo json_encode([
        'ok'       => true,
        'enrolled' => $newEnrolled,
        'capacity' => (int)$cl['capacity'],
        'spots'    => (int)$cl['capacity'] - $newEnrolled,
        'credits'  => $newCredits,
    ]);
    exit;
}

if ($method === 'POST' && $action === 'unenroll') {
    requireAdminOrTrainer($role);
    requireCsrf($session);
    $classId  = (int)($_POST['class_id']  ?? 0);
    $clientId = (int)($_POST['client_id'] ?? 0);
    if (!$classId || !$clientId) {
        http_response_code(400);
        die(json_encode(['ok' => false, 'error' => 'Missing params']));
    }
    if ($role === 'trainer') {
        $s = $db->prepare('SELECT 1 FROM classes WHERE id=? AND trainer_id=?');
        $s->execute([$classId, $userId]);
        if (!$s->fetch()) {
            http_response_code(403);
            die(json_encode(['ok' => false, 'error' => 'Forbidden']));
        }
    }
    $db->beginTransaction();
    $db->prepare('DELETE FROM client_classes WHERE client_id=? AND class_id=?')->execute([$clientId, $classId]);
    $db->prepare('UPDATE memberships SET classes_remaining = classes_remaining + 1 WHERE client_id = ?')->execute([$clientId]);
    $db->commit();
    $s = $db->prepare('SELECT COUNT(*) FROM client_classes WHERE class_id=?');
    $s->execute([$classId]);
    echo json_encode(['ok' => true, 'enrolled' => (int)$s->fetchColumn()]);
    exit;
}

if ($method === 'POST' && $action === 'review') {
    requireAuth($role);
    requireCsrf($session);
    if ($role !== 'client') {
        http_response_code(403);
        die(json_encode(['ok' => false, 'error' => 'Forbidden']));
    }
    $classId = (int)($_POST['class_id'] ?? 0);
    $rating  = (int)($_POST['rating']   ?? 0);
    $comment = mb_substr(trim($_POST['comment'] ?? ''), 0, 500);

    if ($classId <= 0 || $rating < 1 || $rating > 5) {
        http_response_code(422);
        die(json_encode(['ok' => false, 'error' => 'invalid']));
    }

    $s = $db->prepare('SELECT 1 FROM client_classes WHERE client_id=? AND class_id=?');
    $s->execute([$userId, $classId]);
    if (!$s->fetch()) {
        http_response_code(403);
        die(json_encode(['ok' => false, 'error' => 'not_enrolled']));
    }

    $s = $db->prepare('SELECT class_type_id, trainer_id, schedule FROM classes WHERE id=?');
    $s->execute([$classId]);
    $cl = $s->fetch();
    if (!$cl || $cl['schedule'] >= date('Y-m-d H:i:s')) {
        http_response_code(409);
        die(json_encode(['ok' => false, 'error' => 'not_past']));
    }

    $s = $db->prepare('SELECT COUNT(*) FROM reviews WHERE client_id=? AND class_id=?');
    $s->execute([$userId, $classId]);
    if ($s->fetchColumn()) {
        http_response_code(409);
        die(json_encode(['ok' => false, 'error' => 'already_reviewed']));
    }

    $db->prepare('INSERT INTO reviews (client_id, class_id, rating, comment) VALUES (?,?,?,?)')
        ->execute([$userId, $classId, $rating, $comment ?: null]);

    $s = $db->prepare(
        'SELECT ROUND(AVG(r.rating),1) AS avg_rating, COUNT(*) AS review_count
         FROM reviews r
         JOIN classes c2 ON c2.id = r.class_id
         WHERE c2.trainer_id = ? AND c2.class_type_id = ?'
    );
    $s->execute([$cl['trainer_id'], $cl['class_type_id']]);
    $agg = $s->fetch();
    echo json_encode([
        'ok'           => true,
        'avg_rating'   => (float)($agg['avg_rating'] ?? 0),
        'review_count' => (int)($agg['review_count'] ?? 0),
    ]);
    exit;
}

if ($method === 'POST') {
    requireAdminOrTrainer($role);
    requireCsrf($session);

    $classTypeId = (int)($_POST['class_type_id'] ?? 0);
    $gymId       = (int)($_POST['gym_id']         ?? 0);
    $trainerId   = $role === 'trainer' ? $userId : ((int)($_POST['trainer_id'] ?? 0) ?: null);
    $schedule    = trim($_POST['schedule']         ?? '');
    $duration    = (int)($_POST['duration_min']   ?? 0);
    $capacity    = (int)($_POST['capacity']        ?? 0);
    $description = mb_substr(trim($_POST['description'] ?? ''), 0, 500);

    if (!$classTypeId || !$gymId || !$schedule || $duration <= 0 || $capacity <= 0) {
        http_response_code(422);
        die(json_encode(['error' => 'Missing required fields.']));
    }

    if ($role === 'trainer') {
        $s = $db->prepare('SELECT 1 FROM trainer_specializations WHERE trainer_id=? AND class_type_id=?');
        $s->execute([$userId, $classTypeId]);
        $hasSpec = (bool)$s->fetch();
        if (!$hasSpec) {
            $s = $db->prepare('SELECT 1 FROM classes WHERE trainer_id=? AND class_type_id=?');
            $s->execute([$userId, $classTypeId]);
            $hasSpec = (bool)$s->fetch();
        }
        $s = $db->prepare('SELECT 1 FROM trainer_locations WHERE trainer_id=? AND gym_id=?');
        $s->execute([$userId, $gymId]);
        $hasGym = (bool)$s->fetch();
        if (!$hasGym) {
            $s = $db->prepare('SELECT 1 FROM classes WHERE trainer_id=? AND gym_id=?');
            $s->execute([$userId, $gymId]);
            $hasGym = (bool)$s->fetch();
        }
        if (!$hasSpec || !$hasGym) {
            http_response_code(403);
            die(json_encode(['error' => 'Not authorized for this class type or gym.']));
        }
    }

    $db->prepare('INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity, description) VALUES (?,?,?,?,?,?,?)')
       ->execute([$classTypeId, $gymId, $trainerId, $schedule, $duration, $capacity, $description]);
    $newId = (int)$db->lastInsertId();
    saveClassPhoto($db, $newId);

    http_response_code(201);
    echo json_encode(['id' => $newId, 'msg' => 'Class created.']);
    exit;
}

if ($method === 'PUT') {
    requireAdminOrTrainer($role);
    requireCsrf($session);

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing class id in URL (?id=X)']));
    }

    if ($role === 'trainer') {
        $s = $db->prepare('SELECT 1 FROM classes WHERE id=? AND trainer_id=?');
        $s->execute([$id, $userId]);
        if (!$s->fetch()) {
            http_response_code(403);
            die(json_encode(['error' => 'You can only edit your own classes.']));
        }
    }

    $classTypeId = (int)($_POST['class_type_id'] ?? 0);
    $gymId       = (int)($_POST['gym_id']         ?? 0);
    $trainerId   = $role === 'trainer' ? $userId : ((int)($_POST['trainer_id'] ?? 0) ?: null);
    $schedule    = trim($_POST['schedule']         ?? '');
    $duration    = (int)($_POST['duration_min']   ?? 0);
    $capacity    = (int)($_POST['capacity']        ?? 0);
    $description = mb_substr(trim($_POST['description'] ?? ''), 0, 500);

    if (!$classTypeId || !$gymId || !$schedule || $duration <= 0 || $capacity <= 0) {
        http_response_code(422);
        die(json_encode(['error' => 'Missing required fields.']));
    }

    if ($role === 'trainer') {
        $db->prepare('UPDATE classes SET class_type_id=?, gym_id=?, trainer_id=?, schedule=?, duration_min=?, capacity=?, description=? WHERE id=? AND trainer_id=?')
           ->execute([$classTypeId, $gymId, $trainerId, $schedule, $duration, $capacity, $description, $id, $userId]);
    } else {
        $db->prepare('UPDATE classes SET class_type_id=?, gym_id=?, trainer_id=?, schedule=?, duration_min=?, capacity=?, description=? WHERE id=?')
           ->execute([$classTypeId, $gymId, $trainerId, $schedule, $duration, $capacity, $description, $id]);
    }
    saveClassPhoto($db, $id);

    echo json_encode(['msg' => 'Class updated.']);
    exit;
}

if ($method === 'DELETE') {
    requireAdminOrTrainer($role);
    requireCsrf($session);

    if (!$id) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing class id in URL (?id=X)']));
    }

    if ($role === 'trainer') {
        $db->prepare('DELETE FROM classes WHERE id=? AND trainer_id=?')->execute([$id, $userId]);
    } else {
        $db->prepare('DELETE FROM classes WHERE id=?')->execute([$id]);
    }
    http_response_code(204);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
