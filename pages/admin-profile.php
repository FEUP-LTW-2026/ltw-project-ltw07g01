<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();
require_once('../database/connection.db.php');
require_once('../templates/common.tpl.php');

if (!$session->isLoggedIn()) {
    header('Location: /actions/login.php');
    exit;
}

$db      = getDatabaseConnection();
$adminId = (int)$session->getId();

$s = $db->prepare('SELECT 1 FROM admins WHERE user_id = :id');
$s->execute([':id' => $adminId]);
if (!$s->fetch()) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error = '';
$msg   = '';

$stmt = $db->prepare('SELECT username, email, first_name, last_name, profile_photo, created_at FROM users WHERE id = ?');
$stmt->execute([$adminId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName       = trim($_POST['first_name']      ?? '');
    $lastName        = trim($_POST['last_name']       ?? '');
    $username        = trim($_POST['username']        ?? '');
    $currentPassword = $_POST['current_password']     ?? '';
    $newPassword     = $_POST['new_password']          ?? '';
    $confirmPassword = $_POST['confirm_password']      ?? '';

    if (!$firstName || !$lastName || !$username) {
        $error = 'Name and username are required.';
    } elseif (!preg_match('/^[\w.]{3,30}$/', $username)) {
        $error = 'Username must be 3–30 characters (letters, numbers, underscores, dots).';
    } else {
        $s = $db->prepare('SELECT 1 FROM users WHERE username = ? AND id != ?');
        $s->execute([$username, $adminId]);
        if ($s->fetch()) {
            $error = 'That username is already taken.';
        }
    }

    if (!$error && $newPassword !== '') {
        $row = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $row->execute([$adminId]);
        $hash = $row->fetchColumn();
        if (!password_verify($currentPassword, $hash)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        }
    }

    $newPhotoPath = null;
    if (!$error && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($_FILES['profile_photo']['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            $error = 'Photo must be JPEG, PNG, WebP or GIF.';
        } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
            $error = 'Photo must be under 5 MB.';
        } else {
            $ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
            $filename = 'user_' . $adminId . '_' . time() . '.' . $ext;
            $destDir  = __DIR__ . '/../images/profile_photos/';
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destDir . $filename)) {
                $newPhotoPath = '../images/profile_photos/' . $filename;
            } else {
                $error = 'Could not save the photo. Please try again.';
            }
        }
    }

    if (!$error) {
        $fields = 'first_name=?, last_name=?, username=?';
        $params = [$firstName, $lastName, $username];

        if ($newPhotoPath !== null) {
            $fields .= ', profile_photo=?';
            $params[] = $newPhotoPath;
        }
        if ($newPassword !== '') {
            $fields .= ', password_hash=?';
            $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $params[] = $adminId;
        $db->prepare("UPDATE users SET {$fields} WHERE id=?")->execute($params);

        header('Location: /pages/admin-profile.php?msg=Profile+saved.');
        exit;
    }

    // em erro mantem os valores submetidos
    $user['first_name'] = $firstName;
    $user['last_name']  = $lastName;
    $user['username']   = $username;
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

$profilePhoto = $user['profile_photo'] ?? '../images/profile_pic.webp';
$fullName     = $user['first_name'] . ' ' . $user['last_name'];

drawDashHeader($session, $db, 'profile', [], 'profile-body admin-theme');
?>

<main class="profile-page">
    <aside class="sidebar-container">
        <section class="profile-card">
            <div class="profile-info">
                <figure class="profile-avatar profile-avatar--edit" id="avatarWrapper">
                    <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="Profile photo" id="avatarPreview">
                    <label class="avatar-upload-overlay" for="profile_photo" title="Change photo">
                        <i class="fa fa-camera"></i>
                    </label>
                </figure>
                <div class="user-meta">
                    <h2 class="user-name"><?= htmlspecialchars($fullName) ?></h2>
                    <p class="user-handle">@<?= htmlspecialchars($user['username']) ?></p>
                    <span class="member-tag">ADMIN</span>
                </div>
            </div>
            <div class="user-identity">
                <span class="archetype-tag"><i class="fa fa-user-shield"></i> Administrator</span>
            </div>
        </section>
    </aside>

    <div class="main-content">

        <?php if ($msg): ?>
        <div class="admin-alert admin-alert--ok" style="margin-bottom:1.25rem">
            <i class="fa fa-circle-check"></i> <?= $msg ?>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="admin-alert admin-alert--err" style="margin-bottom:1.25rem">
            <i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="file" id="profile_photo" name="profile_photo"
                   accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">

            <div class="profile-details">
                <div class="detail-item">
                    <label class="detail-label" for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="detail-input" required
                        value="<?= htmlspecialchars($user['first_name']) ?>">
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="detail-input" required
                        value="<?= htmlspecialchars($user['last_name']) ?>">
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="detail-input" required
                        pattern="[\w.]{3,30}" title="3–30 characters: letters, numbers, underscores, dots"
                        value="<?= htmlspecialchars($user['username']) ?>">
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email</span>
                    <p class="detail-value"><?= htmlspecialchars($user['email']) ?></p>
                </div>
            </div>

            <div class="password-section">
                <h3>CHANGE PASSWORD</h3>
                <span class="detail-label">Leave blank to keep your current password.</span>
                <div class="detail-item">
                    <label class="detail-label" for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password"
                           class="detail-input" autocomplete="current-password">
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password"
                           class="detail-input" minlength="6" autocomplete="new-password">
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           class="detail-input" autocomplete="new-password">
                </div>
            </div>

            <div class="profile-actions">
                <button type="submit" class="btn-edit-profile">Save Changes</button>
            </div>
        </form>

    </div>
</main>

<script>
document.getElementById('profile_photo').addEventListener('change', function () {
    var file = this.files[0];
    if (file && file.type.startsWith('image/')) {
        var reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById('avatarPreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

document.querySelector('form').addEventListener('submit', function (e) {
    var np  = document.getElementById('new_password').value;
    var cp  = document.getElementById('confirm_password').value;
    var cur = document.getElementById('current_password').value;
    if (np !== '' && np !== cp) {
        e.preventDefault();
        alert('New passwords do not match.');
    }
    if (np !== '' && cur === '') {
        e.preventDefault();
        alert('Enter your current password to set a new one.');
    }
});
</script>

<?php drawFooter(); ?>
