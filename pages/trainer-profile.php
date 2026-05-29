<?php
declare(strict_types=1);
require_once(__DIR__ . '/../utils/session.php');
$session = new Session();

require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/layout/common.tpl.php');
require_once(__DIR__ . '/../templates/pages/trainer-profile.tpl.php');

$db = getDatabaseConnection();

if (!$session->isLoggedIn()) {
    header('Location: /actions/login.php');
    exit;
}

$currentUserId = (int)$session->getId();
$userId        = (isset($_GET['id']) && (int)$_GET['id'] > 0) ? (int)$_GET['id'] : $currentUserId;
$isOwnProfile  = ($userId === $currentUserId);
$role = null;

foreach (['admins' => 'admin', 'trainers' => 'trainer', 'clients' => 'client'] as $tbl => $r) {
    $s = $db->prepare("SELECT 1 FROM $tbl WHERE user_id = :id");
    $s->execute([':id' => $currentUserId]);
    if ($s->fetch()) {
        $role = $r;
        break;
    }
}


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
    header('Location: /actions/login.php');
    exit;
}


$stmt = $db->prepare(
    'SELECT gl.name, gl.city
     FROM trainer_locations tl
     JOIN gym_locations gl ON gl.id = tl.gym_id
     WHERE tl.trainer_id = :id'
);
$stmt->execute([':id' => $userId]);
$trainerGyms = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $db->prepare(
    "SELECT ct.id, ct.name
     FROM trainer_specializations ts
     JOIN class_types ct ON ct.id = ts.class_type_id
     WHERE ts.trainer_id = :id
       AND ct.name IN ('Pilates', 'Cycling', 'Personal Training')
     ORDER BY ct.name"
);
$stmt->execute([':id' => $userId]);
$specializations = $stmt->fetchAll(PDO::FETCH_ASSOC);


$certifications = array_values(array_filter(array_map('trim', explode("\n", $user['trainer_bio'] !== null ? ($user['certifications'] ?? '') : ($user['certifications'] ?? '')))));

$fullName     = $user['first_name'] . ' ' . $user['last_name'];
$memberSince  = (new DateTime($user['created_at']))->format('F Y');
$profilePhoto = $user['profile_photo'] ?? '../images/profile_pic.webp';
$bio          = $user['trainer_bio'] ?? '';
$homeGyms     = array_map(fn($g) => 'Cubo Gym - ' . $g['city'] . ', ' . $g['name'], $trainerGyms);

$weekOffset = isset($_GET['w']) ? max(-2, min(8, (int)$_GET['w'])) : 0;
$today = new DateTime('today');
$weekMon = new DateTime('monday this week');
$weekMon->modify("{$weekOffset} weeks");

$days = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $weekMon;
    $d->modify("+{$i} days");
    $days[] = $d;
}
$weekSun = $days[6];

$defaultDay = ($weekOffset === 0 && $today >= $weekMon && $today <= $weekSun)
    ? $today->format('Y-m-d')
    : $weekMon->format('Y-m-d');

$stmt = $db->prepare("
    SELECT cl.id, ct.name AS class_name, cl.schedule, cl.duration_min, cl.capacity,
           cl.trainer_id,
           gl.name AS gym_name, gl.city AS gym_city,
           u.first_name AS trainer_first, u.last_name AS trainer_last,
           u.profile_photo AS trainer_photo,
           (SELECT COUNT(*) FROM client_classes cc WHERE cc.class_id = cl.id) AS enrolled,
           ROUND(COALESCE((SELECT AVG(r2.rating) FROM reviews r2
               JOIN classes c2 ON c2.id = r2.class_id
               WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id), 0), 1) AS avg_rating,
           (SELECT COUNT(*) FROM reviews r2
               JOIN classes c2 ON c2.id = r2.class_id
               WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id) AS review_count,
           my_rev.rating  AS my_rating,
           my_rev.comment AS my_comment
    FROM classes cl
    JOIN class_types ct ON ct.id = cl.class_type_id
    LEFT JOIN gym_locations gl ON gl.id = cl.gym_id
    LEFT JOIN trainers t ON t.user_id = cl.trainer_id
    LEFT JOIN users u ON u.id = t.user_id
    LEFT JOIN reviews my_rev ON my_rev.class_id = cl.id AND my_rev.client_id = :uid
    WHERE date(cl.schedule) BETWEEN :start AND :end
      AND cl.trainer_id = :trainer_id
      AND ct.name IN ('Pilates', 'Cycling', 'Personal Training')
    ORDER BY cl.schedule ASC
");
$stmt->execute([
    ':start'      => $weekMon->format('Y-m-d'),
    ':end'        => $weekSun->format('Y-m-d'),
    ':trainer_id' => $userId,
    ':uid'        => ($role === 'client') ? $currentUserId : null,
]);
$allClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$classesByDay = [];
foreach ($allClasses as $cls) {
    $classesByDay[substr($cls['schedule'], 0, 10)][] = $cls;
}

$enrolledIds = [];
if ($role === 'client') {
    $s = $db->prepare('SELECT class_id FROM client_classes WHERE client_id = :id');
    $s->execute([':id' => $currentUserId]);
    $enrolledIds = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

$totalClasses = count($allClasses);
$totalSpots   = array_sum(array_map(fn($c) => max(0, (int)$c['capacity'] - (int)$c['enrolled']), $allClasses));
$weekTypes = array_values(array_unique(array_column($allClasses, 'class_name')));
sort($weekTypes);

$typeColors = [
    'Cycling'                 => '#60a5fa',
    'Pilates'                 => '#f472b6',
    'Personal Training'       => '#34d399',
];

$typeDescriptions = [
    'Cycling'                 => 'High-energy indoor cycling set to motivating music. Builds cardiovascular endurance and lower-body power.',
    'Pilates'                 => 'Low-impact exercise focusing on core strength, posture and controlled movement. Suitable for all fitness levels.',
    'Personal Training'       => 'One-on-one session tailored to your specific goals with dedicated coach guidance and personalised programming.',
];

function typeColor(string $n, array $m): string { return $m[$n] ?? '#888'; }
function timeOfDay(string $schedule): string {
    $h = (int)(new DateTime($schedule))->format('H');
    if ($h >= 6 && $h <= 11) return 'morning';
    if ($h >= 12 && $h <= 16) return 'afternoon';
    return 'evening';
}

$classesForJS = function() use ($allClasses, $typeColors, $typeDescriptions, $enrolledIds) {
    return $allClasses ? array_combine(
        array_column($allClasses, 'id'),
        array_map(function($c) use ($typeColors, $typeDescriptions, $enrolledIds) {
            $color = $typeColors[$c['class_name']] ?? '#888';
            return [
                'id'           => (int)$c['id'],
                'class_name'   => $c['class_name'],
                'color'        => $color,
                'schedule'     => $c['schedule'],
                'duration_min' => (int)$c['duration_min'],
                'gym_name'     => $c['gym_name'],
                'gym_city'     => $c['gym_city'],
                'trainer_id'   => (int)$c['trainer_id'],
                'trainer_first'=> $c['trainer_first'],
                'trainer_last' => $c['trainer_last'],
                'trainer_photo'=> $c['trainer_photo'] ?? '',
                'capacity'     => (int)$c['capacity'],
                'enrolled'     => (int)$c['enrolled'],
                'avg_rating'   => (float)$c['avg_rating'],
                'review_count' => (int)$c['review_count'],
                'description'  => $typeDescriptions[$c['class_name']] ?? '',
                'is_enrolled'  => in_array((int)$c['id'], $enrolledIds, true),
                'is_full'      => ((int)$c['capacity'] - (int)$c['enrolled']) <= 0,
                'my_rating'    => isset($c['my_rating']) ? (int)$c['my_rating'] : null,
                'my_comment'   => $c['my_comment'] ?? null,
            ];
        }, $allClasses)
    ) : [];
};

$buildScheduleHTML = fn(): string => drawTrainerScheduleHTML($days, $classesByDay, $today, $enrolledIds, $typeColors, $typeDescriptions, $role, $allClasses);

if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'weekOffset'     => $weekOffset,
        'weekLabel'      => $weekMon->format('M j') . ' - ' . $weekSun->format('M j, Y'),
        'defaultDay'     => $defaultDay,
        'totalClasses'   => $totalClasses,
        'totalSpots'     => $totalSpots,
        'classes'        => $classesForJS(),
        'filterTypes'    => $weekTypes,
        'filterTrainers' => [$userId => $fullName],
        'html'           => $buildScheduleHTML(),
    ]);
    exit;
}
drawDashHeader($session, $db, 'profile', ['schedule'], 'profile-body');
drawTrainerProfilePage($session, $db, $user, $isOwnProfile, $userId, $role ?? '', $profilePhoto, $fullName, $memberSince, $homeGyms, $specializations, $certifications, $bio, $weekOffset, $weekMon, $weekSun, $totalClasses, $totalSpots, $weekTypes, $classesForJS(), $defaultDay, $buildScheduleHTML);
