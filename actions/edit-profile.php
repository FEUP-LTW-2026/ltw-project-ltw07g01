<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();

if (!$session->isLoggedIn()) {
  header('Location: login.php');
   exit;
}


require_once('../database/connection.db.php');
require_once('../templates/common.tpl.php');

$db = getDatabaseConnection();
$userId = $session->getId();

$stmt = $db->prepare(
    'SELECT u.username, u.email, u.first_name, u.last_name, u.profile_photo, u.bio, u.created_at, c.preferred_gym_id, c.archetype_id, c.body_weight, c.height, c.selected_badges,
            gl.name AS gym_name, gl.city AS gym_city,
            a.name AS archetype
     FROM users u
     JOIN clients c ON c.user_id = u.id
     LEFT JOIN gym_locations gl ON gl.id = c.preferred_gym_id
     LEFT JOIN archetypes a ON a.id = c.archetype_id
     WHERE u.id = :id'
);
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit;
}

$selectedBadgeCodes = array_filter(array_map('trim', explode(',', $user['selected_badges'] ?? '')));

$stmt = $db->prepare('SELECT id, name, city FROM gym_locations ORDER BY city, name');
$stmt->execute();
$gyms = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare('SELECT id, name FROM archetypes ORDER BY name');
$stmt->execute();
$archetypeOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$validArchetypeIds = array_column($archetypeOptions, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $archetypeId = $_POST['archetype'] ? (int)$_POST['archetype'] : null;
    $preferredGymId = (int)($_POST['preferred_gym_id'] ?? 0);
    $bodyWeight = (float)($_POST['body_weight'] ?? 0);
    $height = (float)($_POST['height'] ?? 0);
    $selectedBadges = array_map('trim', (array)($_POST['display_badges'] ?? []));
    $selectedBadges = array_values(array_filter($selectedBadges));

    $bio = mb_substr(trim($_POST['bio'] ?? ''), 0, 300);
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($firstName) || empty($lastName) || empty($email) || empty($username)) {
        $error = 'First name, last name, email and username are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (!preg_match('/^\w{3,30}$/', $username)) {
        $error = 'Username must be 3–30 characters (letters, numbers, underscores only).';
    } elseif ($bodyWeight <= 0 || $height <= 0) {
        $error = 'Body weight and height must be positive numbers.';
    } elseif ($archetypeId !== null && !in_array($archetypeId, $validArchetypeIds, true)) {
        $error = 'Invalid archetype selected.';
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
        $userFields = 'first_name = :first, last_name = :last, email = :email, username = :username, bio = :bio';
        $userParams = [':first' => $firstName, ':last' => $lastName, ':email' => $email, ':username' => $username, ':bio' => $bio, ':id' => $userId];

        if ($newPhotoPath !== null) {
            $userFields .= ', profile_photo = :photo';
            $userParams[':photo'] = $newPhotoPath;
        }
        if ($newPassword !== '') {
            $userFields .= ', password_hash = :hash';
            $userParams[':hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $stmt = $db->prepare("UPDATE users SET {$userFields} WHERE id = :id");
        $stmt->execute($userParams);

        $stmt = $db->prepare('UPDATE clients SET archetype_id = :arch, preferred_gym_id = :gym, body_weight = :weight, height = :height, selected_badges = :selected_badges WHERE user_id = :id');
        $stmt->execute([
            ':arch' => $archetypeId ?: null,
            ':gym' => $preferredGymId ?: null,
            ':weight' => $bodyWeight,
            ':height' => $height,
            ':selected_badges' => implode(',', $selectedBadges),
            ':id' => $userId
        ]);

        header('Location: /pages/profile.php');
        exit;
    }
}

$fullName = $user['first_name'] . ' ' . $user['last_name'];
$memberSince = (new DateTime($user['created_at']))->format('F Y');
$homeGym = $user['gym_name'] ? 'Cubo Gym - ' . $user['gym_city'] . ', ' . $user['gym_name'] : 'No gym selected';
$profilePhoto = $user['profile_photo'] ?? '../images/profile_pic.webp';
$archetype = $user['archetype'] ?? 'NO ARCHETYPE';
$bio = $user['bio'] ?? '';
$bodyWeight = $user['body_weight'] ?? '';
$height = $user['height'] ?? '';
$memberTag = 'MEMBER';

$stmt = $db->prepare('SELECT COUNT(*) FROM gym_visits WHERE client_id = :id');
$stmt->execute([':id' => $userId]);
$totalVisits = (int)$stmt->fetchColumn();

$stmt = $db->prepare('SELECT COUNT(*) FROM client_classes WHERE client_id = :id');
$stmt->execute([':id' => $userId]);
$classesAttended = (int)$stmt->fetchColumn();

$stmt = $db->prepare(
    'SELECT SUM((julianday(checked_out) - julianday(checked_in)) * 1440) AS total_minutes
     FROM gym_visits
     WHERE client_id = :id AND checked_out IS NOT NULL'
);
$stmt->execute([':id' => $userId]);
$totalGymMinutes = (int)round($stmt->fetchColumn() ?? 0);

$badgeDefinitions = [
    'classes' => [
        ['code' => 'A_PLUS_STUDENT', 'threshold' => 20, 'icon' => '<i class="fa fa-book"></i>', 'title' => 'A+ Student: 20 classes attended', 'label' => 'A+ Student'],
        ['code' => 'NEWBIE', 'threshold' => 1, 'icon' => '<i class="fa fa-graduation-cap"></i>', 'title' => 'Newbie: 1st class attended', 'label' => 'Newbie']
    ],
    'visits' => [
        ['code' => 'CENTURY_CLUB', 'threshold' => 100, 'icon' => '<i class="fa fa-trophy"></i>', 'title' => 'Century Club: 100 gym visits', 'label' => 'Century Club'],
        ['code' => 'IRON_REGULAR', 'threshold' => 50, 'icon' => '<i class="fa fa-dumbbell"></i>', 'title' => 'Iron Regular: 50 gym visits', 'label' => 'Iron Regular'],
        ['code' => 'GYM_EXPLORER', 'threshold' => 10, 'icon' => '<i class="fa fa-compass"></i>', 'title' => 'Gym Explorer: 10 gym visits', 'label' => 'Gym Explorer']
    ],
    'time' => [
        ['code' => 'TIME_CHAMPION', 'threshold' => 6000, 'icon' => '<i class="fa fa-crown"></i>', 'title' => 'Time Champion: 100+ hours at the gym', 'label' => '100+ Hours'],
        ['code' => 'GYM_WARRIOR', 'threshold' => 3000, 'icon' => '<i class="fa fa-shield-halved"></i>', 'title' => 'Gym Warrior: 50+ hours at the gym', 'label' => '50+ Hours'],
        ['code' => 'ENDURANCE_BUILDER', 'threshold' => 1200, 'icon' => '<i class="fa fa-bolt"></i>', 'title' => 'Endurance Builder: 20+ hours at the gym', 'label' => '20+ Hours']
    ]
];

$earnedCount = 0;
$availableBadges = [];
foreach ($badgeDefinitions as $category => $definitions) {
    $value = 0;
    if ($category === 'classes') {
        $value = $classesAttended;
    } elseif ($category === 'visits') {
        $value = $totalVisits;
    } elseif ($category === 'time') {
        $value = $totalGymMinutes;
    }

    foreach ($definitions as $definition) {
        if ($value >= $definition['threshold']) {
            $earnedCount++;
        }
    }

    foreach ($definitions as $definition) {
        if ($value >= $definition['threshold']) {
            $availableBadges[] = $definition;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedBadges = array_map('trim', (array)($_POST['display_badges'] ?? []));
    $selectedBadges = array_values(array_filter($selectedBadges, function ($badgeCode) use ($availableBadges) {
        return in_array($badgeCode, array_column($availableBadges, 'code'), true);
    }));
} else {
    $selectedBadges = $selectedBadgeCodes;
}

$selectedBadges = array_values(array_unique($selectedBadges));
$selectedBadgeCodes = $selectedBadges;
$selectedBadgesDisplay = array_filter($availableBadges, function ($badge) use ($selectedBadgeCodes) {
    return in_array($badge['code'], $selectedBadgeCodes, true);
});

?>
<?php drawDashHeader($session, $db, 'profile', [], 'profile-body'); ?>

<main class="profile-page">
    <aside class="sidebar-container">
        <section class="profile-card">
            <div class="profile-info">
                <figure class="profile-avatar profile-avatar--edit" id="avatarWrapper">
                    <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="User Profile Picture" id="avatarPreview">
                    <label class="avatar-upload-overlay" for="profile_photo" title="Change photo">
                        <i class="fa fa-camera"></i>
                    </label>
                </figure>
                <div class="user-meta">
                    <h2 class="user-name"><?= htmlspecialchars($fullName) ?></h2>
                    <p class="user-handle">@<?= htmlspecialchars($user['username']) ?></p>
                    <span class="member-tag"><?= htmlspecialchars($memberTag) ?></span>
                </div>
            </div>
        </section>
    </aside>

    <div class="main-content">
        <?php if (isset($error)): ?>
            <div class="error-message" style="color: red; margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="edit-form">
            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
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
                    <textarea id="bio" name="bio" class="detail-input bio-textarea" maxlength="300" rows="4" placeholder="Tell the gym about yourself..."><?= htmlspecialchars($bio) ?></textarea>
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="preferred_gym_id">Home Gym</label>
                    <select id="preferred_gym_id" name="preferred_gym_id" class="detail-select">
                        <option value="">No gym selected</option>
                        <?php foreach ($gyms as $gym): ?>
                            <option value="<?= $gym['id'] ?>" <?= $user['preferred_gym_id'] == $gym['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($gym['city'] . ' - ' . $gym['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="archetype">Archetype</label>
                    <select id="archetype" name="archetype" class="detail-select">
                        <option value="">No archetype</option>
                        <?php foreach ($archetypeOptions as $option): ?>
                            <option value="<?= $option['id'] ?>" <?= $user['archetype_id'] == $option['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($option['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="badge-picker">
            </div>

            <div class="metrics-section">
                <h3>METRICS</h3>
                <div class="metrics-grid">
                    <div class="metric-card">
                        <label class="metric-label" for="body_weight">Body Weight (kg)</label>
                        <input type="number" id="body_weight" name="body_weight" class="metric-input" step="0.1" value="<?= htmlspecialchars((string)$bodyWeight) ?>" required>
                    </div>
                    <div class="metric-card">
                        <label class="metric-label" for="height">Height (cm)</label>
                        <input type="number" id="height" name="height" class="metric-input" step="0.1" value="<?= htmlspecialchars((string)$height) ?>" required>
                    </div>
                </div>
            </div>

            <div class="display-badges-section">
                <h3>DISPLAY BADGES</h3>
                <p class="detail-label">Select which earned badges should appear on your profile.</p>
                <div class="badge-toggle-group">
                    <?php if (empty($availableBadges)): ?>
                        <p>No badges are available yet.</p>
                    <?php else: ?>
                        <?php foreach ($availableBadges as $badge): ?>
                            <label class="badge badge-toggle <?= in_array($badge['code'], $selectedBadgeCodes, true) ? 'selected' : '' ?>">
                                <input type="checkbox" name="display_badges[]" value="<?= $badge['code'] ?>" <?= in_array($badge['code'], $selectedBadgeCodes, true) ? 'checked' : '' ?>>
                                <span class="badge-icon"><?= $badge['icon'] ?></span>
                                <span class="badge-name"><?= htmlspecialchars($badge['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

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
                <a href="../pages/profile.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.badge-toggle input[type="checkbox"]').forEach(function (cb) {
        cb.addEventListener('change', function (e) {
            var label = e.target.closest('.badge-toggle');
            if (!label) return;
            if (e.target.checked) {
                label.classList.add('selected');
            } else {
                label.classList.remove('selected');
            }
        });
    });

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
</html>