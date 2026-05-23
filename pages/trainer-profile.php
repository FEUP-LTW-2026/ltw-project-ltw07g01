<?php
declare(strict_types=1);
require_once(__DIR__ . '/../utils/session.php');
$session = new Session();

require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');

$db = getDatabaseConnection();

if (!$session->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int)$session->getId();
$userId        = (isset($_GET['id']) && (int)$_GET['id'] > 0) ? (int)$_GET['id'] : $currentUserId;
$isOwnProfile  = ($userId === $currentUserId);

// --- Dados do trainer ---
$stmt = $db->prepare(
    'SELECT u.username, u.email, u.first_name, u.last_name, u.profile_photo, u.bio AS user_bio, u.created_at,
            t.bio AS trainer_bio, t.certifications
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

// --- Gyms do trainer ---
$stmt = $db->prepare(
    'SELECT gl.name, gl.city
     FROM trainer_locations tl
     JOIN gym_locations gl ON gl.id = tl.gym_id
     WHERE tl.trainer_id = :id'
);
$stmt->execute([':id' => $userId]);
$trainerGyms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Specializations ---
$stmt = $db->prepare(
    'SELECT ct.id, ct.name
     FROM trainer_specializations ts
     JOIN class_types ct ON ct.id = ts.class_type_id
     WHERE ts.trainer_id = :id
     ORDER BY ct.name'
);
$stmt->execute([':id' => $userId]);
$specializations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Certifications (newline-separated) ---
$certifications = array_values(array_filter(array_map('trim', explode("\n", $user['trainer_bio'] !== null ? ($user['certifications'] ?? '') : ($user['certifications'] ?? '')))));

$fullName     = $user['first_name'] . ' ' . $user['last_name'];
$memberSince  = (new DateTime($user['created_at']))->format('F Y');
$profilePhoto = $user['profile_photo'] ?? '../images/profile_pic.webp';
$bio          = $user['trainer_bio'] ?? '';
$homeGyms     = array_map(fn($g) => 'Cubo Gym - ' . $g['city'] . ', ' . $g['name'], $trainerGyms);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($fullName) ?> | Cubo Gym Trainer</title>
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Gothic&display=swap" rel="stylesheet">
</head>
<body class="trainer-theme profile-body">

<?php drawDashNavbar($session, $db, 'profile', false); ?>

<main class="profile-page">
    <aside class="sidebar-container">
        <section class="profile-card">
            <div class="profile-info">
                <figure class="profile-avatar">
                    <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="Trainer Profile Picture">
                </figure>
                <div class="user-meta">
                    <h2 class="user-name"><?= htmlspecialchars($fullName) ?></h2>
                    <p class="user-handle">@<?= htmlspecialchars($user['username']) ?></p>
                    <span class="member-tag trainer-tag">TRAINER</span>
                </div>
            </div>

            <div class="user-identity">
                <div class="specializations-list">
                    <?php if (empty($specializations)): ?>
                        <span class="specialization-tag specialization-tag--empty">No specializations yet</span>
                    <?php else: ?>
                        <?php foreach ($specializations as $spec): ?>
                            <span class="specialization-tag"><?= htmlspecialchars($spec['name']) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if ($bio): ?>
                    <p class="user-bio"><?= nl2br(htmlspecialchars($bio)) ?></p>
                <?php else: ?>
                    <p class="user-bio user-bio--empty">No bio yet.</p>
                <?php endif; ?>
            </div>
        </section>
    </aside>

    <div class="main-content">
        <div class="profile-details">
            <div class="detail-item">
                <span class="detail-label">Full Name</span>
                <p class="detail-value"><?= htmlspecialchars($fullName) ?></p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email Address</span>
                <p class="detail-value"><?= htmlspecialchars($user['email']) ?></p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Member Since</span>
                <p class="detail-value"><?= htmlspecialchars($memberSince) ?></p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Gym Location<?= count($homeGyms) > 1 ? 's' : '' ?></span>
                <p class="detail-value">
                    <?= $homeGyms ? htmlspecialchars(implode(' · ', $homeGyms)) : 'No gym assigned' ?>
                </p>
            </div>
        </div>

        <?php if (!empty($certifications)): ?>
        <div class="certifications-section">
            <h3>CERTIFICATIONS</h3>
            <div class="certifications-grid">
                <?php foreach ($certifications as $cert): ?>
                    <div class="certification-card">
                        <span class="cert-icon"><i class="fa fa-certificate"></i></span>
                        <span class="cert-name"><?= htmlspecialchars($cert) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="certifications-section">
            <h3>CERTIFICATIONS</h3>
            <p class="no-certifications">No certifications listed yet.</p>
        </div>
        <?php endif; ?>

        <?php if ($isOwnProfile): ?>
        <div class="profile-actions">
            <a href="edit-trainer-profile.php" class="btn-edit-profile">Edit Profile</a>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php drawFooter(); ?>
</body>
</html>
