<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();
require_once('../database/connection.db.php');
require_once('../templates/layout/common.tpl.php');
require_once('../templates/pages/admin-members.tpl.php');

if (!$session->isLoggedIn()) {
    header('Location: /actions/login.php');
    exit;
}

$db = getDatabaseConnection();
$adminId = (int)$session->getId();

$s = $db->prepare('SELECT 1 FROM admins WHERE user_id = :id');
$s->execute([':id' => $adminId]);
if (!$s->fetch()) {
    header('Location: /pages/dashboard.php');
    exit;
}

$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        if (!empty($_POST['_ajax'])) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        http_response_code(403);
        die('Invalid CSRF token.');
    }
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');
        $username  = trim($_POST['username']   ?? '');
        $password  = $_POST['password']        ?? '';

        if (!$firstName || !$lastName || !$email || !$username || strlen($password) < 6) {
            $error = 'All fields are required. Password must be at least 6 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (!preg_match('/^[\w.]{3,30}$/', $username)) {
            $error = 'Username must be 3-30 characters (letters, numbers, underscores, dots).';
        } else {
            $s = $db->prepare('SELECT 1 FROM users WHERE email = ? OR username = ?');
            $s->execute([$email, $username]);
            if ($s->fetch()) {
                $error = 'Email or username is already taken.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare('INSERT INTO users (username, email, password_hash, first_name, last_name) VALUES (?,?,?,?,?)')
                   ->execute([$username, $email, $hash, $firstName, $lastName]);
                $newId = (int)$db->lastInsertId();
                $db->prepare('INSERT INTO clients (user_id) VALUES (?)')->execute([$newId]);
                $plan    = $_POST['gym_plan'] ?? 'none';
                $credits = max(0, (int)($_POST['classes_remaining'] ?? 0));
                if ($plan !== 'none' && $plan !== '') {
                    $start = trim($_POST['gym_start'] ?? '') ?: date('Y-m-d');
                    $end   = trim($_POST['gym_end']   ?? '') ?: null;
                    $db->prepare('INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining) VALUES (?,?,?,?,?)')
                       ->execute([$newId, $plan, $start, $end, $credits]);
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
                header('Location: /pages/admin-members.php?msg=Member+created+successfully.');
                exit;
            }
        }

    } elseif ($action === 'update') {
        $targetId  = (int)($_POST['target_id']  ?? 0);
        $firstName = trim($_POST['first_name']  ?? '');
        $lastName  = trim($_POST['last_name']   ?? '');
        $email     = trim($_POST['email']       ?? '');
        $plan      = $_POST['gym_plan']         ?? '';
        $start     = trim($_POST['gym_start']   ?? '');
        $end       = trim($_POST['gym_end']     ?? '') ?: null;
        $credits   = max(0, (int)($_POST['classes_remaining'] ?? 0));

        if (!$targetId || !$firstName || !$lastName || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid data.';
        } else {
            $s = $db->prepare('SELECT 1 FROM users WHERE email = ? AND id != ?');
            $s->execute([$email, $targetId]);
            if ($s->fetch()) {
                $error = 'That email is already in use.';
            } else {
                $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?')
                   ->execute([$firstName, $lastName, $email, $targetId]);

                if ($plan === 'none' || $plan === '') {
                    $db->prepare('DELETE FROM memberships WHERE client_id = ?')->execute([$targetId]);
                } else {
                    $start = $start ?: date('Y-m-d');
                    $db->prepare(
                        'INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end, classes_remaining)
                         VALUES (?,?,?,?,?)
                         ON CONFLICT(client_id) DO UPDATE SET gym_plan=excluded.gym_plan, gym_start=excluded.gym_start, gym_end=excluded.gym_end, classes_remaining=excluded.classes_remaining'
                    )->execute([$targetId, $plan, $start, $end, $credits]);
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
                header('Location: /pages/admin-members.php?msg=Member+updated.');
                exit;
            }
        }

    } elseif ($action === 'promote') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            $s = $db->prepare('SELECT 1 FROM clients WHERE user_id = ?');
            $s->execute([$targetId]);
            if ($s->fetch()) {
                $db->prepare('DELETE FROM memberships WHERE client_id = ?')->execute([$targetId]);
                $db->prepare('DELETE FROM client_classes WHERE client_id = ?')->execute([$targetId]);
                $db->prepare('DELETE FROM clients WHERE user_id = ?')->execute([$targetId]);
                $db->prepare('INSERT OR IGNORE INTO trainers (user_id) VALUES (?)')->execute([$targetId]);
                header('Location: /pages/trainers.php?msg=Member+promoted+to+trainer.');
                exit;
            }
        }

    } elseif ($action === 'promote_admin') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $isAjax   = !empty($_POST['_ajax']);
        if ($targetId) {
            $s = $db->prepare('SELECT 1 FROM clients WHERE user_id = ?');
            $s->execute([$targetId]);
            if ($s->fetch()) {
                $db->prepare('DELETE FROM memberships WHERE client_id = ?')->execute([$targetId]);
                $db->prepare('DELETE FROM client_classes WHERE client_id = ?')->execute([$targetId]);
                $db->prepare('DELETE FROM clients WHERE user_id = ?')->execute([$targetId]);
                $db->prepare('INSERT OR IGNORE INTO admins (user_id) VALUES (?)')->execute([$targetId]);
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => true]);
                    exit;
                }
                header('Location: /pages/admin-members.php?msg=Member+promoted+to+admin.');
                exit;
            }
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Member not found.']);
            exit;
        }

    } elseif ($action === 'delete') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
            header('Location: /pages/admin-members.php?msg=Member+removed.');
            exit;
        }
    }
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

$members = $db->query(
    'SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.profile_photo, u.created_at,
            m.gym_plan, m.gym_start, m.gym_end, COALESCE(m.classes_remaining, 0) AS classes_remaining
     FROM users u
     JOIN clients c ON c.user_id = u.id
     LEFT JOIN memberships m ON m.client_id = u.id
     ORDER BY u.created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);

drawDashHeader($session, $db, 'admin-members', ['admin-members']);
drawAdminMembersPage($session, $db, $members, $msg, $error);
