<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();

if (!$session->isLoggedIn()) {
    header('Location: /actions/login.php');
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
                    WHERE c2.trainer_id = cl.trainer_id AND c2.class_type_id = cl.class_type_id) AS review_count
         FROM client_classes cc
         JOIN classes cl ON cl.id = cc.class_id
         JOIN class_types ct ON ct.id = cl.class_type_id
         LEFT JOIN gym_locations gl ON gl.id = cl.gym_id
         LEFT JOIN users u ON u.id = cl.trainer_id
         WHERE cc.client_id = :id AND cl.schedule > datetime(\'now\')
         ORDER BY cl.schedule ASC
         LIMIT 5'
    );
    $stmt->execute([':id' => $userId]);
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
        'SELECT COALESCE(SUM((julianday(checked_out) - julianday(checked_in)) * 60), 0)
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
}


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
         WHERE gym_start >= datetime(\'now\', \'-30 days\')'
    );
    $stmt->execute();
    $newMemberships = (int)$stmt->fetchColumn();

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

    $stmt = $db->prepare(
        'SELECT u.first_name, u.last_name, u.username, u.profile_photo, u.created_at
         FROM users u
         JOIN clients c ON c.user_id = u.id
         ORDER BY u.created_at DESC
         LIMIT 5'
    );
    $stmt->execute();
    $recentMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
<?php drawDashHeader($session, $db, 'home', in_array($role, ['client', 'trainer']) ? ['schedule'] : []); ?>

<main class="dashboard-page dashboard-<?= $role ?>">

    
    <div class="dash-header">
        <div class="dash-greeting">
            <span class="dash-role-tag"><?= strtoupper($role) ?></span>
            <h1>Welcome back, <span class="dash-name"><?= htmlspecialchars($user['first_name']) ?></span></h1>
            <p class="dash-date"><?= date('l, F j, Y') ?></p>
        </div>
    </div>

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
                    <li class="dash-class-item dash-class-item--clickable"
                        data-class-id="<?= $cls['id'] ?>"
                        onclick="openModal(<?= $cls['id'] ?>)">
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

        <div class="dash-stack">

            <section class="dash-card">
                <div class="dash-card-header">
                    <h2><i class="fa fa-flag-checkered"></i> Recent Classes</h2>
                    <a href="schedule.php" class="dash-link-small">See Schedule →</a>
                </div>
                <?php if (empty($recentClasses)): ?>
                    <div class="dash-empty"><span><i class="fa fa-dumbbell"></i></span><p>No past classes yet.</p></div>
                <?php else: ?>
                    <ul class="dash-class-list">
                        <?php foreach ($recentClasses as $cls):
                            $dt = new DateTime($cls['schedule']);
                            $reviewed = $cls['my_rating'] !== null;
                        ?>
                        <li class="dash-class-item dash-class-item--clickable dash-class-item--past"
                            data-class-id="<?= $cls['id'] ?>"
                            onclick="openModal(<?= $cls['id'] ?>)">
                            <div class="class-date-block class-date-block--past">
                                <span class="class-month"><?= $dt->format('M') ?></span>
                                <span class="class-day"><?= $dt->format('d') ?></span>
                            </div>
                            <div class="class-info">
                                <strong><?= htmlspecialchars($cls['class_name']) ?></strong>
                                <span><?= $dt->format('H:i') ?> · <?= $cls['duration_min'] ?> min</span>
                                <span><?= htmlspecialchars($cls['gym_city'] . ' — ' . $cls['gym_name']) ?></span>
                            </div>
                            <div class="class-review-badge">
                                <?php if ($reviewed): ?>
                                    <span class="review-badge review-badge--done" title="Reviewed">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa fa-star<?= $i <= $cls['my_rating'] ? '' : '-o' ?>" style="color:<?= $i <= $cls['my_rating'] ? '#f59e0b' : '#555' ?>"></i>
                                        <?php endfor; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="review-badge review-badge--pending"><i class="fa fa-pen"></i> Review</span>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

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

        </div>

        <section class="dash-card dash-metrics-card">
            <div class="dash-card-header">
                <h2><i class="fa fa-chart-line"></i> My Metrics</h2>
                <a href="../actions/edit-profile.php" class="dash-link-small">Edit →</a>
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

        <section class="dash-card">
            <div class="dash-card-header">
                <h2><i class="fa fa-medal"></i> My Badges</h2>
                <a href="../actions/edit-profile.php#badges" class="dash-link-small">Manage →</a>
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

<?php
// construir o objeto SC para o js do modal - tem de ter o mesmo formato que no schedule.php
$dashTypeColors = [
    'Yoga' => '#a78bfa', 'Cycling' => '#60a5fa', 'Pilates' => '#f472b6',
    'HIIT' => '#fb923c', 'Personal Training' => '#34d399', 'Spin' => '#22d3ee',
    'Strength & Conditioning' => '#fbbf24', 'Zumba' => '#a3e635', 'Boxing' => '#f87171',
];
$dashClassesForSC = [];
// juntamos as upcoming e as recentes para o modal funcionar em ambas
foreach (array_merge($upcomingClasses, $recentClasses) as $c) {
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
?>

<?php endif; /* end client */ ?>


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

    <?php if (!empty($specializations)): ?>
    <div class="dash-spec-row">
        <?php foreach ($specializations as $spec): ?>
        <span class="specialization-tag"><?= htmlspecialchars($spec) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="dash-grid-2">

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
                        data-class-id="<?= $cls['id'] ?>"
                        onclick="openModal(<?= $cls['id'] ?>)">
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

<?php
$dashTypeColors = [
    'Yoga' => '#a78bfa', 'Cycling' => '#60a5fa', 'Pilates' => '#f472b6',
    'HIIT' => '#fb923c', 'Personal Training' => '#34d399', 'Spin' => '#22d3ee',
    'Strength & Conditioning' => '#fbbf24', 'Zumba' => '#a3e635', 'Boxing' => '#f87171',
];
$dashClassesForSC = [];
foreach ($trainerClasses as $c) {
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
?>

<?php endif;?>


<?php if ($role === 'admin'): ?>

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

<?php endif; ?>

</main>

<?php if (in_array($role, ['client', 'trainer'])): ?>
<div class="sc-modal" id="scModal" aria-hidden="true">
    <div class="sc-modal-backdrop" id="scModalBackdrop"></div>
    <div class="sc-modal-panel" id="scModalPanel">
        <button class="sc-modal-close" id="scModalClose"><i class="fa fa-xmark"></i></button>
        <div class="sc-modal-header" id="scModalHeader">
            <span class="sc-modal-type" id="scModalType"></span>
            <h2 class="sc-modal-title" id="scModalTitle"></h2>
            <p class="sc-modal-datetime" id="scModalDatetime"></p>
        </div>
        <div class="sc-modal-body">
            <div class="sc-modal-info-grid">
                <div class="sc-modal-info-item" id="scModalTrainerItem">
                    <img id="scModalTrainerAvatar" src="" alt="" class="sc-modal-trainer-avatar">
                    <div>
                        <small>Trainer</small>
                        <a class="sc-modal-trainer-link" id="scModalTrainerLink" href="#">
                            <strong id="scModalTrainerName"></strong>
                        </a>
                    </div>
                </div>
                <div class="sc-modal-info-item">
                    <i class="fa fa-location-dot"></i>
                    <div><small>Location</small><strong id="scModalLocation"></strong></div>
                </div>
                <div class="sc-modal-info-item">
                    <i class="fa fa-clock"></i>
                    <div><small>Duration</small><strong id="scModalDuration"></strong></div>
                </div>
                <div class="sc-modal-info-item">
                    <i class="fa fa-users"></i>
                    <div><small>Capacity</small><strong id="scModalCapacity"></strong></div>
                </div>
            </div>
            <div class="sc-modal-cap-wrap">
                <div class="sc-cap-bar sc-cap-bar--lg">
                    <div class="sc-cap-fill" id="scModalCapFill" style="width:0%"></div>
                </div>
                <span id="scModalCapLabel"></span>
            </div>
            <div class="sc-modal-section">
                <h3><i class="fa fa-circle-info"></i> About this class</h3>
                <p id="scModalDesc" class="sc-modal-desc"></p>
            </div>
            <div class="sc-modal-section">
                <h3><i class="fa fa-star"></i> Rating &amp; Reviews</h3>
                <div id="scModalRating"></div>
                <div id="scModalReviewList" class="sc-review-list"></div>
            </div>
        </div>
        <div class="sc-modal-footer" id="scModalFooter"></div>
    </div>
</div>
<script>
var SC = {
    isClient   : <?= $role === 'client' ? 'true' : 'false' ?>,
    weekOffset : 0,
    defaultDay : '',
    classes    : <?= json_encode($dashClassesForSC ?: new stdClass()) ?>,
};
</script>
<script src="../js/schedule.js"></script>
<?php endif; ?>

<?php drawFooter(); ?>

