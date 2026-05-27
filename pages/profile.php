<?php
declare(strict_types=1);
require_once(__DIR__ . '/../utils/session.php');
$session = new Session();

require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');
require_once(__DIR__ . '/../templates/profile.tpl.php');

$db = getDatabaseConnection();
$currentUserId = $session->isLoggedIn() ? (int)$session->getId() : 3; // 3 = test client fallback

if (!$currentUserId) {
    header('Location: /actions/login.php');
    exit;
}

$viewId      = (isset($_GET['id']) && (int)$_GET['id'] > 0) ? (int)$_GET['id'] : $currentUserId;
$isOwnProfile = ($viewId === $currentUserId);


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
$stmt->execute([':id' => $viewId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: dashboard.php');
    exit;
}


$stmt = $db->prepare(
    'SELECT gym_plan, gym_start, gym_end
     FROM memberships
     WHERE client_id = :id AND (gym_end IS NULL OR gym_end > CURRENT_TIMESTAMP)
     ORDER BY gym_start DESC
     LIMIT 1'
);
$stmt->execute([':id' => $viewId]);
$membership = $stmt->fetch();


$stmt = $db->prepare('SELECT COUNT(*) FROM gym_visits WHERE client_id = :id');
$stmt->execute([':id' => $viewId]);
$totalVisits = (int)$stmt->fetchColumn();


$stmt = $db->prepare('SELECT COUNT(*) FROM client_classes WHERE client_id = :id');
$stmt->execute([':id' => $viewId]);
$classesAttended = (int)$stmt->fetchColumn();


$stmt = $db->prepare(
    'SELECT SUM((julianday(checked_out) - julianday(checked_in)) * 1440) AS total_minutes
     FROM gym_visits
     WHERE client_id = :id AND checked_out IS NOT NULL'
);
$stmt->execute([':id' => $viewId]);
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
$stmt->execute([':id' => $viewId, ':rangeStart' => $rangeStart]);
$periodVisits = $stmt->fetchAll();


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

if (!empty($_GET['ajax']) && $_GET['ajax'] === 'chart') {
    header('Content-Type: application/json');
    echo json_encode([
        'minutesByDay' => $minutesByDay,
        'labels'       => $daysLabels,
        'times'        => array_map('formatMinutes', $minutesByDay),
        'periodTotal'  => $periodTotalFormatted,
        'avg'          => $avgFormatted,
        'periodLabel'  => $periodLabel,
    ]);
    exit;
}
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
    ? ($membership['gym_plan'] === 'ultra' ? 'ULTRA MEMBER' : 'MEMBER')
    : 'NO MEMBERSHIP';


drawDashHeader($session, $db, 'profile', [], 'profile-body');
drawProfilePage($session, $db, $user, $isOwnProfile, $viewId, $currentUserId, $profilePhoto, $fullName, $memberSince, $homeGym, $archetype, (string)$bio, (string)$bodyWeight, (string)$height, $memberTag, $totalVisits, $classesAttended, $earnedBadgeCount, array_values($selectedBadges), $daysRange, $periodLabel, $minutesByDay, $daysLabels, $periodTotalFormatted, $totalWorkoutFormatted, $avgFormatted);
