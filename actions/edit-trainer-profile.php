<?php
declare(strict_types=1);
require_once(__DIR__ . '/../utils/session.php');
$session = new Session();

$userId = 2; // trainer de teste (ana.silva)

require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');

$db = getDatabaseConnection();

// --- Dados do trainer ---
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

// --- All class types (specializations) ---
$stmt = $db->prepare('SELECT id, name FROM class_types ORDER BY name');
$stmt->execute();
$allSpecializations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Current specializations ---
$stmt = $db->prepare('SELECT class_type_id FROM trainer_specializations WHERE trainer_id = :id');
$stmt->execute([':id' => $userId]);
$currentSpecIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

// --- All gyms ---
$stmt = $db->prepare('SELECT id, name, city FROM gym_locations ORDER BY city, name');
$stmt->execute();
$allGyms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Current trainer gyms ---
$stmt = $db->prepare('SELECT gym_id FROM trainer_locations WHERE trainer_id = :id');
$stmt->execute([':id' => $userId]);
$currentGymIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

// --- POST ---
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
    } elseif (!preg_match('/^\w{3,30}$/', $username)) {
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

    // Photo upload
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

        header('Location: trainer-profile.php');
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
<?php drawDashHeader($session, $db, 'profile', [], 'trainer-theme profile-body'); ?>

<main class="profile-page">
    <aside class="sidebar-container">
        <section class="profile-card">
            <div class="profile-info">
                <figure class="profile-avatar profile-avatar--edit" id="avatarWrapper">
                    <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="Trainer Profile Picture" id="avatarPreview">
                    <label class="avatar-upload-overlay" for="profile_photo" title="Change photo">
                        <i class="fa fa-camera"></i>
                    </label>
                </figure>
                <div class="user-meta">
                    <h2 class="user-name"><?= htmlspecialchars($fullName) ?></h2>
                    <p class="user-handle">@<?= htmlspecialchars($user['username']) ?></p>
                    <span class="member-tag trainer-tag">TRAINER</span>
                </div>
            </div>
        </section>
    </aside>

    <div class="main-content">
        <?php if (isset($error)): ?>
            <div class="error-message" style="color:red;margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="edit-form">
            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">

            <!-- Basic info -->
            <div class="profile-details">
                <div class="detail-item">
                    <label class="detail-label" for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="detail-input" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="detail-input" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="detail-input" value="<?= htmlspecialchars($user['username']) ?>" required pattern="\w{3,30}" title="3–30 characters: letters, numbers, underscores">
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="detail-input" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="detail-item detail-item--bio">
                    <label class="detail-label" for="bio">Bio <span class="bio-char-count">(<span id="bioCount"><?= mb_strlen($bio) ?></span>/300)</span></label>
                    <textarea id="bio" name="bio" class="detail-input bio-textarea" maxlength="300" rows="4" placeholder="Tell members about yourself..."><?= htmlspecialchars($bio) ?></textarea>
                </div>
            </div>

            <!-- Specializations -->
            <div class="specializations-section">
                <h3>SPECIALIZATIONS</h3>
                <p class="detail-label">Select all areas you are qualified to teach.</p>
                <div class="specialization-toggle-group">
                    <?php foreach ($allSpecializations as $spec): ?>
                        <label class="spec-toggle <?= in_array((int)$spec['id'], array_map('intval', $currentSpecIds)) ? 'selected' : '' ?>">
                            <input type="checkbox" name="specializations[]" value="<?= $spec['id'] ?>"
                                   <?= in_array((int)$spec['id'], array_map('intval', $currentSpecIds)) ? 'checked' : '' ?>>
                            <span class="spec-name"><?= htmlspecialchars($spec['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Certifications -->
            <div class="certifications-edit-section">
                <h3>CERTIFICATIONS</h3>
                <p class="detail-label">Enter one certification per line.</p>
                <textarea name="certifications" class="detail-input bio-textarea" rows="6" placeholder="e.g. NASM Certified Personal Trainer&#10;ACE Group Fitness Instructor"><?= htmlspecialchars($certifications) ?></textarea>
            </div>

            <!-- Gym locations -->
            <div class="gyms-section">
                <h3>GYM LOCATIONS</h3>
                <p class="detail-label">Select the gyms where you work.</p>
                <div class="specialization-toggle-group">
                    <?php foreach ($allGyms as $gym): ?>
                        <label class="spec-toggle <?= in_array((int)$gym['id'], array_map('intval', $currentGymIds)) ? 'selected' : '' ?>">
                            <input type="checkbox" name="gym_ids[]" value="<?= $gym['id'] ?>"
                                   <?= in_array((int)$gym['id'], array_map('intval', $currentGymIds)) ? 'checked' : '' ?>>
                            <span class="spec-name"><?= htmlspecialchars($gym['city'] . ' - ' . $gym['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Password -->
            <div class="password-section">
                <h3>CHANGE PASSWORD</h3>
                <p class="detail-label">Leave blank to keep your current password.</p>
                <div class="detail-item">
                    <label class="detail-label" for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="detail-input" autocomplete="current-password">
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="detail-input" autocomplete="new-password" minlength="6">
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="detail-input" autocomplete="new-password">
                </div>
            </div>

            <div class="profile-actions">
                <button type="submit" class="btn-save-changes">Save Changes</button>
                <a href="../pages/trainer-profile.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var bioTextarea = document.getElementById('bio');
    var bioCount = document.getElementById('bioCount');
    if (bioTextarea && bioCount) {
        bioTextarea.addEventListener('input', function () {
            bioCount.textContent = bioTextarea.value.length;
        });
    }

    var photoInput = document.getElementById('profile_photo');
    var avatarPreview = document.getElementById('avatarPreview');
    if (photoInput && avatarPreview) {
        photoInput.addEventListener('change', function () {
            var file = photoInput.files[0];
            if (file && file.type.startsWith('image/')) {
                var reader = new FileReader();
                reader.onload = function (e) { avatarPreview.src = e.target.result; };
                reader.readAsDataURL(file);
            }
        });
    }

    document.querySelectorAll('.spec-toggle input[type="checkbox"]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            cb.closest('.spec-toggle').classList.toggle('selected', cb.checked);
        });
    });

    var newPw = document.getElementById('new_password');
    var confirmPw = document.getElementById('confirm_password');
    var currentPw = document.getElementById('current_password');
    if (newPw && confirmPw && currentPw) {
        document.querySelector('.edit-form').addEventListener('submit', function (e) {
            if (newPw.value !== '' && newPw.value !== confirmPw.value) {
                e.preventDefault();
                alert('New passwords do not match.');
            }
            if (newPw.value !== '' && currentPw.value === '') {
                e.preventDefault();
                alert('Please enter your current password to set a new one.');
            }
        });
    }
});
</script>

<?php drawFooter(); ?>
