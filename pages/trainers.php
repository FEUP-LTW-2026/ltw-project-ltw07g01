<?php
declare(strict_types=1);
require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');
require_once(__DIR__ . '/../templates/trainers.tpl.php');

$session = new Session();
$db = getDatabaseConnection();

$userId = $session->isLoggedIn() ? (int)$session->getId() : 0;
$isAdmin = false;
if ($userId) {
    $s = $db->prepare('SELECT 1 FROM admins WHERE user_id = :id');
    $s->execute([':id' => $userId]);
    $isAdmin = (bool)$s->fetch();
}

$msg   = '';
$error = '';

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');
        $username  = trim($_POST['username']   ?? '');
        $password  = $_POST['password']        ?? '';
        $bio       = mb_substr(trim($_POST['bio'] ?? ''), 0, 500);
        $certs     = mb_substr(trim($_POST['certifications'] ?? ''), 0, 500);

        if (!$firstName || !$lastName || !$email || !$username || strlen($password) < 6) {
            $error = 'All fields required. Password min 6 chars.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email.';
        } elseif (!preg_match('/^[\w.]{3,30}$/', $username)) {
            $error = 'Username: 3-30 chars, letters/numbers/underscore/dot.';
        } else {
            $s = $db->prepare('SELECT 1 FROM users WHERE email = ? OR username = ?');
            $s->execute([$email, $username]);
            if ($s->fetch()) {
                $error = 'Email or username already taken.';
            } else {
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
                $pf = $_FILES['photo'] ?? null;
                if ($pf && $pf['error'] === UPLOAD_ERR_OK && $pf['size'] <= 5 * 1024 * 1024) {
                    $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($pf['tmp_name']);
                    if (isset($ext[$mime])) {
                        $fn = 'user_' . $newId . '_' . time() . '.' . $ext[$mime];
                        if (move_uploaded_file($pf['tmp_name'], __DIR__ . '/../images/profile_photos/' . $fn)) {
                            $db->prepare('UPDATE users SET profile_photo=? WHERE id=?')
                               ->execute(['../images/profile_photos/' . $fn, $newId]);
                        }
                    }
                }
                header('Location: /pages/trainers.php?msg=Trainer+created.'); exit;
            }
        }

    } elseif ($action === 'update') {
        $targetId  = (int)($_POST['target_id'] ?? 0);
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');
        $bio       = mb_substr(trim($_POST['bio'] ?? ''), 0, 500);
        $certs     = mb_substr(trim($_POST['certifications'] ?? ''), 0, 500);

        if (!$targetId || !$firstName || !$lastName || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid data.';
        } else {
            $s = $db->prepare('SELECT 1 FROM users WHERE email = ? AND id != ?');
            $s->execute([$email, $targetId]);
            if ($s->fetch()) {
                $error = 'Email already in use.';
            } else {
                $db->prepare('UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?')
                   ->execute([$firstName, $lastName, $email, $targetId]);
                $db->prepare('UPDATE trainers SET bio=?, certifications=? WHERE user_id=?')
                   ->execute([$bio ?: null, $certs ?: null, $targetId]);
                $db->prepare('DELETE FROM trainer_specializations WHERE trainer_id=?')->execute([$targetId]);
                foreach (array_map('intval', (array)($_POST['specializations'] ?? [])) as $ctId) {
                    if ($ctId > 0) $db->prepare('INSERT OR IGNORE INTO trainer_specializations (trainer_id, class_type_id) VALUES (?,?)')->execute([$targetId, $ctId]);
                }
                $db->prepare('DELETE FROM trainer_locations WHERE trainer_id=?')->execute([$targetId]);
                foreach (array_map('intval', (array)($_POST['gyms'] ?? [])) as $gId) {
                    if ($gId > 0) $db->prepare('INSERT OR IGNORE INTO trainer_locations (trainer_id, gym_id) VALUES (?,?)')->execute([$targetId, $gId]);
                }
                $pf = $_FILES['photo'] ?? null;
                if ($pf && $pf['error'] === UPLOAD_ERR_OK && $pf['size'] <= 5 * 1024 * 1024) {
                    $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($pf['tmp_name']);
                    if (isset($ext[$mime])) {
                        $fn = 'user_' . $targetId . '_' . time() . '.' . $ext[$mime];
                        if (move_uploaded_file($pf['tmp_name'], __DIR__ . '/../images/profile_photos/' . $fn)) {
                            $db->prepare('UPDATE users SET profile_photo=? WHERE id=?')
                               ->execute(['../images/profile_photos/' . $fn, $targetId]);
                        }
                    }
                }
                header('Location: /pages/trainers.php?msg=Trainer+updated.'); exit;
            }
        }

    } elseif ($action === 'promote') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            $s = $db->prepare('SELECT 1 FROM trainers WHERE user_id = ?');
            $s->execute([$targetId]);
            if ($s->fetch()) {
                $db->prepare('DELETE FROM trainer_specializations WHERE trainer_id=?')->execute([$targetId]);
                $db->prepare('DELETE FROM trainer_locations WHERE trainer_id=?')->execute([$targetId]);
                $db->prepare('DELETE FROM trainers WHERE user_id=?')->execute([$targetId]);
                $db->prepare('INSERT OR IGNORE INTO admins (user_id) VALUES (?)')->execute([$targetId]);
                header('Location: /pages/admin-members.php?msg=Trainer+promoted+to+admin.');
                exit;
            }
        }

    } elseif ($action === 'delete') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            $db->prepare('DELETE FROM users WHERE id=?')->execute([$targetId]);
            header('Location: /pages/trainers.php?msg=Trainer+removed.'); exit;
        }
    }
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

$editTrainer = null;
$editSpecs   = [];
$editGyms    = [];
if ($isAdmin && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $s = $db->prepare('SELECT u.id, u.first_name, u.last_name, u.email, u.username, u.profile_photo, t.bio, t.certifications FROM users u JOIN trainers t ON t.user_id = u.id WHERE u.id = ?');
    $s->execute([$editId]);
    $editTrainer = $s->fetch() ?: null;
    if ($editTrainer) {
        $s = $db->prepare('SELECT class_type_id FROM trainer_specializations WHERE trainer_id=?');
        $s->execute([$editId]);
        $editSpecs = $s->fetchAll(PDO::FETCH_COLUMN);
        $s = $db->prepare('SELECT gym_id FROM trainer_locations WHERE trainer_id=?');
        $s->execute([$editId]);
        $editGyms = $s->fetchAll(PDO::FETCH_COLUMN);
    }
}

$classTypes = $gymList = [];
if ($isAdmin) {
    $classTypes = $db->query("SELECT id, name FROM class_types WHERE name IN ('Pilates', 'Cycling', 'Personal Training') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $gymList    = $db->query('SELECT id, name, city FROM gym_locations ORDER BY city, name')->fetchAll(PDO::FETCH_ASSOC);
}

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
$trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($session->isLoggedIn()) {
    drawDashHeader($session, $db, 'trainers', ['trainers']);
} else {
    drawHeader($session, ['trainers']);
}

drawTrainersPage($session, $trainers, $isAdmin, [
    'classTypes'   => $classTypes,
    'gymList'      => $gymList,
    'editTrainer'  => $editTrainer,
    'editSpecs'    => $editSpecs,
    'editGyms'     => $editGyms,
    'msg'          => $msg,
    'error'        => $error,
]);
drawFooter();
