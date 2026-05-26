<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();
require_once('../database/connection.db.php');
require_once('../templates/common.tpl.php');
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

// admin: criar / editar / apagar aula
if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_GET['ajax'])) {
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $classTypeId = (int)($_POST['class_type_id'] ?? 0);
        $gymId       = (int)($_POST['gym_id']        ?? 0);
        $trainerId   = (int)($_POST['trainer_id']    ?? 0) ?: null;
        $schedule    = trim($_POST['schedule']        ?? '');
        $duration    = (int)($_POST['duration_min']  ?? 0);
        $capacity    = (int)($_POST['capacity']       ?? 0);
        if ($classTypeId && $gymId && $schedule && $duration > 0 && $capacity > 0) {
            $db->prepare('INSERT INTO classes (class_type_id, gym_id, trainer_id, schedule, duration_min, capacity) VALUES (?,?,?,?,?,?)')
               ->execute([$classTypeId, $gymId, $trainerId, $schedule, $duration, $capacity]);
        }
        header('Location: /pages/schedule.php'); exit;

    } elseif ($action === 'update') {
        $targetId    = (int)($_POST['target_id']     ?? 0);
        $classTypeId = (int)($_POST['class_type_id'] ?? 0);
        $gymId       = (int)($_POST['gym_id']        ?? 0);
        $trainerId   = (int)($_POST['trainer_id']    ?? 0) ?: null;
        $schedule    = trim($_POST['schedule']        ?? '');
        $duration    = (int)($_POST['duration_min']  ?? 0);
        $capacity    = (int)($_POST['capacity']       ?? 0);
        if ($targetId && $classTypeId && $gymId && $schedule && $duration > 0 && $capacity > 0) {
            $db->prepare('UPDATE classes SET class_type_id=?, gym_id=?, trainer_id=?, schedule=?, duration_min=?, capacity=? WHERE id=?')
               ->execute([$classTypeId, $gymId, $trainerId, $schedule, $duration, $capacity, $targetId]);
        }
        header('Location: /pages/schedule.php'); exit;

    } elseif ($action === 'delete') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            $db->prepare('DELETE FROM classes WHERE id=?')->execute([$targetId]);
        }
        header('Location: /pages/schedule.php'); exit;
    }
}

// ajax: review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax']) && ($_POST['action'] ?? '') === 'review' && $role === 'client') {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax']) && ($_POST['action'] ?? '') === 'cancel' && $role === 'client') {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax']) && $role === 'client') {
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

$stmt = $db->prepare('
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
    AND (:trainer_filter IS NULL OR cl.trainer_id = :trainer_filter)
    ORDER BY cl.schedule ASC
');
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
if ($role === 'admin') {
    $classTypes = $db->query('SELECT id, name FROM class_types ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $gymList    = $db->query('SELECT id, name, city FROM gym_locations ORDER BY city, name')->fetchAll(PDO::FETCH_ASSOC);
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
    'Yoga'                    => '#a78bfa',
    'Cycling'                 => '#60a5fa',
    'Pilates'                 => '#f472b6',
    'HIIT'                    => '#fb923c',
    'Personal Training'       => '#34d399',
    'Spin'                    => '#22d3ee',
    'Strength & Conditioning' => '#fbbf24',
    'Zumba'                   => '#a3e635',
    'Boxing'                  => '#f87171',
];

$typeDescriptions = [
    'Yoga'                    => 'A mind-body practice combining physical postures, breathing and meditation to improve flexibility, strength and mental clarity.',
    'Cycling'                 => 'High-energy indoor cycling set to motivating music. Builds cardiovascular endurance and lower-body power.',
    'Pilates'                 => 'Low-impact exercise focusing on core strength, posture and controlled movement. Suitable for all fitness levels.',
    'Personal Training'       => 'One-on-one session tailored to your specific goals with dedicated coach guidance and personalised programming.',
    'Strength & Conditioning' => 'Compound lifts and functional movements to build muscle, improve athletic performance and increase overall body strength.',
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

                        <?php if ($role === 'admin'): ?>
                        <div class="sc-card-actions" onclick="event.stopPropagation()">
                            <button class="sc-details-btn" onclick="openEditModal(<?= $cls['id'] ?>)">
                                <i class="fa fa-pen"></i> Edit
                            </button>
                            <form method="POST" style="display:inline"
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

if (!empty($_GET['ajax'])) {
    $html = $buildDynamicHTML();

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
?>
<?php drawDashHeader($session, $db, 'schedule', $role === 'admin' ? ['schedule', 'admin'] : ['schedule']); ?>

<div class="sc-filter-bar" id="scFilterBar">
    <span class="sc-filter-label"><i class="fa fa-sliders"></i> Filter</span>
    <select class="sc-filter-select" id="filterType">
        <option value="">All types</option>
        <?php foreach ($weekTypes as $t): ?>
        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($role !== 'trainer'): ?>
    <select class="sc-filter-select" id="filterTrainer">
        <option value="">All trainers</option>
        <?php foreach ($weekTrainers as $tid => $tname): ?>
        <option value="<?= $tid ?>"><?= htmlspecialchars($tname) ?></option>
        <?php endforeach; ?>
    </select>
    <?php else: ?>
    <select class="sc-filter-select" id="filterTrainer" hidden></select>
    <?php endif; ?>
    <select class="sc-filter-select" id="filterTime">
        <option value="">Any time</option>
        <option value="morning">Morning (6–11h)</option>
        <option value="afternoon">Afternoon (12–16h)</option>
        <option value="evening">Evening (17h+)</option>
    </select>
    <button class="sc-filter-clear" id="filterClear" hidden><i class="fa fa-xmark"></i> Clear</button>
    <span class="sc-filter-count" id="filterCount"></span>
</div>

<main class="sc-page sc-has-navbar">

    <div class="sc-title-row">
        <?php if ($role === 'admin'): ?>
        <div>
            <h1 class="sc-title">Manage Classes</h1>
            <p class="sc-subtitle">Create, edit and remove classes from the catalog</p>
        </div>
        <div class="sc-week-stats">
            <div class="sc-stat"><span id="statClasses"><?= $totalClasses ?></span><small>classes</small></div>
            <div class="sc-stat"><span id="statSpots"><?= $totalEnrolled ?></span><small>enrolled</small></div>
            <button class="btn-admin-primary" onclick="openEditModal(null)" style="align-self:center">
                <i class="fa fa-plus"></i> New Class
            </button>
        </div>
        <?php else: ?>
        <div>
            <h1 class="sc-title">Schedule</h1>
            <p class="sc-subtitle">Browse and book upcoming fitness classes</p>
        </div>
        <div class="sc-week-stats">
            <div class="sc-stat"><span id="statClasses"><?= $totalClasses ?></span><small>classes</small></div>
            <div class="sc-stat"><span id="statSpots"><?= $totalSpots ?></span><small>spots open</small></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="sc-week-nav">
        <button class="sc-week-arrow <?= $weekOffset <= -2 ? 'sc-week-arrow--disabled' : '' ?>"
                id="scPrevBtn" <?= $weekOffset <= -2 ? 'disabled' : '' ?> aria-label="Previous week">
            <i class="fa fa-chevron-left"></i>
        </button>
        <span class="sc-week-label" id="scWeekLabel">
            <?= $weekMon->format('M j') ?> – <?= $weekSun->format('M j, Y') ?>
        </span>
        <button class="sc-week-today" id="scTodayBtn" <?= $weekOffset === 0 ? 'hidden' : '' ?>>Today</button>
        <button class="sc-week-arrow <?= $weekOffset >= 8 ? 'sc-week-arrow--disabled' : '' ?>"
                id="scNextBtn" <?= $weekOffset >= 8 ? 'disabled' : '' ?> aria-label="Next week">
            <i class="fa fa-chevron-right"></i>
        </button>
    </div>

    <div id="scDynamicContent">
        <?= $buildDynamicHTML() ?>
    </div>

</main>

<?php if ($role === 'admin'): ?>
<!-- modal criar/editar (admin) -->
<div class="sc-modal" id="editModal" aria-hidden="true">
    <div class="sc-modal-backdrop" id="editModalBackdrop"></div>
    <div class="sc-modal-panel" id="editModalPanel">
        <button class="sc-modal-close" id="editModalClose"><i class="fa fa-xmark"></i></button>
        <div class="sc-modal-header" style="--modal-color:var(--accent)">
            <span class="sc-modal-type" id="editModalLabel">New Class</span>
            <h2 class="sc-modal-title">Class Details</h2>
        </div>
        <div class="sc-modal-body">
            <form method="POST" id="editForm">
                <input type="hidden" name="_action" id="editAction" value="create">
                <input type="hidden" name="target_id" id="editTargetId" value="">
                <div class="admin-form-grid" style="margin-bottom:1rem">
                    <div class="admin-field">
                        <label>Class Type</label>
                        <select name="class_type_id" id="editClassType" required>
                            <option value="">— select —</option>
                            <?php foreach ($classTypes as $ct): ?>
                            <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-field">
                        <label>Gym Location</label>
                        <select name="gym_id" id="editGymId" required>
                            <option value="">— select —</option>
                            <?php foreach ($gymList as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['city'] . ' — ' . $g['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-field">
                        <label>Trainer <span style="font-weight:400;text-transform:none">(optional)</span></label>
                        <select name="trainer_id" id="editTrainerId">
                            <option value="">— no trainer —</option>
                            <?php foreach ($trainers as $tr): ?>
                            <option value="<?= $tr['id'] ?>"><?= htmlspecialchars($tr['first_name'] . ' ' . $tr['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-field">
                        <label>Date &amp; Time</label>
                        <input type="datetime-local" name="schedule" id="editSchedule" required>
                    </div>
                    <div class="admin-field">
                        <label>Duration (min)</label>
                        <input type="number" name="duration_min" id="editDuration" min="1" required>
                    </div>
                    <div class="admin-field">
                        <label>Capacity</label>
                        <input type="number" name="capacity" id="editCapacity" min="1" required>
                    </div>
                </div>
            </form>
        </div>
        <div class="sc-modal-footer">
            <button class="sc-modal-book-btn" id="editSubmitBtn" onclick="document.getElementById('editForm').submit()">
                <i class="fa fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<style>
.sc-details-btn--danger { color: #ef4444; border-color: transparent; }
.sc-details-btn--danger:hover { background: rgba(239,68,68,.15); border-color: #ef4444; }
</style>
<?php else: ?>
<!-- modal de detalhe (client/trainer) -->
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
            </div>
        </div>
        <div class="sc-modal-footer" id="scModalFooter"></div>
    </div>
</div>
<?php endif; ?>

<script>
var SC = {
    isClient   : <?= json_encode($role === 'client') ?>,
    weekOffset : <?= json_encode($weekOffset) ?>,
    defaultDay : <?= json_encode($defaultDay) ?>,
    classes    : <?php
    if ($role === 'admin') {
        echo json_encode($allClasses ? array_combine(array_column($allClasses, 'id'), $allClasses) : new stdClass());
    } else {
        echo json_encode($allClasses ? array_combine(
            array_column($allClasses, 'id'),
            array_map(function($c) use ($typeColors, $typeDescriptions, $enrolledIds) {
                return [
                    'id'           => (int)$c['id'],
                    'class_name'   => $c['class_name'],
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
        ) : new stdClass());
    }
    ?>,
};
</script>
<?php if ($role === 'admin'): ?>
<script>
(function () {
    'use strict';

    var dynamicContent  = document.getElementById('scDynamicContent');
    var prevBtn         = document.getElementById('scPrevBtn');
    var nextBtn         = document.getElementById('scNextBtn');
    var todayBtn        = document.getElementById('scTodayBtn');
    var weekLabelEl     = document.getElementById('scWeekLabel');
    var statClassesEl   = document.getElementById('statClasses');
    var statSpotsEl     = document.getElementById('statSpots');
    var filterTypeEl    = document.getElementById('filterType');
    var filterTrainerEl = document.getElementById('filterTrainer');
    var filterTimeEl    = document.getElementById('filterTime');
    var filterClearBtn  = document.getElementById('filterClear');
    var filterCountEl   = document.getElementById('filterCount');

    function selectDay(dayKey) {
        document.querySelectorAll('.sc-day-btn').forEach(function (b) {
            b.classList.toggle('sc-day-btn--active', b.dataset.day === dayKey);
        });
        document.querySelectorAll('.sc-day-panel').forEach(function (p) {
            p.classList.toggle('sc-day-panel--active', p.id === 'panel-' + dayKey);
        });
    }

    function applyFilters() {
        var rows   = document.querySelectorAll('.sc-row');
        var panels = document.querySelectorAll('.sc-day-panel');
        var type    = filterTypeEl.value;
        var trainer = filterTrainerEl ? filterTrainerEl.value : '';
        var time    = filterTimeEl.value;
        var hasFilter = !!(type || trainer || time);
        filterClearBtn.hidden = !hasFilter;
        var visibleTotal = 0;
        rows.forEach(function (row) {
            var show = true;
            if (type && row.dataset.type !== type) show = false;
            if (trainer && row.dataset.trainerId !== trainer) show = false;
            if (time && row.dataset.timeofday !== time) show = false;
            row.classList.toggle('sc-row--hidden', !show);
            if (show) visibleTotal++;
        });
        panels.forEach(function (panel) {
            var dayKey    = panel.id.replace('panel-', '');
            var panelRows = panel.querySelectorAll('.sc-row');
            var visible   = panel.querySelectorAll('.sc-row:not(.sc-row--hidden)').length;
            var emptyEl   = document.getElementById('fempty-' + dayKey);
            var countEl   = document.getElementById('count-' + dayKey);
            if (emptyEl) emptyEl.hidden = !(panelRows.length > 0 && visible === 0);
            if (countEl) countEl.textContent = visible + ' class' + (visible !== 1 ? 'es' : '');
        });
        filterCountEl.textContent = hasFilter ? visibleTotal + ' result' + (visibleTotal !== 1 ? 's' : '') : '';
    }

    [filterTypeEl, filterTrainerEl, filterTimeEl].forEach(function (el) {
        if (el) el.addEventListener('change', applyFilters);
    });
    filterClearBtn.addEventListener('click', function () {
        filterTypeEl.value = '';
        if (filterTrainerEl) filterTrainerEl.value = '';
        filterTimeEl.value = '';
        applyFilters();
    });

    function updateWeekNav(offset) {
        prevBtn.disabled = offset <= -2;
        prevBtn.classList.toggle('sc-week-arrow--disabled', offset <= -2);
        nextBtn.disabled = offset >= 8;
        nextBtn.classList.toggle('sc-week-arrow--disabled', offset >= 8);
        if (todayBtn) todayBtn.hidden = (offset === 0);
    }

    function updateFilterOptions(types, trainers) {
        var curType    = filterTypeEl.value;
        var curTrainer = filterTrainerEl ? filterTrainerEl.value : '';
        filterTypeEl.innerHTML = '<option value="">All types</option>';
        types.forEach(function (t) {
            var opt = document.createElement('option');
            opt.value = t; opt.textContent = t;
            if (t === curType) opt.selected = true;
            filterTypeEl.appendChild(opt);
        });
        if (filterTrainerEl) {
            filterTrainerEl.innerHTML = '<option value="">All trainers</option>';
            Object.keys(trainers).forEach(function (tid) {
                var opt = document.createElement('option');
                opt.value = tid; opt.textContent = trainers[tid];
                if (tid === curTrainer) opt.selected = true;
                filterTrainerEl.appendChild(opt);
            });
        }
    }

    function loadWeek(offset) {
        dynamicContent.classList.add('sc-loading');
        prevBtn.disabled = true; nextBtn.disabled = true;
        fetch('schedule.php?ajax=1&w=' + offset)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                SC.weekOffset = data.weekOffset;
                SC.classes    = data.classes;
                SC.defaultDay = data.defaultDay;
                weekLabelEl.textContent   = data.weekLabel;
                statClassesEl.textContent = data.totalClasses;
                statSpotsEl.textContent   = data.totalEnrolled;
                dynamicContent.innerHTML  = data.html;
                dynamicContent.classList.remove('sc-loading');
                updateFilterOptions(data.filterTypes, data.filterTrainers);
                updateWeekNav(data.weekOffset);
                selectDay(data.defaultDay);
                applyFilters();
            })
            .catch(function () {
                dynamicContent.classList.remove('sc-loading');
                updateWeekNav(SC.weekOffset);
            });
    }

    prevBtn.addEventListener('click', function () { loadWeek(SC.weekOffset - 1); });
    nextBtn.addEventListener('click', function () { loadWeek(SC.weekOffset + 1); });
    if (todayBtn) todayBtn.addEventListener('click', function () { loadWeek(0); });

    dynamicContent.addEventListener('click', function (e) {
        var dayBtn = e.target.closest('.sc-day-btn');
        if (dayBtn) { selectDay(dayBtn.dataset.day); return; }
    });

    selectDay(SC.defaultDay);
    applyFilters();

    var editModal    = document.getElementById('editModal');
    var editBackdrop = document.getElementById('editModalBackdrop');
    var editClose    = document.getElementById('editModalClose');

    window.openEditModal = function (classId) {
        var cls = classId ? SC.classes[classId] : null;
        document.getElementById('editModalLabel').textContent = cls ? 'Edit Class' : 'New Class';
        document.getElementById('editAction').value    = cls ? 'update' : 'create';
        document.getElementById('editTargetId').value  = cls ? classId : '';
        document.getElementById('editClassType').value = cls ? cls.class_type_id : '';
        document.getElementById('editGymId').value     = cls ? cls.gym_id : '';
        document.getElementById('editTrainerId').value = cls ? (cls.trainer_id || '') : '';
        document.getElementById('editDuration').value  = cls ? cls.duration_min : '';
        document.getElementById('editCapacity').value  = cls ? cls.capacity : '';
        if (cls && cls.schedule) {
            var d = new Date(cls.schedule);
            var pad = function(n) { return String(n).padStart(2, '0'); };
            document.getElementById('editSchedule').value =
                d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate())
                + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        } else {
            document.getElementById('editSchedule').value = '';
        }
        editModal.setAttribute('aria-hidden', 'false');
        editModal.classList.add('sc-modal--open');
        document.body.style.overflow = 'hidden';
    };

    function closeEditModal() {
        editModal.setAttribute('aria-hidden', 'true');
        editModal.classList.remove('sc-modal--open');
        document.body.style.overflow = '';
    }

    editClose.addEventListener('click', closeEditModal);
    editBackdrop.addEventListener('click', closeEditModal);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeEditModal(); });
})();
</script>
<?php else: ?>
<script src="../js/schedule.js"></script>
<?php endif; ?>

<?php drawFooter(); ?>
