<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();

//if (!$session->isLoggedIn()) {
//  header('Location: login.php');
//    exit;
//}

$userId = 3; //user de teste

require_once('../database/connection.db.php');
require_once('../templates/common.tmp.php');

$db = getDatabaseConnection();
//$userId = $session->getId();

// --- Buscar dados atuais ---
$stmt = $db->prepare(
    'SELECT u.username, u.email, u.first_name, u.last_name, u.profile_photo, u.created_at, c.preferred_gym_id, c.archetype_id, c.body_weight, c.height, c.selected_badges,
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

// --- Buscar todos os gyms ---
$stmt = $db->prepare('SELECT id, name, city FROM gym_locations ORDER BY city, name');
$stmt->execute();
$gyms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Buscar todos os archetypes ---
$stmt = $db->prepare('SELECT id, name FROM archetypes ORDER BY name');
$stmt->execute();
$archetypeOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
$validArchetypeIds = array_column($archetypeOptions, 'id');

// --- Processar form ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $archetypeId = $_POST['archetype'] ? (int)$_POST['archetype'] : null;
    $preferredGymId = (int)($_POST['preferred_gym_id'] ?? 0);
    $bodyWeight = (float)($_POST['body_weight'] ?? 0);
    $height = (float)($_POST['height'] ?? 0);
    $selectedBadges = array_map('trim', (array)($_POST['display_badges'] ?? []));
    $selectedBadges = array_values(array_filter($selectedBadges));

    // Validações básicas
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($bodyWeight <= 0 || $height <= 0) {
        $error = 'Body weight and height must be positive numbers.';
    } elseif ($archetypeId !== null && !in_array($archetypeId, $validArchetypeIds, true)) {
        $error = 'Invalid archetype selected.';
    } else {
        // Atualizar users
        $stmt = $db->prepare('UPDATE users SET first_name = :first, last_name = :last, email = :email WHERE id = :id');
        $stmt->execute([':first' => $firstName, ':last' => $lastName, ':email' => $email, ':id' => $userId]);

        // Atualizar clients
        $stmt = $db->prepare('UPDATE clients SET archetype_id = :arch, preferred_gym_id = :gym, body_weight = :weight, height = :height, selected_badges = :selected_badges WHERE user_id = :id');
        $stmt->execute([
            ':arch' => $archetypeId ?: null,
            ':gym' => $preferredGymId ?: null,
            ':weight' => $bodyWeight,
            ':height' => $height,
            ':selected_badges' => implode(',', $selectedBadges),
            ':id' => $userId
        ]);

        // Redirecionar de volta ao perfil
        header('Location: profile.php');
        exit;
    }
}

// --- Valores atuais ---
$fullName = $user['first_name'] . ' ' . $user['last_name'];
$memberSince = (new DateTime($user['created_at']))->format('F Y');
$homeGym = $user['gym_name'] ? 'Cubo Gym - ' . $user['gym_city'] . ', ' . $user['gym_name'] : 'No gym selected';
$profilePhoto = $user['profile_photo'] ?? '../images/profile_pic.webp';
$archetype = $user['archetype'] ?? 'NO ARCHETYPE';
$bodyWeight = $user['body_weight'] ?? '';
$height = $user['height'] ?? '';
$memberTag = 'MEMBER'; // Simplificado

// --- Estatísticas para badges ---
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
        ['code' => 'A_PLUS_STUDENT', 'threshold' => 20, 'icon' => '📚', 'title' => 'A+ Student: 20 classes attended', 'label' => 'A+ Student'],
        ['code' => 'NEWBIE', 'threshold' => 1, 'icon' => '🎓', 'title' => 'Newbie: 1st class attended', 'label' => 'Newbie']
    ],
    'visits' => [
        ['code' => 'CENTURY_CLUB', 'threshold' => 100, 'icon' => '💯', 'title' => 'Century Club: 100 gym visits', 'label' => 'Century Club'],
        ['code' => 'IRON_REGULAR', 'threshold' => 50, 'icon' => '🏋️', 'title' => 'Iron Regular: 50 gym visits', 'label' => 'Iron Regular'],
        ['code' => 'GYM_EXPLORER', 'threshold' => 10, 'icon' => '✨', 'title' => 'Gym Explorer: 10 gym visits', 'label' => 'Gym Explorer']
    ],
    'time' => [
        ['code' => 'TIME_CHAMPION', 'threshold' => 6000, 'icon' => '⏱️', 'title' => 'Time Champion: 100+ hours at the gym', 'label' => '100+ Hours'],
        ['code' => 'GYM_WARRIOR', 'threshold' => 3000, 'icon' => '⏱️', 'title' => 'Gym Warrior: 50+ hours at the gym', 'label' => '50+ Hours'],
        ['code' => 'ENDURANCE_BUILDER', 'threshold' => 1200, 'icon' => '⏱️', 'title' => 'Endurance Builder: 20+ hours at the gym', 'label' => '20+ Hours']
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | Cubo Gym</title>
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Gothic&display=swap" rel="stylesheet">
</head>
<body>

<?php drawHeader($session); ?>

<main class="profile-page">
    <aside class="sidebar-container">
        <section class="profile-card">
            <div class="profile-info">
                <figure class="profile-avatar">
                    <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="User Profile Picture">
                </figure>
                <div class="user-meta">
                    <h2 class="user-name"><?= htmlspecialchars($fullName) ?></h2>
                    <p class="user-handle">@<?= htmlspecialchars($user['username']) ?></p>
                    <span class="member-tag"><?= htmlspecialchars($memberTag) ?></span>
                </div>
            </div>

            </div>
        </section>
    </aside>

    <div class="main-content">
        <?php if (isset($error)): ?>
            <div class="error-message" style="color: red; margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="edit-form">
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
                    <label class="detail-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="detail-input" value="<?= htmlspecialchars($user['email']) ?>" required>
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
                <!-- DISPLAY BADGES: moved below Metrics -->
                <!-- Placeholder: badge picker will be rendered after Metrics -->
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

            <!-- DISPLAY BADGES: editable picker placed after Metrics -->
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

            <div class="profile-actions">
                <button type="submit" class="btn-save-changes">Save Changes</button>
                <a href="profile.php" class="btn-cancel">Cancel</a>
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
        // clicking the label will toggle the hidden checkbox natively
    });
});
</script>

<?php drawFooter(); ?>

</body>
</html>