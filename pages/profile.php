<?php
declare(strict_types=1);
require_once(__DIR__ . '/../utils/session.php');
$session = new Session();

//if (!$session->isLoggedIn()) {
//  header('Location: login.php');
//    exit;
//}

$userId = 3; //user de teste

require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');

$db = getDatabaseConnection();
//$userId = $session->getId();

// --- Utilizador e gym ---
$stmt = $db->prepare(
    'SELECT u.username, u.email, u.first_name, u.last_name, u.profile_photo, u.bio, u.created_at, c.preferred_gym_id, c.archetype_id, c.selected_badges, c.body_weight, c.height,
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

// --- Membership ---
$stmt = $db->prepare(
    'SELECT is_classes_enabled, start_date, end_date
     FROM memberships
     WHERE client_id = :id AND (end_date IS NULL OR end_date > CURRENT_TIMESTAMP)
     ORDER BY start_date DESC
     LIMIT 1'
);
$stmt->execute([':id' => $userId]);
$membership = $stmt->fetch();

// --- Total visitas ---
$stmt = $db->prepare('SELECT COUNT(*) FROM gym_visits WHERE client_id = :id');
$stmt->execute([':id' => $userId]);
$totalVisits = (int)$stmt->fetchColumn();

// --- Classes attended ---
$stmt = $db->prepare('SELECT COUNT(*) FROM client_classes WHERE client_id = :id');
$stmt->execute([':id' => $userId]);
$classesAttended = (int)$stmt->fetchColumn();

// --- Total training time ---
$stmt = $db->prepare(
    'SELECT SUM((julianday(checked_out) - julianday(checked_in)) * 1440) AS total_minutes
     FROM gym_visits
     WHERE client_id = :id AND checked_out IS NOT NULL'
);
$stmt->execute([':id' => $userId]);
$totalGymMinutes = (int)round($stmt->fetchColumn() ?? 0);

$selectedBadgeCodes = array_filter(array_map('trim', explode(',', $user['selected_badges'] ?? '')));

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

$earnedBadgeCount = 0;
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
            $earnedBadgeCount++;
        }
    }

    foreach ($definitions as $definition) {
        if ($value >= $definition['threshold']) {
            $availableBadges[] = $definition;
            break;
        }
    }
}

$selectedBadges = array_filter($availableBadges, function ($badge) use ($selectedBadgeCodes) {
    return in_array($badge['code'], $selectedBadgeCodes, true);
});

// --- Chart range ---
$daysRange = isset($_GET['days']) && in_array((int)$_GET['days'], [7, 30], true)
    ? (int)$_GET['days']
    : 7;
$periodLabel = $daysRange === 30 ? 'Last 30 days' : 'Last 7 days';
$rangeStart = sprintf('-%d days', $daysRange - 1);
$periodStartDate = new DateTime('today');
$periodStartDate->modify($rangeStart);

$stmt = $db->prepare(
    'SELECT checked_in, checked_out
     FROM gym_visits
     WHERE client_id = :id AND checked_in >= DATE("now", :rangeStart)
     ORDER BY checked_in ASC'
);
$stmt->execute([':id' => $userId, ':rangeStart' => $rangeStart]);
$periodVisits = $stmt->fetchAll();

// --- Calcular minutos por dia ---
$minutesByDay = array_fill(0, $daysRange, 0);
$periodMinutes = 0;
$daysLabels = [];
for ($i = 0; $i < $daysRange; $i++) {
    $date = (clone $periodStartDate)->modify("+{$i} days");
    $daysLabels[] = $date->format('d M');
}

foreach ($periodVisits as $visit) {
    if ($visit['checked_out']) {
        $in   = new DateTime($visit['checked_in']);
        $out  = new DateTime($visit['checked_out']);
        $mins = (int)(($out->getTimestamp() - $in->getTimestamp()) / 60);
        $dayIndex = (int)$periodStartDate->diff($in)->days;
        if ($dayIndex >= 0 && $dayIndex < $daysRange) {
            $minutesByDay[$dayIndex] += $mins;
        }
        $periodMinutes += $mins;
    }
}

$maxMinutes  = max($minutesByDay) ?: 1;
$activeDays  = count(array_filter($minutesByDay));
$avgMinutes  = $activeDays > 0 ? (int)($periodMinutes / $activeDays) : 0;

function formatMinutes(int $mins): string {
    if ($mins === 0) return 'Rest';
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
}

$periodTotalFormatted = formatMinutes($periodMinutes);
$totalWorkoutFormatted = formatMinutes($totalGymMinutes);
$avgFormatted   = formatMinutes($avgMinutes);
$fullName       = $user['first_name'] . ' ' . $user['last_name'];
$memberSince    = (new DateTime($user['created_at']))->format('F Y');
$homeGym        = $user['gym_name']
    ? 'Cubo Gym - ' . $user['gym_city'] . ', ' . $user['gym_name']
    : 'No gym selected';
$profilePhoto   = $user['profile_photo'] ?? '../images/profile_pic.webp';
$archetype = $user['archetype'] ?? 'NO ARCHETYPE';
$bio = $user['bio'] ?? '';
$bodyWeight = $user['body_weight'] ?? 'N/A';
$height = $user['height'] ?? 'N/A';
$memberTag      = $membership
    ? ($membership['is_classes_enabled'] ? 'ULTRA MEMBER' : 'MEMBER')
    : 'NO MEMBERSHIP';

// --- Badges ---
$badges = [];

if ($classesAttended >= 20) {
    $badges[] = ['icon' => '<i class="fa fa-book"></i>', 'title' => 'A+ Student: 20 classes attended'];
} elseif ($classesAttended >= 1) {
    $badges[] = ['icon' => '<i class="fa fa-graduation-cap"></i>', 'title' => 'Newbie: 1st class attended'];
}

if ($totalVisits >= 100) {
    $badges[] = ['icon' => '<i class="fa fa-trophy"></i>', 'title' => 'Century Club: 100 gym visits'];
} elseif ($totalVisits >= 50) {
    $badges[] = ['icon' => '<i class="fa fa-dumbbell"></i>', 'title' => 'Iron Regular: 50 gym visits'];
} elseif ($totalVisits >= 10) {
    $badges[] = ['icon' => '<i class="fa fa-compass"></i>', 'title' => 'Gym Explorer: 10 gym visits'];
}

if ($totalGymMinutes >= 6000) {
    $badges[] = ['icon' => '<i class="fa fa-crown"></i>', 'title' => 'Time Champion: 100+ hours at the gym'];
} elseif ($totalGymMinutes >= 3000) {
    $badges[] = ['icon' => '<i class="fa fa-shield-halved"></i>', 'title' => 'Gym Warrior: 50+ hours at the gym'];
} elseif ($totalGymMinutes >= 1200) {
    $badges[] = ['icon' => '<i class="fa fa-bolt"></i>', 'title' => 'Endurance Builder: 20+ hours at the gym'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | Cubo Gym</title>
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

            <div class="user-identity">
                <span class="archetype-tag"><?= htmlspecialchars($archetype) ?></span>
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
                <span class="detail-label">Home Gym</span>
                <p class="detail-value"><?= htmlspecialchars($homeGym) ?></p>
            </div>
        </div>

        <div class="metrics-section">
            <h3>MY METRICS</h3>
            <div class="metrics-grid">
                <div class="metric-card">
                    <span class="metric-label">Body Weight</span>
                    <span class="metric-value"><?= htmlspecialchars((string)$bodyWeight) ?></span>
                </div>
                <div class="metric-card">
                    <span class="metric-label">Height</span>
                    <span class="metric-value"><?= htmlspecialchars((string)$height) ?></span>
                </div>
            </div>
        </div>

        <div class="hall-of-fame">
            <h3>HALL OF FAME</h3>
            <div class="stats-row">
                <div class="stat-box">
                    <span class="stat-number"><?= $totalVisits ?></span>
                    <span class="stat-label">LIFE TIME VISITS</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?= $classesAttended ?></span>
                    <span class="stat-label">CLASSES ATTENDED</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?= $earnedBadgeCount ?></span>
                    <span class="stat-label">EARNED BADGES</span>
                </div>
            </div>

            <div class="badge-container">
                <?php foreach ($selectedBadges as $badge): ?>
                    <span class="badge" title="<?= htmlspecialchars($badge['title']) ?>">
                        <?= $badge['icon'] ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stats-section">
            <header class="stats-header">
                <div class="header-titles">
                    <h3>TIME SPENT TRAINING</h3>
                    <p class="subtitle">Calculated via QR Check-in/out</p>
                </div>
                <form class="stats-filter-form" method="get">
                    <label for="stats-range" class="stats-filter-label">Period</label>
                    <select id="stats-range" name="days" class="stats-filter" onchange="this.form.submit()">
                        <option value="7" <?= $daysRange === 7 ? 'selected' : '' ?>>Last 7 days</option>
                        <option value="30" <?= $daysRange === 30 ? 'selected' : '' ?>>Last 30 days</option>
                    </select>
                </form>
                <div class="chart-badge">
                    <span class="chart-badge-label">Total Gym Time</span>
                    <strong><?= htmlspecialchars($totalWorkoutFormatted) ?></strong>
                </div>
            </header>

            <div class="chart-container">
                <div class="chart">
                    <?php foreach ($minutesByDay as $day => $mins):
                        $height = $mins > 0 ? min(320, max(25, $mins * 2)) : 0;
                        $time   = formatMinutes($mins);
                    ?>
                    <div class="bar-group">
                        <div class="bar" style="height: <?= $height ?>px;" data-time="<?= htmlspecialchars($time) ?>"></div>
                        <span class="day-label"><?= htmlspecialchars($daysLabels[$day]) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="chart-legend">
                    <div class="legend-item">
                        <span class="legend-label"><?= htmlspecialchars($periodLabel) ?> Total: </span>
                        <strong><?= htmlspecialchars($periodTotalFormatted) ?></strong>
                    </div>
                    <div class="legend-item">
                        <span class="legend-label">Daily Avg: </span>
                        <strong><?= htmlspecialchars($avgFormatted) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-actions">
            <a href="edit-profile.php" class="btn-edit-profile">Edit Profile</a>
        </div>
    </div>
</main>

<php drawFooter(); ?>
</body>
</html>