<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();
require_once('../database/connection.db.php');
require_once('../templates/common.tpl.php');
require_once(__DIR__ . '/../templates/schedule.tpl.php');
$db = getDatabaseConnection();

$userId = $session->isLoggedIn() ? (int)$session->getId() : 0;
$role = null;
if ($userId) {
    foreach (['admins' => 'admin', 'trainers' => 'trainer', 'clients' => 'client'] as $tbl => $r) {
        $s = $db->prepare("SELECT 1 FROM $tbl WHERE user_id = :id");
        $s->execute([':id' => $userId]);
        if ($s->fetch()) { $role = $r; break; }
    }
}
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$trainerClassTypeIds = [];
$trainerGymIds = [];
if ($role === 'trainer') {
    $s = $db->prepare('SELECT class_type_id FROM trainer_specializations WHERE trainer_id = ?');
    $s->execute([$userId]);
    $trainerClassTypeIds = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));

    $s = $db->prepare('SELECT gym_id FROM trainer_locations WHERE trainer_id = ?');
    $s->execute([$userId]);
    $trainerGymIds = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

function trainerCanUseClassOption(string $role, int $classTypeId, int $gymId, array $trainerClassTypeIds, array $trainerGymIds): bool {
    if ($role !== 'trainer') return true;
    return in_array($classTypeId, $trainerClassTypeIds, true)
        && in_array($gymId, $trainerGymIds, true);
}

// admin/trainer: criar aula; trainer só edita/apaga aulas próprias
if (in_array($role, ['admin', 'trainer'], true) && $requestMethod === 'POST' && empty($_GET['ajax'])) {
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $classTypeId = (int)($_POST['class_type_id'] ?? 0);
        $gymId       = (int)($_POST['gym_id']        ?? 0);
        $trainerId   = $role === 'trainer' ? $userId : ((int)($_POST['trainer_id'] ?? 0) ?: null);
        $schedule    = trim($_POST['schedule']        ?? '');
        $duration    = (int)($_POST['duration_min']  ?? 0);
        $capacity    = (int)($_POST['capacity']       ?? 0);
        if ($classTypeId && $gymId && $schedule && $duration > 0 && $capacity > 0 && trainerCanUseClassOption($role ?? '', $classTypeId, $gymId, $trainerClassTypeIds, $trainerGymIds)) {
            $db->prepare('INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity) VALUES (?,?,?,?,?,?)')
               ->execute([$classTypeId, $gymId, $trainerId, $schedule, $duration, $capacity]);
        }
        header('Location: /pages/schedule.php'); exit;

    } elseif ($action === 'update') {
        $targetId    = (int)($_POST['target_id']     ?? 0);
        $classTypeId = (int)($_POST['class_type_id'] ?? 0);
        $gymId       = (int)($_POST['gym_id']        ?? 0);
        $trainerId   = $role === 'trainer' ? $userId : ((int)($_POST['trainer_id'] ?? 0) ?: null);
        $schedule    = trim($_POST['schedule']        ?? '');
        $duration    = (int)($_POST['duration_min']  ?? 0);
        $capacity    = (int)($_POST['capacity']       ?? 0);
        if ($targetId && $classTypeId && $gymId && $schedule && $duration > 0 && $capacity > 0 && trainerCanUseClassOption($role ?? '', $classTypeId, $gymId, $trainerClassTypeIds, $trainerGymIds)) {
            if ($role === 'trainer') {
                $db->prepare('UPDATE classes SET class_type_id=?, gym_id=?, trainer_id=?, schedule=?, duration_min=?, capacity=? WHERE id=? AND trainer_id=?')
                   ->execute([$classTypeId, $gymId, $trainerId, $schedule, $duration, $capacity, $targetId, $userId]);
            } else {
                $db->prepare('UPDATE classes SET class_type_id=?, gym_id=?, trainer_id=?, schedule=?, duration_min=?, capacity=? WHERE id=?')
                   ->execute([$classTypeId, $gymId, $trainerId, $schedule, $duration, $capacity, $targetId]);
            }
        }
        header('Location: /pages/schedule.php'); exit;

    } elseif ($action === 'delete') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            if ($role === 'trainer') {
                $db->prepare('DELETE FROM classes WHERE id=? AND trainer_id=?')->execute([$targetId, $userId]);
            } else {
                $db->prepare('DELETE FROM classes WHERE id=?')->execute([$targetId]);
            }
        }
        header('Location: /pages/schedule.php'); exit;
    }
}

// ajax: lista de inscritos (admin e trainer)
if (in_array($role, ['admin', 'trainer'], true) && $requestMethod === 'GET' && !empty($_GET['ajax']) && ($_GET['action'] ?? '') === 'enrollments') {
    header('Content-Type: application/json');
    $classId = (int)($_GET['class_id'] ?? 0);
    if (!$classId) { echo json_encode(['ok' => false]); exit; }
    if ($role === 'trainer') {
        $s = $db->prepare('SELECT 1 FROM classes WHERE id=? AND trainer_id=?');
        $s->execute([$classId, $userId]);
        if (!$s->fetch()) { echo json_encode(['ok' => false]); exit; }
    }
    $s = $db->prepare('SELECT u.id, u.first_name, u.last_name, u.username, u.profile_photo FROM client_classes cc JOIN users u ON u.id = cc.client_id WHERE cc.class_id = ? ORDER BY u.first_name, u.last_name');
    $s->execute([$classId]);
    echo json_encode(['ok' => true, 'enrollments' => $s->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ajax: remover inscrito (admin e trainer)
if (in_array($role, ['admin', 'trainer'], true) && $requestMethod === 'POST' && !empty($_POST['ajax']) && ($_POST['action'] ?? '') === 'unenroll') {
    header('Content-Type: application/json');
    $classId  = (int)($_POST['class_id']  ?? 0);
    $clientId = (int)($_POST['client_id'] ?? 0);
    if (!$classId || !$clientId) { echo json_encode(['ok' => false]); exit; }
    if ($role === 'trainer') {
        $s = $db->prepare('SELECT 1 FROM classes WHERE id=? AND trainer_id=?');
        $s->execute([$classId, $userId]);
        if (!$s->fetch()) { echo json_encode(['ok' => false]); exit; }
    }
    $db->beginTransaction();
    $db->prepare('DELETE FROM client_classes WHERE client_id=? AND class_id=?')->execute([$clientId, $classId]);
    $db->prepare('UPDATE memberships SET classes_remaining = classes_remaining + 1 WHERE client_id = ?')->execute([$clientId]);
    $db->commit();
    $s = $db->prepare('SELECT COUNT(*) FROM client_classes WHERE class_id=?');
    $s->execute([$classId]);
    echo json_encode(['ok' => true, 'enrolled' => (int)$s->fetchColumn()]);
    exit;
}

// ajax: review
if ($requestMethod === 'POST' && !empty($_POST['ajax']) && ($_POST['action'] ?? '') === 'review' && $role === 'client') {
    header('Content-Type: application/json');
    $classId = (int)($_POST['class_id'] ?? 0);
    $rating  = (int)($_POST['rating'] ?? 0);
    $comment = mb_substr(trim($_POST['comment'] ?? ''), 0, 500);
    if ($classId <= 0 || $rating < 1 || $rating > 5) { echo json_encode(['ok' => false, 'error' => 'invalid']); exit; }
    $s = $db->prepare('SELECT 1 FROM client_classes WHERE client_id=? AND class_id=?');
    $s->execute([$userId, $classId]);
    if (!$s->fetch()) { echo json_encode(['ok' => false, 'error' => 'not_enrolled']); exit; }
    $s = $db->prepare('SELECT class_type_id, trainer_id, schedule FROM classes WHERE id=?');
    $s->execute([$classId]);
    $cl = $s->fetch();
    if (!$cl || $cl['schedule'] >= date('Y-m-d H:i:s')) { echo json_encode(['ok' => false, 'error' => 'not_past']); exit; }
    $s = $db->prepare('SELECT COUNT(*) FROM reviews WHERE client_id=? AND class_id=?');
    $s->execute([$userId, $classId]);
    if ($s->fetchColumn()) { echo json_encode(['ok' => false, 'error' => 'already_reviewed']); exit; }
    $db->prepare('INSERT INTO reviews (client_id, class_id, rating, comment) VALUES (?,?,?,?)')
        ->execute([$userId, $classId, $rating, $comment ?: null]);
    $s = $db->prepare('SELECT ROUND(AVG(r.rating),1) AS avg_rating, COUNT(*) AS review_count FROM reviews r JOIN classes c2 ON c2.id = r.class_id WHERE c2.trainer_id = ? AND c2.class_type_id = ?');
    $s->execute([$cl['trainer_id'], $cl['class_type_id']]);
    $agg = $s->fetch();
    echo json_encode(['ok' => true, 'avg_rating' => (float)($agg['avg_rating'] ?? 0), 'review_count' => (int)($agg['review_count'] ?? 0)]);
    exit;
}

// ajax: cancelar
if ($requestMethod === 'POST' && !empty($_POST['ajax']) && ($_POST['action'] ?? '') === 'cancel' && $role === 'client') {
    header('Content-Type: application/json');
    $classId = (int)($_POST['class_id'] ?? 0);
    if ($classId <= 0) { echo json_encode(['ok' => false, 'error' => 'invalid']); exit; }
    $s = $db->prepare('SELECT c.schedule FROM client_classes cc JOIN classes c ON c.id = cc.class_id WHERE cc.client_id=? AND cc.class_id=?');
    $s->execute([$userId, $classId]);
    $enrollment = $s->fetch();
    if (!$enrollment) { echo json_encode(['ok' => false, 'error' => 'not_enrolled']); exit; }
    if ($enrollment['schedule'] <= date('Y-m-d H:i:s')) { echo json_encode(['ok' => false, 'error' => 'past_class']); exit; }
    $db->beginTransaction();
    $db->prepare('DELETE FROM client_classes WHERE client_id=? AND class_id=?')->execute([$userId, $classId]);
    $db->prepare('UPDATE memberships SET classes_remaining = classes_remaining + 1 WHERE client_id = ?')->execute([$userId]);
    $db->commit();
    $s = $db->prepare('SELECT capacity, (SELECT COUNT(*) FROM client_classes WHERE class_id=c.id) AS enrolled FROM classes c WHERE id=?');
    $s->execute([$classId]);
    $cl = $s->fetch();
    $newEnrolled = (int)$cl['enrolled'];
    echo json_encode(['ok' => true, 'enrolled' => $newEnrolled, 'capacity' => (int)$cl['capacity'], 'spots' => (int)$cl['capacity'] - $newEnrolled]);
    exit;
}

// ajax: reservar
if ($requestMethod === 'POST' && !empty($_POST['ajax']) && $role === 'client') {
    header('Content-Type: application/json');
    $classId = (int)($_POST['class_id'] ?? 0);
    if ($classId <= 0) { echo json_encode(['ok' => false, 'error' => 'invalid']); exit; }
    $s = $db->prepare('SELECT COUNT(*) FROM client_classes WHERE client_id=? AND class_id=?');
    $s->execute([$userId, $classId]);
    if ($s->fetchColumn()) { echo json_encode(['ok' => false, 'error' => 'already']); exit; }
    $s = $db->prepare('SELECT capacity, (SELECT COUNT(*) FROM client_classes WHERE class_id=c.id) AS enrolled FROM classes c WHERE id=?');
    $s->execute([$classId]);
    $cl = $s->fetch();
    if (!$cl || $cl['enrolled'] >= $cl['capacity']) { echo json_encode(['ok' => false, 'error' => 'full']); exit; }
    $s = $db->prepare('SELECT classes_remaining FROM memberships WHERE client_id = ?');
    $s->execute([$userId]);
    $mem = $s->fetch();
    if (!$mem || (int)$mem['classes_remaining'] <= 0) { echo json_encode(['ok' => false, 'error' => 'no_credits']); exit; }
    $db->prepare('INSERT INTO client_classes (client_id, class_id) VALUES (?,?)')->execute([$userId, $classId]);
    $db->prepare('UPDATE memberships SET classes_remaining = classes_remaining - 1 WHERE client_id = ?')->execute([$userId]);
    $newEnrolled = (int)$cl['enrolled'] + 1;
    echo json_encode(['ok' => true, 'enrolled' => $newEnrolled, 'capacity' => (int)$cl['capacity'], 'spots' => (int)$cl['capacity'] - $newEnrolled]);
    exit;
}

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
           cl.class_type_id, cl.gym_id, cl.trainer_id,
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
    AND ct.name IN ('Pilates', 'Cycling', 'Personal Training')
    AND (:trainer_filter IS NULL OR cl.trainer_id = :trainer_filter)
    ORDER BY cl.schedule ASC
");
$stmt->execute([
    ':start'          => $weekMon->format('Y-m-d'),
    ':end'            => $weekSun->format('Y-m-d'),
    ':trainer_filter' => ($role === 'trainer') ? $userId : null,
    ':uid'            => ($role === 'client') ? $userId : null,
]);
$allClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$classesByDay = [];
foreach ($allClasses as $cls) {
    $classesByDay[substr($cls['schedule'], 0, 10)][] = $cls;
}

$enrolledIds = [];
if ($role === 'client') {
    $s = $db->prepare('SELECT class_id FROM client_classes WHERE client_id = :id');
    $s->execute([':id' => $userId]);
    $enrolledIds = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

$classTypes = $gymList = $trainers = [];
if (in_array($role, ['admin', 'trainer'], true)) {
    if ($role === 'trainer') {
        $classTypes = $trainerClassTypeIds
            ? $db->query("SELECT id, name FROM class_types WHERE name IN ('Pilates', 'Cycling', 'Personal Training') AND id IN (" . implode(',', array_map('intval', $trainerClassTypeIds)) . ") ORDER BY name")->fetchAll(PDO::FETCH_ASSOC)
            : [];
        $gymList = $trainerGymIds
            ? $db->query('SELECT id, name, city FROM gym_locations WHERE id IN (' . implode(',', array_map('intval', $trainerGymIds)) . ') ORDER BY city, name')->fetchAll(PDO::FETCH_ASSOC)
            : [];
    } else {
        $classTypes = $db->query("SELECT id, name FROM class_types WHERE name IN ('Pilates', 'Cycling', 'Personal Training') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $gymList    = $db->query('SELECT id, name, city FROM gym_locations ORDER BY city, name')->fetchAll(PDO::FETCH_ASSOC);
    }
}
if ($role === 'admin') {
    $trainers   = $db->query('SELECT u.id, u.first_name, u.last_name FROM users u JOIN trainers t ON t.user_id=u.id ORDER BY u.first_name')->fetchAll(PDO::FETCH_ASSOC);
}

$totalClasses  = count($allClasses);
$totalSpots    = array_sum(array_map(fn($c) => max(0, $c['capacity'] - $c['enrolled']), $allClasses));
$totalEnrolled = array_sum(array_column($allClasses, 'enrolled'));

$weekTypes = array_values(array_unique(array_column($allClasses, 'class_name')));
sort($weekTypes);
$weekTrainers = [];
foreach ($allClasses as $c) {
    if ($c['trainer_id'] && !isset($weekTrainers[$c['trainer_id']])) {
        $weekTrainers[$c['trainer_id']] = $c['trainer_first'] . ' ' . $c['trainer_last'];
    }
}

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
    if ($h >= 6  && $h <= 11) return 'morning';
    if ($h >= 12 && $h <= 16) return 'afternoon';
    return 'evening';
}

$buildDynamicHTML = function() use ($days, $classesByDay, $today, $enrolledIds, $typeColors, $typeDescriptions, $role, $allClasses) {
    $usedTypes = array_unique(array_column($allClasses, 'class_name'));
    sort($usedTypes);
    ob_start();
    ?>

    <div class="sc-days-strip">
        <?php foreach ($days as $day):
            $dayKey = $day->format('Y-m-d');
            $isToday = $dayKey === $today->format('Y-m-d');
            $dayClasses = $classesByDay[$dayKey] ?? [];
            $typesOnDay = array_unique(array_column($dayClasses, 'class_name'));
        ?>
        <button class="sc-day-btn <?= $isToday ? 'sc-day-btn--today' : '' ?> <?= empty($dayClasses) ? 'sc-day-btn--empty' : '' ?>"
                data-day="<?= $dayKey ?>">
            <span class="sc-day-name"><?= $day->format('D') ?></span>
            <span class="sc-day-num"><?= $day->format('j') ?></span>
            <div class="sc-day-dots">
                <?php if (empty($dayClasses)): ?>
                    <span class="sc-dot sc-dot--empty"></span>
                <?php else: ?>
                    <?php foreach (array_slice($typesOnDay, 0, 4) as $tn): ?>
                    <span class="sc-dot" style="background:<?= typeColor($tn, $typeColors) ?>"></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($days as $day):
        $dayKey = $day->format('Y-m-d');
        $dayClasses = $classesByDay[$dayKey] ?? [];
    ?>
    <div class="sc-day-panel" id="panel-<?= $dayKey ?>">

        <div class="sc-panel-header">
            <h2 class="sc-panel-day"><?= $day->format('l, F j') ?></h2>
            <span class="sc-panel-count" id="count-<?= $dayKey ?>">
                <?= count($dayClasses) ?> class<?= count($dayClasses) !== 1 ? 'es' : '' ?>
            </span>
        </div>

        <?php if (empty($dayClasses)): ?>
        <div class="sc-no-classes"><i class="fa fa-moon"></i><p>No classes scheduled for this day.</p></div>
        <?php else: ?>

        <div class="sc-filtered-empty" id="fempty-<?= $dayKey ?>" hidden>
            <i class="fa fa-filter"></i><p>No classes match the current filters.</p>
        </div>

        <div class="sc-timeline" id="timeline-<?= $dayKey ?>">
            <?php foreach ($dayClasses as $cls):
                $dt = new DateTime($cls['schedule']);
                $spots = (int)$cls['capacity'] - (int)$cls['enrolled'];
                $full = $spots <= 0;
                $enrolled = ($role !== 'admin') && in_array((int)$cls['id'], $enrolledIds);
                $fillPct = $cls['capacity'] > 0 ? round(($cls['enrolled'] / $cls['capacity']) * 100) : 0;
                $filling = !$full && $fillPct >= 70;
                $color = typeColor($cls['class_name'], $typeColors);
                $tod = timeOfDay($cls['schedule']);

                $rowData = json_encode([
                    'id'           => (int)$cls['id'],
                    'class_name'   => $cls['class_name'],
                    'class_type_id'=> (int)$cls['class_type_id'],
                    'gym_id'       => (int)$cls['gym_id'],
                    'trainer_id'   => (int)$cls['trainer_id'],
                    'schedule'     => $cls['schedule'],
                    'duration_min' => (int)$cls['duration_min'],
                    'capacity'     => (int)$cls['capacity'],
                    'gym_name'     => $cls['gym_name'],
                    'gym_city'     => $cls['gym_city'],
                    'trainer_first'=> $cls['trainer_first'],
                    'trainer_last' => $cls['trainer_last'],
                    'trainer_photo'=> $cls['trainer_photo'] ?? '',
                    'enrolled'     => (int)$cls['enrolled'],
                    'color'        => $color,
                    'avg_rating'   => (float)$cls['avg_rating'],
                    'review_count' => (int)$cls['review_count'],
                    'description'  => $typeDescriptions[$cls['class_name']] ?? '',
                    'is_enrolled'  => $enrolled,
                    'is_full'      => $full,
                    'my_rating'    => isset($cls['my_rating']) && $cls['my_rating'] !== null ? (int)$cls['my_rating'] : null,
                    'my_comment'   => $cls['my_comment'] ?? null,
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            ?>
            <div class="sc-row"
                 id="row-<?= $cls['id'] ?>"
                 data-type="<?= htmlspecialchars($cls['class_name']) ?>"
                 data-trainer-id="<?= (int)$cls['trainer_id'] ?>"
                 data-timeofday="<?= $tod ?>"
                 data-class="<?= htmlspecialchars($rowData) ?>">

                <div class="sc-time-col">
                    <span class="sc-time"><?= $dt->format('H:i') ?></span>
                    <span class="sc-dur"><?= $cls['duration_min'] ?>min</span>
                </div>

                <div class="sc-card" style="--type-color:<?= $color ?>">
                    <div class="sc-card-accent"></div>
                    <div class="sc-card-body">

                        <div class="sc-card-top">
                            <span class="sc-class-name"><?= htmlspecialchars($cls['class_name']) ?></span>
                            <div class="sc-badges" id="badges-<?= $cls['id'] ?>">
                                <?php if ($role !== 'admin' && $enrolled): ?>
                                <span class="sc-badge sc-badge--enrolled"><i class="fa fa-check"></i> Enrolled</span>
                                <?php elseif ($full): ?>
                                <span class="sc-badge sc-badge--full">Full</span>
                                <?php elseif ($filling): ?>
                                <span class="sc-badge sc-badge--filling"><i class="fa fa-fire"></i> Filling up</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="sc-card-meta">
                            <?php if ($cls['trainer_first']): ?>
                            <a href="trainer-profile.php?id=<?= (int)$cls['trainer_id'] ?>"
                               class="sc-trainer-link" onclick="event.stopPropagation()">
                                <img src="<?= htmlspecialchars($cls['trainer_photo'] ?: '../images/profile_pic.webp') ?>"
                                     class="sc-trainer-avatar" alt="">
                                <span><?= htmlspecialchars($cls['trainer_first'] . ' ' . $cls['trainer_last']) ?></span>
                            </a>
                            <?php endif; ?>
                            <span class="sc-location">
                                <i class="fa fa-location-dot"></i>
                                <?= htmlspecialchars($cls['gym_city'] . ' — ' . $cls['gym_name']) ?>
                            </span>
                        </div>

                        <div class="sc-capacity-row">
                            <div class="sc-cap-bar">
                                <div class="sc-cap-fill" id="capfill-<?= $cls['id'] ?>"
                                     style="width:<?= $fillPct ?>%;background:<?= $color ?>"></div>
                            </div>
                            <span class="sc-spots <?= ($spots <= 3 && !$full) ? 'sc-spots--low' : '' ?>"
                                  id="spots-<?= $cls['id'] ?>">
                                <?= $cls['enrolled'] ?>/<?= $cls['capacity'] ?>
                                <?= !$full ? " · $spots left" : '' ?>
                            </span>
                        </div>

                        <?php if (in_array($role, ['admin', 'trainer'], true)): ?>
                        <div class="sc-card-actions" onclick="event.stopPropagation()">
                            <div class="sc-btn-group">
                                <button class="sc-details-btn" onclick="openEnrollmentsModal(<?= $cls['id'] ?>)">
                                    <i class="fa fa-users"></i> <span id="enroll-count-<?= $cls['id'] ?>"><?= (int)$cls['enrolled'] ?></span>
                                </button>
                                <button class="sc-details-btn" onclick="openEditModal(<?= $cls['id'] ?>)">
                                    <i class="fa fa-pen"></i> Edit
                                </button>
                            </div>
                            <form method="POST" class="form-inline"
                                  onsubmit="return confirm('Delete this class? All enrollments will also be removed.')">
                                <input type="hidden" name="_action" value="delete">
                                <input type="hidden" name="target_id" value="<?= $cls['id'] ?>">
                                <button type="submit" class="sc-details-btn sc-details-btn--danger">
                                    <i class="fa fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <div class="sc-card-actions" id="actions-<?= $cls['id'] ?>">
                            <?php if ($role === 'client' && !$enrolled && !$full): ?>
                            <button class="sc-book-btn"
                                    onclick="event.stopPropagation(); bookClass(<?= $cls['id'] ?>, this)">
                                <i class="fa fa-calendar-check"></i> Book
                            </button>
                            <?php elseif ($enrolled): ?>
                            <span class="sc-enrolled-confirm"><i class="fa fa-circle-check"></i> You're enrolled</span>
                            <?php elseif ($full): ?>
                            <span class="sc-full-msg">Class full</span>
                            <?php endif; ?>
                            <button class="sc-details-btn" onclick="event.stopPropagation(); openModal(<?= $cls['id'] ?>)">
                                Details <i class="fa fa-chevron-right"></i>
                            </button>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($usedTypes)): ?>
    <div class="sc-legend">
        <?php foreach ($usedTypes as $tn): ?>
        <span class="sc-legend-item">
            <span class="sc-legend-dot" style="background:<?= typeColor($tn, $typeColors) ?>"></span>
            <?= htmlspecialchars($tn) ?>
        </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php
    return ob_get_clean();
};

if ($role === 'admin') {
    $classesForJS = $allClasses
        ? array_combine(array_column($allClasses, 'id'), $allClasses)
        : new stdClass();
} else {
    $classesForJS = $allClasses ? array_combine(
        array_column($allClasses, 'id'),
        array_map(function($c) use ($typeColors, $typeDescriptions, $enrolledIds) {
            return [
                'id'           => (int)$c['id'],
                'class_name'   => $c['class_name'],
                'class_type_id'=> (int)$c['class_type_id'],
                'gym_id'       => (int)$c['gym_id'],
                'color'        => $typeColors[$c['class_name']] ?? '#888',
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
                'is_enrolled'  => in_array((int)$c['id'], $enrolledIds),
                'is_full'      => ((int)$c['capacity'] - (int)$c['enrolled']) <= 0,
                'my_rating'    => isset($c['my_rating']) ? (int)$c['my_rating'] : null,
                'my_comment'   => $c['my_comment'] ?? null,
            ];
        }, $allClasses)
    ) : new stdClass();
}

if (!empty($_GET['ajax'])) {
    $html = $buildDynamicHTML();
    header('Content-Type: application/json');
    echo json_encode([
        'weekOffset'     => $weekOffset,
        'weekLabel'      => $weekMon->format('M j') . ' – ' . $weekSun->format('M j, Y'),
        'defaultDay'     => $defaultDay,
        'totalClasses'   => $totalClasses,
        'totalSpots'     => $totalSpots,
        'totalEnrolled'  => $totalEnrolled,
        'classes'        => $classesForJS,
        'filterTypes'    => $weekTypes,
        'filterTrainers' => $weekTrainers,
        'html'           => $html,
    ]);
    exit;
}
drawDashHeader($session, $db, 'schedule', ['schedule']);
drawSchedulePage($session, $db, $role ?? '', $weekOffset, $weekMon, $weekSun, $weekTypes, $weekTrainers, $totalClasses, $totalSpots, $totalEnrolled, $classTypes, $gymList, $trainers, $buildDynamicHTML, $defaultDay, $classesForJS);
