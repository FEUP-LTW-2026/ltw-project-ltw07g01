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
    header('Location: login.php');
    exit;
}

//dados do user
$stmt = $db->prepare('SELECT username, first_name, last_name, profile_photo FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();
$profilePhoto = $user['profile_photo'] ?? '../images/profile_pic.webp';
$fullName     = $user['first_name'] . ' ' . $user['last_name'];
$profileUrl   = ($role === 'trainer') ? 'trainer-profile.php?id=' . $userId : 'profile.php';

//enrolled students for a trainers class
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

//client

if ($role === 'client') {

    //dados do client
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

    //próximas aulas (inscritas)
    $stmt = $db->prepare(
        'SELECT cl.id, ct.name AS class_name, cl.schedule, cl.duration_min,
                gl.name AS gym_name, gl.city AS gym_city,
                u.first_name AS trainer_first, u.last_name AS trainer_last
         FROM client_classes cc
         JOIN classes cl ON cl.id = cc.class_id
         JOIN class_types ct ON ct.id = cl.class_type_id
         LEFT JOIN gym_locations gl ON gl.id = cl.gym_id
         LEFT JOIN trainers t ON t.user_id = cl.trainer_id
         LEFT JOIN users u ON u.id = t.user_id
         WHERE cc.client_id = :id AND cl.schedule > datetime(\'now\')
         ORDER BY cl.schedule ASC
         LIMIT 5'
    );
    $stmt->execute([':id' => $userId]);
    $upcomingClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //visitas recentes
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

    //stats gerais
    $stmt = $db->prepare('SELECT COUNT(*) FROM gym_visits WHERE client_id = :id');
    $stmt->execute([':id' => $userId]);
    $totalVisits = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM client_classes WHERE client_id = :id');
    $stmt->execute([':id' => $userId]);
    $totalClasses = (int)$stmt->fetchColumn();

    $stmt = $db->prepare(
        'SELECT COALESCE(SUM((julianday(checked_out) - julianday(checked_in)) * 60), 0)
         FROM gym_visits WHERE client_id = :id AND checked_out IS NOT NULL'
    );
    $stmt->execute([':id' => $userId]);
    $totalMinutes = (int)round((float)$stmt->fetchColumn());
    $totalHours   = round($totalMinutes / 60, 1);

    // badges
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

// trainer
if ($role === 'trainer') {

    // próximas aulas do trainer
    $stmt = $db->prepare(
        'SELECT cl.id, ct.name AS class_name, cl.schedule, cl.duration_min, cl.capacity,
                gl.name AS gym_name, gl.city AS gym_city,
                (SELECT COUNT(*) FROM client_classes cc WHERE cc.class_id = cl.id) AS enrolled
         FROM classes cl
         JOIN class_types ct ON ct.id = cl.class_type_id
         LEFT JOIN gym_locations gl ON gl.id = cl.gym_id
         WHERE cl.trainer_id = :id AND cl.schedule > datetime(\'now\')
         ORDER BY cl.schedule ASC
         LIMIT 6'
    );
    $stmt->execute([':id' => $userId]);
    $trainerClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total de alunos (inscritos em aulas deste trainer)
    $stmt = $db->prepare(
        'SELECT COUNT(DISTINCT cc.client_id)
         FROM client_classes cc
         JOIN classes cl ON cl.id = cc.class_id
         WHERE cl.trainer_id = :id'
    );
    $stmt->execute([':id' => $userId]);
    $totalStudents = (int)$stmt->fetchColumn();

    // Total de aulas dadas (passadas)
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM classes WHERE trainer_id = :id AND schedule < datetime(\'now\')'
    );
    $stmt->execute([':id' => $userId]);
    $classesTaught = (int)$stmt->fetchColumn();

    // Rating médio
    $stmt = $db->prepare(
        'SELECT ROUND(AVG(r.rating), 1)
         FROM reviews r
         JOIN classes cl ON cl.id = r.class_id
         WHERE cl.trainer_id = :id'
    );
    $stmt->execute([':id' => $userId]);
    $avgRating = $stmt->fetchColumn() ?? '—';

    // Clientes recentes
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

    // Specializations
    $stmt = $db->prepare(
        'SELECT ct.name FROM trainer_specializations ts
         JOIN class_types ct ON ct.id = ts.class_type_id
         WHERE ts.trainer_id = :id'
    );
    $stmt->execute([':id' => $userId]);
    $specializations = $stmt->fetchAll(PDO::FETCH_COLUMN);
}


// admin

if ($role === 'admin') {

    $stmt = $db->prepare('SELECT COUNT(*) FROM clients');
    $stmt->execute();
    $totalMembers = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM trainers');
    $stmt->execute();
    $totalTrainers = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM classes WHERE schedule > datetime(\'now\')');
    $stmt->execute();
    $upcomingClassesCount = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM gym_locations');
    $stmt->execute();
    $totalGyms = (int)$stmt->fetchColumn();

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM gym_visits
         WHERE checked_in >= datetime(\'now\', \'-7 days\')'
    );
    $stmt->execute();
    $visitsThisWeek = (int)$stmt->fetchColumn();

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM memberships
         WHERE start_date >= datetime(\'now\', \'-30 days\')'
    );
    $stmt->execute();
    $newMemberships = (int)$stmt->fetchColumn();

    // Aulas mais populares
    $stmt = $db->prepare(
        'SELECT ct.name, COUNT(cc.client_id) AS enrollments
         FROM client_classes cc
         JOIN classes cl ON cl.id = cc.class_id
         JOIN class_types ct ON ct.id = cl.class_type_id
         GROUP BY ct.name
         ORDER BY enrollments DESC
         LIMIT 5'
    );
    $stmt->execute();
    $popularClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //membros mais recentes
    $stmt = $db->prepare(
        'SELECT u.first_name, u.last_name, u.username, u.profile_photo, u.created_at
         FROM users u
         JOIN clients c ON c.user_id = u.id
         ORDER BY u.created_at DESC
         LIMIT 5'
    );
    $stmt->execute();
    $recentMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ginásios e visitas
    $stmt = $db->prepare(
        'SELECT gl.name, gl.city, COUNT(gv.id) AS visit_count
         FROM gym_locations gl
         LEFT JOIN gym_visits gv ON gv.gym_id = gl.id
         GROUP BY gl.id
         ORDER BY visit_count DESC'
    );
    $stmt->execute();
    $gymStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Cubo Gym</title>
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Gothic&display=swap" rel="stylesheet">
</head>
<body class="<?= $role === 'trainer' ? 'trainer-theme' : '' ?>">

<?php drawDashNavbar($session, $db, 'home'); ?>

<main class="dashboard-page dashboard-<?= $role ?>">

    
    <div class="dash-header">
        <div class="dash-greeting">
            <span class="dash-role-tag"><?= strtoupper($role) ?></span>
            <h1>Welcome back, <span class="dash-name"><?= htmlspecialchars($user['first_name']) ?></span></h1>
            <p class="dash-date"><?= date('l, F j, Y') ?></p>
        </div>
    </div>

<?php /* dashboard do cliente*/ ?>
<?php if ($role === 'client'): ?>

    <!-- Stats row -->
    <div class="dash-stats-row">
        <div class="dash-stat-card">
            <span class="dash-stat-icon"><i class="fa fa-person-running"></i></span>
            <div>
                <span class="dash-stat-value"><?= $totalVisits ?></span>
                <span class="dash-stat-label">Total Visits</span>
            </div>
        </div>
        <div class="dash-stat-card">
            <span class="dash-stat-icon"><i class="fa fa-calendar-check"></i></span>
            <div>
                <span class="dash-stat-value"><?= $totalClasses ?></span>
                <span class="dash-stat-label">Classes Booked</span>
            </div>
        </div>
        <div class="dash-stat-card">
            <span class="dash-stat-icon"><i class="fa fa-clock"></i></span>
            <div>
                <span class="dash-stat-value"><?= $totalHours ?>h</span>
                <span class="dash-stat-label">Gym Time</span>
            </div>
        </div>
        <div class="dash-stat-card">
            <span class="dash-stat-icon"><i class="fa fa-medal"></i></span>
            <div>
                <span class="dash-stat-value"><?= $earnedBadgeCount ?></span>
                <span class="dash-stat-label">Badges Earned</span>
            </div>
        </div>
    </div>

    <div class="dash-grid-2">

        <!--próximas Aulas -->
        <section class="dash-card">
            <div class="dash-card-header">
                <h2><i class="fa fa-calendar-check"></i> Upcoming Classes</h2>
                <a href="schedule.php" class="dash-link-small">See Schedule →</a>
            </div>
            <?php if (empty($upcomingClasses)): ?>
                <div class="dash-empty">
                    <span><i class="fa fa-inbox"></i></span>
                    <p>No upcoming classes.<br><a href="schedule.php">Book a class</a></p>
                </div>
            <?php else: ?>
                <ul class="dash-class-list">
                    <?php foreach ($upcomingClasses as $cls):
                        $dt = new DateTime($cls['schedule']);
                    ?>
                    <li class="dash-class-item">
                        <div class="class-date-block">
                            <span class="class-month"><?= $dt->format('M') ?></span>
                            <span class="class-day"><?= $dt->format('d') ?></span>
                        </div>
                        <div class="class-info">
                            <strong><?= htmlspecialchars($cls['class_name']) ?></strong>
                            <span><?= $dt->format('H:i') ?> · <?= $cls['duration_min'] ?> min</span>
                            <span><?= htmlspecialchars($cls['gym_city'] . ' — ' . $cls['gym_name']) ?></span>
                        </div>
                        <?php if ($cls['trainer_first']): ?>
                        <div class="class-trainer">
                            <i class="fa fa-user-tie"></i>
                            <?= htmlspecialchars($cls['trainer_first'] . ' ' . $cls['trainer_last']) ?>
                        </div>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!--visitas recentes -->
        <section class="dash-card">
            <div class="dash-card-header">
                <h2><i class="fa fa-clock"></i> Recent Visits</h2>
            </div>
            <?php if (empty($recentVisits)): ?>
                <div class="dash-empty"><span><i class="fa fa-landmark"></i></span><p>No visits recorded yet.</p></div>
            <?php else: ?>
                <ul class="dash-visit-list">
                    <?php foreach ($recentVisits as $visit):
                        $inDt  = new DateTime($visit['checked_in']);
                        $outDt = $visit['checked_out'] ? new DateTime($visit['checked_out']) : null;
                        $dur   = $outDt ? round(($outDt->getTimestamp() - $inDt->getTimestamp()) / 60) . ' min' : 'Active';
                    ?>
                    <li class="dash-visit-item">
                        <div class="visit-dot"></div>
                        <div class="visit-info">
                            <strong><?= htmlspecialchars($visit['gym_city'] . ' — ' . $visit['gym_name']) ?></strong>
                            <span><?= $inDt->format('d M Y, H:i') ?></span>
                        </div>
                        <span class="visit-dur <?= $dur === 'Active' ? 'visit-dur--active' : '' ?>"><?= $dur ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!--metrics -->
        <section class="dash-card dash-metrics-card">
            <div class="dash-card-header">
                <h2><i class="fa fa-chart-line"></i> My Metrics</h2>
                <a href="edit-profile.php" class="dash-link-small">Edit →</a>
            </div>
            <div class="metrics-grid">
                <div class="metric-card">
                    <label class="metric-label">Body Weight</label>
                    <span class="metric-value"><?= $clientData['body_weight'] ? $clientData['body_weight'] . ' kg' : '—' ?></span>
                </div>
                <div class="metric-card">
                    <label class="metric-label">Height</label>
                    <span class="metric-value"><?= $clientData['height'] ? $clientData['height'] . ' cm' : '—' ?></span>
                </div>
                <div class="metric-card">
                    <label class="metric-label">Archetype</label>
                    <span class="metric-value" style="font-size:1rem"><?= htmlspecialchars($clientData['archetype'] ?? '—') ?></span>
                </div>
                <div class="metric-card">
                    <label class="metric-label">Home Gym</label>
                    <span class="metric-value" style="font-size:0.9rem"><?= $clientData['gym_name'] ? htmlspecialchars($clientData['gym_city'] . ' — ' . $clientData['gym_name']) : '—' ?></span>
                </div>
            </div>
        </section>

        <!--badges -->
        <section class="dash-card">
            <div class="dash-card-header">
                <h2><i class="fa fa-medal"></i> My Badges</h2>
                <a href="edit-profile.php#badges" class="dash-link-small">Manage →</a>
            </div>
            <?php if (empty($displayBadges)): ?>
                <div class="dash-empty"><span><i class="fa fa-bullseye"></i></span><p>Keep training to earn badges!</p></div>
            <?php else: ?>
                <div class="badge-container">
                    <?php foreach ($displayBadges as $badge): ?>
                    <span class="badge" title="<?= htmlspecialchars($badge['label']) ?>">
                        <?= $badge['icon'] ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </div><!-- /.dash-grid-2 -->

<?php endif; /* end client */ ?>


<?php /* dashboard do trainer */ ?>
<?php if ($role === 'trainer'): ?>

    <!-- Stats row -->
    <div class="dash-stats-row">
        <div class="dash-stat-card">
            <span class="dash-stat-icon"><i class="fa fa-users"></i></span>
            <div>
                <span class="dash-stat-value"><?= $totalStudents ?></span>
                <span class="dash-stat-label">Total Students</span>
            </div>
        </div>
        <div class="dash-stat-card">
            <span class="dash-stat-icon"><i class="fa fa-chalkboard-teacher"></i></span>
            <div>
                <span class="dash-stat-value"><?= $classesTaught ?></span>
                <span class="dash-stat-label">Classes Given</span>
            </div>
        </div>
        <div class="dash-stat-card">
            <span class="dash-stat-icon"><i class="fa fa-star"></i></span>
            <div>
                <span class="dash-stat-value"><?= $avgRating ?></span>
                <span class="dash-stat-label">Avg. Rating</span>
            </div>
        </div>
        <div class="dash-stat-card">
            <span class="dash-stat-icon"><i class="fa fa-clipboard-list"></i></span>
            <div>
                <span class="dash-stat-value"><?= count($trainerClasses) ?></span>
                <span class="dash-stat-label">Upcoming Classes</span>
            </div>
        </div>
    </div>

    <!--specializations -->
    <?php if (!empty($specializations)): ?>
    <div class="dash-spec-row">
        <?php foreach ($specializations as $spec): ?>
        <span class="specialization-tag"><?= htmlspecialchars($spec) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="dash-grid-2">

        <!-- próximas aulas do trainer -->
        <section class="dash-card">
            <div class="dash-card-header">
                <h2><i class="fa fa-chalkboard-teacher"></i> Upcoming Classes</h2>
            </div>
            <?php if (empty($trainerClasses)): ?>
                <div class="dash-empty"><span><i class="fa fa-inbox"></i></span><p>No upcoming classes scheduled.</p></div>
            <?php else: ?>
                <ul class="dash-class-list">
                    <?php foreach ($trainerClasses as $cls):
                        $dt = new DateTime($cls['schedule']);
                        $fillPct = $cls['capacity'] > 0 ? round(($cls['enrolled'] / $cls['capacity']) * 100) : 0;
                    ?>
                    <li class="dash-class-item dash-class-item--clickable"
                        onclick="openClassStudentsModal(<?= $cls['id'] ?>)">
                        <div class="class-date-block">
                            <span class="class-month"><?= $dt->format('M') ?></span>
                            <span class="class-day"><?= $dt->format('d') ?></span>
                        </div>
                        <div class="class-info">
                            <strong><?= htmlspecialchars($cls['class_name']) ?></strong>
                            <span><?= $dt->format('H:i') ?> · <?= $cls['duration_min'] ?> min</span>
                            <span><?= htmlspecialchars($cls['gym_city'] . ' — ' . $cls['gym_name']) ?></span>
                        </div>
                        <div class="class-capacity">
                            <span><?= $cls['enrolled'] ?>/<?= $cls['capacity'] ?></span>
                            <div class="capacity-bar">
                                <div class="capacity-fill" style="width:<?= $fillPct ?>%"></div>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- alunos recentes -->
        <section class="dash-card">
            <div class="dash-card-header">
                <h2><i class="fa fa-users"></i> Recent Students</h2>
            </div>
            <?php if (empty($recentStudents)): ?>
                <div class="dash-empty"><span><i class="fa fa-user"></i></span><p>No students enrolled yet.</p></div>
            <?php else: ?>
                <ul class="dash-student-list">
                    <?php foreach ($recentStudents as $stu): ?>
                    <li class="dash-student-item">
                        <a href="profile.php?id=<?= (int)$stu['user_id'] ?>" class="dash-student-link">
                            <img src="<?= htmlspecialchars($stu['profile_photo'] ?? '../images/profile_pic.webp') ?>"
                                 alt="<?= htmlspecialchars($stu['username']) ?>" class="student-avatar">
                            <div>
                                <strong><?= htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']) ?></strong>
                                <span>@<?= htmlspecialchars($stu['username']) ?></span>
                            </div>
                        </a>
                        <span class="student-date"><?= (new DateTime($stu['enrolled_at']))->format('d M') ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div>

<!--class students modal -->
<div class="csm-overlay" id="csmOverlay" aria-hidden="true">
    <div class="csm-backdrop" id="csmBackdrop"></div>
    <div class="csm-panel">
        <button class="csm-close" id="csmClose"><i class="fa fa-xmark"></i></button>
        <div class="csm-header">
            <h3 id="csmTitle"></h3>
            <p id="csmInfo"></p>
        </div>
        <div class="csm-grid" id="csmGrid"></div>
    </div>
</div>

<?php endif; /* end trainer */ ?>


<?php /* dahsboard do admin*/ ?>
<?php if ($role === 'admin'): ?>

    <!-- Stats row -->
    <div class="dash-stats-row dash-stats-row--admin">
        <div class="dash-stat-card">
            <span class="dash-stat-icon"><i class="fa fa-users"></i></span>
            <div>
                <span class="dash-stat-value"><?= $totalMembers ?></span>
                <span class="dash-stat-label">Total Members</span>
            </div>
        </div>
        <div class="dash-stat-card">
            <span class="dash-stat-icon"><i class="fa fa-dumbbell"></i></span>
            <div>
                <span class="dash-stat-value"><?= $totalTrainers ?></span>
                <span class="dash-stat-label">Trainers</span>
            </div>
        </div>
        <div class="dash-stat-card">
            <span class="dash-stat-icon"><i class="fa fa-calendar"></i></span>
            <div>
                <span class="dash-stat-value"><?= $upcomingClassesCount ?></span>
                <span class="dash-stat-label">Upcoming Classes</span>
            </div>
        </div>
        <div class="dash-stat-card">
            <span class="dash-stat-icon"><i class="fa fa-location-dot"></i></span>
            <div>
                <span class="dash-stat-value"><?= $totalGyms ?></span>
                <span class="dash-stat-label">Gym Locations</span>
            </div>
        </div>
        <div class="dash-stat-card">
            <span class="dash-stat-icon"><i class="fa fa-rotate"></i></span>
            <div>
                <span class="dash-stat-value"><?= $visitsThisWeek ?></span>
                <span class="dash-stat-label">Visits This Week</span>
            </div>
        </div>
        <div class="dash-stat-card">
            <span class="dash-stat-icon"><i class="fa fa-user-plus"></i></span>
            <div>
                <span class="dash-stat-value"><?= $newMemberships ?></span>
                <span class="dash-stat-label">New Members (30d)</span>
            </div>
        </div>
    </div>

    <div class="dash-grid-3">

        <!--ginásios -->
        <section class="dash-card">
            <div class="dash-card-header">
                <h2><i class="fa fa-building"></i> Gym Locations</h2>
            </div>
            <ul class="dash-gym-list">
                <?php foreach ($gymStats as $gym): ?>
                <li class="dash-gym-item">
                    <div>
                        <strong><?= htmlspecialchars($gym['name']) ?></strong>
                        <span><?= htmlspecialchars($gym['city']) ?></span>
                    </div>
                    <span class="gym-visit-badge"><?= $gym['visit_count'] ?> visits</span>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!--aulas mais populares -->
        <section class="dash-card">
            <div class="dash-card-header">
                <h2><i class="fa fa-fire"></i> Popular Classes</h2>
            </div>
            <?php if (empty($popularClasses)): ?>
                <div class="dash-empty"><span><i class="fa fa-chart-bar"></i></span><p>No data yet.</p></div>
            <?php else: ?>
                <?php
                $maxEnroll = max(array_column($popularClasses, 'enrollments'));
                ?>
                <ul class="dash-popular-list">
                    <?php foreach ($popularClasses as $i => $pc):
                        $pct = $maxEnroll > 0 ? round(($pc['enrollments'] / $maxEnroll) * 100) : 0;
                    ?>
                    <li class="dash-popular-item">
                        <span class="popular-rank">#<?= $i + 1 ?></span>
                        <div class="popular-info">
                            <span><?= htmlspecialchars($pc['name']) ?></span>
                            <div class="popular-bar">
                                <div class="popular-fill" style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>
                        <span class="popular-count"><?= $pc['enrollments'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!--membros recentes -->
        <section class="dash-card">
            <div class="dash-card-header">
                <h2><i class="fa fa-user-plus"></i> Recent Members</h2>
            </div>
            <?php if (empty($recentMembers)): ?>
                <div class="dash-empty"><span><i class="fa fa-user"></i></span><p>No members yet.</p></div>
            <?php else: ?>
                <ul class="dash-student-list">
                    <?php foreach ($recentMembers as $m): ?>
                    <li class="dash-student-item">
                        <img src="<?= htmlspecialchars($m['profile_photo'] ?? '../images/profile_pic.webp') ?>"
                             alt="<?= htmlspecialchars($m['username']) ?>" class="student-avatar">
                        <div>
                            <strong><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></strong>
                            <span>@<?= htmlspecialchars($m['username']) ?></span>
                        </div>
                        <span class="student-date"><?= (new DateTime($m['created_at']))->format('d M') ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div>

<?php endif; /* end admin */ ?>

</main>

<?php drawFooter(); ?>

<?php if ($role === 'trainer'): ?>
<script>
(function () {
    var overlay  = document.getElementById('csmOverlay');
    var backdrop = document.getElementById('csmBackdrop');
    var closeBtn = document.getElementById('csmClose');
    var grid     = document.getElementById('csmGrid');

    function closeModal() {
        overlay.setAttribute('aria-hidden', 'true');
        overlay.classList.remove('csm-open');
        document.body.style.overflow = '';
    }

    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

    window.openClassStudentsModal = function (classId) {
        grid.innerHTML = '<p class="csm-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</p>';
        overlay.setAttribute('aria-hidden', 'false');
        overlay.classList.add('csm-open');
        document.body.style.overflow = 'hidden';

        fetch('dashboard.php?ajax=1&class_id=' + classId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) { grid.innerHTML = '<p class="csm-loading">Could not load students.</p>'; return; }
                document.getElementById('csmTitle').textContent = data.class.class_name;
                var d = new Date(data.class.schedule);
                document.getElementById('csmInfo').textContent =
                    d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })
                    + ' · ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
                    + ' · ' + data.class.enrolled + ' / ' + data.class.capacity + ' enrolled';

                if (data.students.length === 0) {
                    grid.innerHTML = '<p class="csm-loading">No students enrolled yet.</p>';
                } else {
                    grid.innerHTML = data.students.map(function (s) {
                        var photo = s.profile_photo || '../images/profile_pic.webp';
                        return '<a href="profile.php?id=' + s.id + '" class="csm-card">'
                            + '<img src="' + photo + '" alt="' + s.first_name + '" '
                            + 'onerror="this.src=\'../images/profile_pic.webp\'">'
                            + '<span>' + s.first_name + ' ' + s.last_name + '</span>'
                            + '</a>';
                    }).join('');
                }
            })
            .catch(function () {
                grid.innerHTML = '<p class="csm-loading">Error loading students.</p>';
            });
    };
})();
</script>
<?php endif; ?>

</body>
</html>