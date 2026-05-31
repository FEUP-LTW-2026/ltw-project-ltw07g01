<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();

if (!$session->isLoggedIn()) {
    header('Location: /actions/login.php');
    exit;
 }

require_once('../database/connection.db.php');
require_once(__DIR__ . '/../templates/layout/common.tpl.php');
require_once(__DIR__ . '/../templates/pages/dashboard.tpl.php');

$db = getDatabaseConnection();

function fetchClassesForSC(PDO $db, array $classIds, array $typeColors): array {
    if (empty($classIds)) return [];
    $placeholders = implode(',', array_fill(0, count($classIds), '?'));
    $s = $db->prepare("
        SELECT cl.id, ct.name AS class_name, cl.schedule, cl.duration_min, cl.capacity, cl.photo,
               cl.trainer_id, gl.name AS gym_name, gl.city AS gym_city,
               u.first_name AS trainer_first, u.last_name AS trainer_last, u.profile_photo AS trainer_photo,
               (SELECT COUNT(*) FROM client_classes cc WHERE cc.class_id = cl.id) AS enrolled,
               ROUND(COALESCE((SELECT AVG(r2.rating) FROM reviews r2 JOIN classes c2 ON c2.id=r2.class_id
                   WHERE c2.trainer_id=cl.trainer_id AND c2.class_type_id=cl.class_type_id),0),1) AS avg_rating,
               (SELECT COUNT(*) FROM reviews r2 JOIN classes c2 ON c2.id=r2.class_id
                   WHERE c2.trainer_id=cl.trainer_id AND c2.class_type_id=cl.class_type_id) AS review_count
        FROM classes cl
        JOIN class_types ct ON ct.id=cl.class_type_id
        LEFT JOIN gym_locations gl ON gl.id=cl.gym_id
        LEFT JOIN users u ON u.id=cl.trainer_id
        WHERE cl.id IN ($placeholders)");
    $s->execute(array_values($classIds));
    $result = [];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $result[(int)$c['id']] = [
            'id'           => (int)$c['id'],
            'class_name'   => $c['class_name'],
            'color'        => $typeColors[$c['class_name']] ?? '#888',
            'schedule'     => $c['schedule'],
            'duration_min' => (int)$c['duration_min'],
            'gym_name'     => $c['gym_name'] ?? '',
            'gym_city'     => $c['gym_city'] ?? '',
            'trainer_id'   => (int)$c['trainer_id'],
            'trainer_first'=> $c['trainer_first'] ?? '',
            'trainer_last' => $c['trainer_last'] ?? '',
            'trainer_photo'=> $c['trainer_photo'] ?? '',
            'capacity'     => (int)$c['capacity'],
            'enrolled'     => (int)$c['enrolled'],
            'avg_rating'   => (float)$c['avg_rating'],
            'review_count' => (int)$c['review_count'],
            'description'  => '',
            'photo'        => $c['photo'] ?? '',
            'is_enrolled'  => false,
            'is_full'      => ((int)$c['capacity'] - (int)$c['enrolled']) <= 0,
            'my_rating'    => null,
            'my_comment'   => null,
        ];
    }
    return $result;
}

$userId = $session->getId();


$role = null;

$stmt = $db->prepare('SELECT 1 FROM admins WHERE user_id = :id');
$stmt->execute([':id' => $userId]);
if ($stmt->fetch()) $role = 'admin';

if (!$role) {
    $stmt = $db->prepare('SELECT 1 FROM trainers WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    if ($stmt->fetch()) $role = 'trainer';
}

if (!$role) {
    $stmt = $db->prepare('SELECT 1 FROM clients WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    if ($stmt->fetch()) $role = 'client';
}

if (!$role) {
    header('Location: /actions/login.php');
    exit;
}

$stmt = $db->prepare('SELECT username, first_name, last_name, profile_photo FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();
$profilePhoto = $user['profile_photo'] ?? '../images/profile_pic.webp';
$fullName     = $user['first_name'] . ' ' . $user['last_name'];
$profileUrl   = ($role === 'trainer') ? 'trainer-profile.php?id=' . $userId : 'profile.php';

if (!empty($_GET['ajax']) && isset($_GET['class_id']) && $role === 'trainer') {
    $classId = (int)$_GET['class_id'];
    $s = $db->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.username, u.profile_photo
         FROM client_classes cc
         JOIN users u ON u.id = cc.client_id
         WHERE cc.class_id = :cid
         ORDER BY u.first_name, u.last_name'
    );
    $s->execute([':cid' => $classId]);
    $students = $s->fetchAll(PDO::FETCH_ASSOC);

    $s = $db->prepare(
        'SELECT ct.name AS class_name, cl.schedule, cl.capacity,
                (SELECT COUNT(*) FROM client_classes WHERE class_id = cl.id) AS enrolled
         FROM classes cl
         JOIN class_types ct ON ct.id = cl.class_type_id
         WHERE cl.id = :cid AND cl.trainer_id = :tid'
    );
    $s->execute([':cid' => $classId, ':tid' => $userId]);
    $classInfo = $s->fetch();

    header('Content-Type: application/json');
    if (!$classInfo) { echo json_encode(['ok' => false]); exit; }
    echo json_encode(['ok' => true, 'class' => $classInfo, 'students' => $students]);
    exit;
}

if ($role === 'client') {

    $stmt = $db->prepare(
        'SELECT c.body_weight, c.height, c.archetype_id, c.selected_badges,
                c.preferred_gym_id, gl.name AS gym_name, gl.city AS gym_city,
                a.name AS archetype
         FROM clients c
         LEFT JOIN gym_locations gl ON gl.id = c.preferred_gym_id
         LEFT JOIN archetypes a ON a.id = c.archetype_id
         WHERE c.user_id = :id'
    );
    $stmt->execute([':id' => $userId]);
    $clientData = $stmt->fetch();

    $stmt = $db->prepare('SELECT gym_plan, COALESCE(SUM(classes_remaining), 0) AS total_credits FROM memberships WHERE client_id = :id AND (gym_end IS NULL OR gym_end > CURRENT_TIMESTAMP) LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $membershipRow = $stmt->fetch();
    $classCredits = (int)($membershipRow['total_credits'] ?? 0);
    $memberTag = match($membershipRow['gym_plan'] ?? null) {
        'ultra' => 'ULTRA MEMBER',
        'pro'   => 'PRO MEMBER',
        'basic' => 'BASIC MEMBER',
        default => 'NO MEMBERSHIP',
    };

    $stmt = $db->prepare(
        'SELECT cl.id, ct.name AS class_name, cl.schedule, cl.duration_min, cl.capacity,
                gl.name AS gym_name, gl.city AS gym_city,
                cl.trainer_id,
                u.first_name AS trainer_first, u.last_name AS trainer_last,
                u.profile_photo AS trainer_photo,
                (SELECT COUNT(*) FROM client_classes cc2 WHERE cc2.class_id = cl.id) AS enrolled,
                ROUND(COALESCE((SELECT AVG(r.rating) FROM reviews r
                    JOIN classes c2 ON c2.id = r.class_id
                    WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id), 0), 1) AS avg_rating,
                (SELECT COUNT(*) FROM reviews r
                    JOIN classes c2 ON c2.id = r.class_id
                    WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id) AS review_count,
                my_rev.rating  AS my_rating,
                my_rev.comment AS my_comment
         FROM client_classes cc
         JOIN classes cl ON cl.id = cc.class_id
         JOIN class_types ct ON ct.id = cl.class_type_id
         LEFT JOIN gym_locations gl ON gl.id = cl.gym_id
         LEFT JOIN users u ON u.id = cl.trainer_id
         LEFT JOIN reviews my_rev ON my_rev.class_id = cl.id AND my_rev.client_id = :uid
         WHERE cc.client_id = :id AND cl.schedule > datetime(\'now\')
         ORDER BY cl.schedule ASC
         LIMIT 5'
    );
    $stmt->execute([':id' => $userId, ':uid' => $userId]);
    $upcomingClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare(
        'SELECT cl.id, ct.name AS class_name, cl.schedule, cl.duration_min, cl.capacity,
                gl.name AS gym_name, gl.city AS gym_city,
                cl.trainer_id,
                u.first_name AS trainer_first, u.last_name AS trainer_last,
                u.profile_photo AS trainer_photo,
                (SELECT COUNT(*) FROM client_classes cc2 WHERE cc2.class_id = cl.id) AS enrolled,
                ROUND(COALESCE((SELECT AVG(r.rating) FROM reviews r
                    JOIN classes c2 ON c2.id = r.class_id
                    WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id), 0), 1) AS avg_rating,
                (SELECT COUNT(*) FROM reviews r
                    JOIN classes c2 ON c2.id = r.class_id
                    WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id) AS review_count,
                my_rev.rating  AS my_rating,
                my_rev.comment AS my_comment
         FROM client_classes cc
         JOIN classes cl ON cl.id = cc.class_id
         JOIN class_types ct ON ct.id = cl.class_type_id
         LEFT JOIN gym_locations gl ON gl.id = cl.gym_id
         LEFT JOIN users u ON u.id = cl.trainer_id
         LEFT JOIN reviews my_rev ON my_rev.class_id = cl.id AND my_rev.client_id = :uid
         WHERE cc.client_id = :id AND cl.schedule <= datetime(\'now\')
         ORDER BY cl.schedule DESC
         LIMIT 5'
    );
    $stmt->execute([':id' => $userId, ':uid' => $userId]);
    $recentClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare(
        'SELECT gv.checked_in, gv.checked_out, gl.name AS gym_name, gl.city AS gym_city
         FROM gym_visits gv
         LEFT JOIN gym_locations gl ON gl.id = gv.gym_id
         WHERE gv.client_id = :id
         ORDER BY gv.checked_in DESC
         LIMIT 5'
    );
    $stmt->execute([':id' => $userId]);
    $recentVisits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare('SELECT COUNT(*) FROM gym_visits WHERE client_id = :id');
    $stmt->execute([':id' => $userId]);
    $totalVisits = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM client_classes WHERE client_id = :id');
    $stmt->execute([':id' => $userId]);
    $totalClasses = (int)$stmt->fetchColumn();

    $stmt = $db->prepare(
        'SELECT COALESCE(SUM((julianday(checked_out) - julianday(checked_in)) * 1440), 0)
         FROM gym_visits WHERE client_id = :id AND checked_out IS NOT NULL'
    );
    $stmt->execute([':id' => $userId]);
    $totalMinutes = (int)round((float)$stmt->fetchColumn());
    $totalHours   = round($totalMinutes / 60, 1);

    $selectedBadgeCodes = array_filter(array_map('trim', explode(',', $clientData['selected_badges'] ?? '')));
    $badgeCategories = [
        'classes' => [
            ['code' => 'A_PLUS_STUDENT', 'threshold' => 20, 'value' => $totalClasses, 'icon' => '<i class="fa fa-book"></i>',          'label' => 'A+ Student'],
            ['code' => 'NEWBIE',          'threshold' => 1,  'value' => $totalClasses, 'icon' => '<i class="fa fa-graduation-cap"></i>', 'label' => 'Newbie'],
        ],
        'visits' => [
            ['code' => 'CENTURY_CLUB',  'threshold' => 100, 'value' => $totalVisits, 'icon' => '<i class="fa fa-trophy"></i>',        'label' => 'Century Club'],
            ['code' => 'IRON_REGULAR',  'threshold' => 50,  'value' => $totalVisits, 'icon' => '<i class="fa fa-dumbbell"></i>',      'label' => 'Iron Regular'],
            ['code' => 'GYM_EXPLORER',  'threshold' => 10,  'value' => $totalVisits, 'icon' => '<i class="fa fa-compass"></i>',       'label' => 'Gym Explorer'],
        ],
        'time' => [
            ['code' => 'TIME_CHAMPION',      'threshold' => 6000, 'value' => $totalMinutes, 'icon' => '<i class="fa fa-crown"></i>',         'label' => '100+ Hours'],
            ['code' => 'GYM_WARRIOR',        'threshold' => 3000, 'value' => $totalMinutes, 'icon' => '<i class="fa fa-shield-halved"></i>', 'label' => '50+ Hours'],
            ['code' => 'ENDURANCE_BUILDER',  'threshold' => 1200, 'value' => $totalMinutes, 'icon' => '<i class="fa fa-bolt"></i>',          'label' => '20+ Hours'],
        ],
    ];
    $earnedBadges = [];
    $earnedBadgeCount = 0;
    foreach ($badgeCategories as $defs) {
        foreach ($defs as $b) {
            if ($b['value'] >= $b['threshold']) {
                $earnedBadgeCount++;
            }
        }
        foreach ($defs as $b) {
            if ($b['value'] >= $b['threshold']) {
                $earnedBadges[] = $b;
                break;
            }
        }
    }
    $displayBadges = array_filter($earnedBadges, fn($b) => in_array($b['code'], $selectedBadgeCodes, true));
}

if ($role === 'trainer') {

    $stmt = $db->prepare(
        'SELECT cl.id, ct.name AS class_name, cl.schedule, cl.duration_min, cl.capacity,
                gl.name AS gym_name, gl.city AS gym_city,
                cl.trainer_id,
                u.first_name AS trainer_first, u.last_name AS trainer_last,
                u.profile_photo AS trainer_photo,
                (SELECT COUNT(*) FROM client_classes cc WHERE cc.class_id = cl.id) AS enrolled,
                ROUND(COALESCE((SELECT AVG(r.rating) FROM reviews r
                    JOIN classes c2 ON c2.id = r.class_id
                    WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id), 0), 1) AS avg_rating,
                (SELECT COUNT(*) FROM reviews r
                    JOIN classes c2 ON c2.id = r.class_id
                    WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id) AS review_count
         FROM classes cl
         JOIN class_types ct ON ct.id = cl.class_type_id
         LEFT JOIN gym_locations gl ON gl.id = cl.gym_id
         LEFT JOIN users u ON u.id = cl.trainer_id
         WHERE cl.trainer_id = :id AND cl.schedule > datetime(\'now\')
         ORDER BY cl.schedule ASC
         LIMIT 6'
    );
    $stmt->execute([':id' => $userId]);
    $trainerClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare(
        'SELECT COUNT(DISTINCT cc.client_id)
         FROM client_classes cc
         JOIN classes cl ON cl.id = cc.class_id
         WHERE cl.trainer_id = :id'
    );
    $stmt->execute([':id' => $userId]);
    $totalStudents = (int)$stmt->fetchColumn();

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM classes WHERE trainer_id = :id AND schedule < datetime(\'now\')'
    );
    $stmt->execute([':id' => $userId]);
    $classesTaught = (int)$stmt->fetchColumn();

    $stmt = $db->prepare(
        'SELECT ROUND(AVG(r.rating), 1)
         FROM reviews r
         JOIN classes cl ON cl.id = r.class_id
         WHERE cl.trainer_id = :id'
    );
    $stmt->execute([':id' => $userId]);
    $avgRating = $stmt->fetchColumn() ?? '—';

    $stmt = $db->prepare(
        'SELECT DISTINCT u.id AS user_id, u.first_name, u.last_name, u.username, u.profile_photo,
                cc.enrolled_at
         FROM client_classes cc
         JOIN clients c ON c.user_id = cc.client_id
         JOIN users u ON u.id = c.user_id
         JOIN classes cl ON cl.id = cc.class_id
         WHERE cl.trainer_id = :id
         ORDER BY cc.enrolled_at DESC
         LIMIT 6'
    );
    $stmt->execute([':id' => $userId]);
    $recentStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare(
        'SELECT ct.name FROM trainer_specializations ts
         JOIN class_types ct ON ct.id = ts.class_type_id
         WHERE ts.trainer_id = :id'
    );
    $stmt->execute([':id' => $userId]);
    $specializations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $db->prepare(
        'SELECT r.rating, r.comment, r.class_id, u.username, ct.name AS class_name
         FROM reviews r
         JOIN users u ON u.id = r.client_id
         JOIN classes cl ON cl.id = r.class_id
         JOIN class_types ct ON ct.id = cl.class_type_id
         WHERE cl.trainer_id = :id
         ORDER BY r.created_at DESC LIMIT 8'
    );
    $stmt->execute([':id' => $userId]);
    $trainerReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


if ($role === 'admin') {
    $totalMembers     = (int)$db->query('SELECT COUNT(*) FROM clients')->fetchColumn();
    $totalTrainers    = (int)$db->query('SELECT COUNT(*) FROM trainers')->fetchColumn();
    $totalGyms        = (int)$db->query('SELECT COUNT(*) FROM gym_locations')->fetchColumn();
    $totalEquip       = (int)$db->query('SELECT COUNT(*) FROM equipment')->fetchColumn();
    $unavailableEquip = (int)$db->query('SELECT COUNT(*) FROM equipment WHERE is_available = 0')->fetchColumn();
    $upcomingClassesCount = (int)$db->query("SELECT COUNT(*) FROM classes WHERE schedule > datetime('now')")->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM gym_visits WHERE checked_in >= datetime('now', '-7 days')");
    $stmt->execute();
    $visitsThisWeek = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM memberships WHERE gym_start >= datetime('now', '-30 days')");
    $stmt->execute();
    $newMemberships = (int)$stmt->fetchColumn();

    $popularClasses = $db->query(
        'SELECT ct.name, COUNT(cc.client_id) AS enrollments
         FROM client_classes cc
         JOIN classes cl ON cl.id = cc.class_id
         JOIN class_types ct ON ct.id = cl.class_type_id
         GROUP BY ct.name ORDER BY enrollments DESC LIMIT 5'
    )->fetchAll(PDO::FETCH_ASSOC);

    $recentReviews = $db->query(
        'SELECT r.rating, r.comment, r.class_id, u.username, ct.name AS class_name
         FROM reviews r
         JOIN users u ON u.id = r.client_id
         JOIN classes cl ON cl.id = r.class_id
         JOIN class_types ct ON ct.id = cl.class_type_id
         ORDER BY r.created_at DESC LIMIT 8'
    )->fetchAll(PDO::FETCH_ASSOC);

    $gymStats = $db->query(
        'SELECT gl.name, gl.city, COUNT(DISTINCT gv.id) AS visit_count
         FROM gym_locations gl
         LEFT JOIN gym_visits gv ON gv.gym_id = gl.id
         GROUP BY gl.id ORDER BY visit_count DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $nextClasses = $db->query(
        "SELECT c.schedule, c.capacity, ct.name AS class_type,
                gl.name AS gym_name, gl.city AS gym_city,
                u.first_name AS tr_first, u.last_name AS tr_last,
                (SELECT COUNT(*) FROM client_classes cc WHERE cc.class_id = c.id) AS enrolled
         FROM classes c
         LEFT JOIN class_types ct ON ct.id = c.class_type_id
         LEFT JOIN gym_locations gl ON gl.id = c.gym_id
         LEFT JOIN users u ON u.id = c.trainer_id
         WHERE c.schedule > datetime('now') ORDER BY c.schedule ASC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);
}

$dashTypeColors = [
    'Yoga' => '#a78bfa', 'Cycling' => '#60a5fa', 'Pilates' => '#f472b6',
    'HIIT' => '#fb923c', 'Personal Training' => '#34d399', 'Spin' => '#22d3ee',
    'Strength & Conditioning' => '#fbbf24', 'Zumba' => '#a3e635', 'Boxing' => '#f87171',
];
$dashClassesForSC = [];
if ($role === 'client') {
    foreach (array_merge($upcomingClasses ?? [], $recentClasses ?? []) as $c) {
        $dashClassesForSC[(int)$c['id']] = [
            'id'           => (int)$c['id'],
            'class_name'   => $c['class_name'],
            'color'        => $dashTypeColors[$c['class_name']] ?? '#888',
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
            'description'  => '',
            'is_enrolled'  => true,
            'is_full'      => ((int)$c['capacity'] - (int)$c['enrolled']) <= 0,
            'my_rating'    => isset($c['my_rating']) && $c['my_rating'] !== null ? (int)$c['my_rating'] : null,
            'my_comment'   => $c['my_comment'] ?? null,
        ];
    }
} elseif ($role === 'trainer') {
    foreach ($trainerClasses ?? [] as $c) {
        $dashClassesForSC[(int)$c['id']] = [
            'id'           => (int)$c['id'],
            'class_name'   => $c['class_name'],
            'color'        => $dashTypeColors[$c['class_name']] ?? '#888',
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
            'description'  => '',
            'is_enrolled'  => false,
            'is_full'      => ((int)$c['capacity'] - (int)$c['enrolled']) <= 0,
            'my_rating'    => null,
            'my_comment'   => null,
        ];
    }
    // also include past classes referenced in reviews that aren't yet in the map
    $missingIds = array_diff(
        array_unique(array_map('intval', array_column($trainerReviews ?? [], 'class_id'))),
        array_keys($dashClassesForSC)
    );
    $dashClassesForSC += fetchClassesForSC($db, $missingIds, $dashTypeColors);
} elseif ($role === 'admin') {
    $reviewIds = array_unique(array_map('intval', array_column($recentReviews ?? [], 'class_id')));
    $dashClassesForSC = fetchClassesForSC($db, $reviewIds, $dashTypeColors);
}

drawDashHeader($session, $db, 'home', ['schedule']);
drawDashboardPage(
    $session,
    $db,
    $role,
    $user,
    $clientData ?? [],
    $upcomingClasses ?? [],
    $recentClasses ?? [],
    $recentVisits ?? [],
    $totalVisits ?? 0,
    $totalClasses ?? 0,
    $totalHours ?? 0.0,
    $earnedBadgeCount ?? 0,
    $displayBadges ?? [],
    $dashClassesForSC,
    $trainerClasses ?? [],
    $totalStudents ?? 0,
    $classesTaught ?? 0,
    (string)($avgRating ?? '—'),
    $recentStudents ?? [],
    $trainerReviews ?? [],
    $specializations ?? [],
    $totalMembers ?? 0,
    $totalTrainers ?? 0,
    $totalGyms ?? 0,
    $unavailableEquip ?? 0,
    $upcomingClassesCount ?? 0,
    $visitsThisWeek ?? 0,
    $newMemberships ?? 0,
    $popularClasses ?? [],
    $recentReviews ?? [],
    $gymStats ?? [],
    $nextClasses ?? [],
    $classCredits ?? 0,
    $memberTag ?? ''
);
