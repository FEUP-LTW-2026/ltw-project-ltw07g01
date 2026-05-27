<?php
declare(strict_types=1);
require_once(__DIR__ . '/../utils/session.php');
$session = new Session();

if (!$session->isLoggedIn()) {
    header('Location: /actions/login.php');
    exit;
}

require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');
require_once(__DIR__ . '/../templates/edit-trainer-profile.tpl.php');

$db = getDatabaseConnection();
$userId = (int)$session->getId();

$stmt = $db->prepare('SELECT 1 FROM trainers WHERE user_id = :id');
$stmt->execute([':id' => $userId]);
if (!$stmt->fetch()) {
    header('Location: /pages/profile.php');
    exit;
}

$stmt = $db->prepare(
    'SELECT u.username, u.email, u.first_name, u.last_name, u.profile_photo,
            t.bio, t.certifications
     FROM users u
     JOIN trainers t ON t.user_id = u.id
     WHERE u.id = :id'
);
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit;
}

$stmt = $db->prepare('SELECT id, name FROM class_types ORDER BY name');
$stmt->execute();
$allSpecializations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare('SELECT class_type_id FROM trainer_specializations WHERE trainer_id = :id');
$stmt->execute([':id' => $userId]);
$currentSpecIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $db->prepare('SELECT id, name, city FROM gym_locations ORDER BY city, name');
$stmt->execute();
$allGyms = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare('SELECT gym_id FROM trainer_locations WHERE trainer_id = :id');
$stmt->execute([':id' => $userId]);
$currentGymIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName       = trim($_POST['first_name'] ?? '');
    $lastName        = trim($_POST['last_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $username        = trim($_POST['username'] ?? '');
    $bio             = mb_substr(trim($_POST['bio'] ?? ''), 0, 300);
    $certifications  = mb_substr(trim($_POST['certifications'] ?? ''), 0, 2000);
    $selectedSpecs   = array_map('intval', (array)($_POST['specializations'] ?? []));
    $selectedGyms    = array_map('intval', (array)($_POST['gym_ids'] ?? []));
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $validSpecIds = array_column($allSpecializations, 'id');
    $validGymIds  = array_column($allGyms, 'id');
    $selectedSpecs = array_values(array_filter($selectedSpecs, fn($id) => in_array($id, $validSpecIds)));
    $selectedGyms  = array_values(array_filter($selectedGyms,  fn($id) => in_array($id, $validGymIds)));

    if (empty($firstName) || empty($lastName) || empty($email) || empty($username)) {
        $error = 'First name, last name, email and username are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (!preg_match('/^[\w.]{3,30}$/', $username)) {
        $error = 'Username must be 3–30 characters (letters, numbers, underscores only).';
    } else {
        $stmt = $db->prepare('SELECT id FROM users WHERE username = :username AND id != :id');
        $stmt->execute([':username' => $username, ':id' => $userId]);
        if ($stmt->fetch()) {
            $error = 'That username is already taken.';
        }
    }

    if (!isset($error) && $newPassword !== '') {
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if (!password_verify($currentPassword, $row['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        }
    }

    $newPhotoPath = null;
    if (!isset($error) && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['profile_photo']['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            $error = 'Profile photo must be a JPEG, PNG, WebP, or GIF image.';
        } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
            $error = 'Profile photo must be smaller than 5 MB.';
        } else {
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
            $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
            $destDir = __DIR__ . '/../images/profile_photos/';
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destDir . $filename)) {
                $newPhotoPath = '../images/profile_photos/' . $filename;
            } else {
                $error = 'Failed to save the profile photo. Please try again.';
            }
        }
    }

    if (!isset($error)) {
        $userFields = 'first_name = :first, last_name = :last, email = :email, username = :username';
        $userParams = [':first' => $firstName, ':last' => $lastName, ':email' => $email, ':username' => $username, ':id' => $userId];
        if ($newPhotoPath !== null) {
            $userFields .= ', profile_photo = :photo';
            $userParams[':photo'] = $newPhotoPath;
        }
        if ($newPassword !== '') {
            $userFields .= ', password_hash = :hash';
            $userParams[':hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        $db->prepare("UPDATE users SET {$userFields} WHERE id = :id")->execute($userParams);

        $db->prepare('UPDATE trainers SET bio = :bio, certifications = :certs WHERE user_id = :id')
           ->execute([':bio' => $bio, ':certs' => $certifications, ':id' => $userId]);

        $db->prepare('DELETE FROM trainer_specializations WHERE trainer_id = :id')->execute([':id' => $userId]);
        $stmtSpec = $db->prepare('INSERT INTO trainer_specializations (trainer_id, class_type_id) VALUES (:tid, :cid)');
        foreach ($selectedSpecs as $specId) {
            $stmtSpec->execute([':tid' => $userId, ':cid' => $specId]);
        }

        $db->prepare('DELETE FROM trainer_locations WHERE trainer_id = :id')->execute([':id' => $userId]);
        $stmtGym = $db->prepare('INSERT INTO trainer_locations (trainer_id, gym_id) VALUES (:tid, :gid)');
        foreach ($selectedGyms as $gymId) {
            $stmtGym->execute([':tid' => $userId, ':gid' => $gymId]);
        }

        header('Location: /pages/trainer-profile.php?id=' . $userId);
        exit;
    }

    // On error, keep submitted values for re-display
    $currentSpecIds = $selectedSpecs;
    $currentGymIds  = $selectedGyms;
}

$fullName     = $user['first_name'] . ' ' . $user['last_name'];
$profilePhoto = $user['profile_photo'] ?? '../images/profile_pic.webp';
$bio          = $user['bio'] ?? '';
$certifications = $user['certifications'] ?? '';
?>
<?php
drawDashHeader($session, $db, 'profile', [], 'trainer-theme profile-body');
drawEditTrainerProfilePage($session, $db, $user, $userId, $profilePhoto, $fullName, $bio, $certifications, $allSpecializations, $currentSpecIds, $allGyms, $currentGymIds, $error ?? '');
