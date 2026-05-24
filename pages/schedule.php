<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();
require_once('../database/connection.db.php');
require_once('../templates/common.tpl.php');
$db = getDatabaseConnection();


$userId = $session->isLoggedIn() ? (int)$session->getId() : 3;
$role = null;
$user = null;
$profilePhoto = '../images/profile_pic.webp';
$fullName = '';

if ($userId) {
    foreach (['admins' => 'admin', 'trainers' => 'trainer', 'clients' => 'client'] as $tbl => $r) {
        $s = $db->prepare("SELECT 1 FROM $tbl WHERE user_id = :id");
        $s->execute([':id' => $userId]);
        if ($s->fetch()) { $role = $r; break; }
    }
    if ($role) {
        $s = $db->prepare('SELECT username, first_name, last_name, profile_photo FROM users WHERE id = :id');
        $s->execute([':id' => $userId]);
        $user = $s->fetch();
        $profilePhoto = $user['profile_photo'] ?? $profilePhoto;
        $fullName = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
    }
}
$profileUrl = ($role === 'trainer') ? 'trainer-profile.php?id=' . $userId : 'profile.php';

// enroll(POST)
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

    $db->prepare('INSERT INTO client_classes (client_id, class_id) VALUES (?,?)')->execute([$userId, $classId]);
    $newEnrolled = (int)$cl['enrolled'] + 1;
    echo json_encode([
        'ok' => true,
        'enrolled' => $newEnrolled,
        'capacity' => (int)$cl['capacity'],
        'spots' => (int)$cl['capacity'] - $newEnrolled,
    ]);
    exit;
}

// week computaton 
$weekOffset = isset($_GET['w']) ? max(-2, min(8, (int)$_GET['w'])) : 0;
$today = new DateTime('today');
$weekMon = new DateTime('monday this week');
$weekMon->modify("+{$weekOffset} weeks");

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

//fetch week classes
$stmt = $db->prepare('
    SELECT cl.id, ct.name AS class_name, cl.schedule, cl.duration_min, cl.capacity,
           cl.trainer_id,
           gl.name AS gym_name, gl.city AS gym_city,
           u.first_name AS trainer_first, u.last_name AS trainer_last,
           u.profile_photo AS trainer_photo,
           (SELECT COUNT(*) FROM client_classes cc WHERE cc.class_id = cl.id) AS enrolled,
           ROUND(COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.class_id = cl.id), 0), 1) AS avg_rating,
           (SELECT COUNT(*) FROM reviews r WHERE r.class_id = cl.id) AS review_count
    FROM classes cl
    JOIN class_types ct ON ct.id = cl.class_type_id
    LEFT JOIN gym_locations gl ON gl.id = cl.gym_id
    LEFT JOIN trainers t ON t.user_id = cl.trainer_id
    LEFT JOIN users u ON u.id = t.user_id
    WHERE date(cl.schedule) BETWEEN :start AND :end
    AND (:trainer_filter IS NULL OR cl.trainer_id = :trainer_filter)
    ORDER BY cl.schedule ASC
');
$stmt->execute([
    ':start'          => $weekMon->format('Y-m-d'),
    ':end'            => $weekSun->format('Y-m-d'),
    ':trainer_filter' => ($role === 'trainer') ? $userId : null,
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

$totalClasses = count($allClasses);
$totalSpots   = array_sum(array_map(fn($c) => max(0, $c['capacity'] - $c['enrolled']), $allClasses));

$weekTypes = array_values(array_unique(array_column($allClasses, 'class_name')));
sort($weekTypes);
$weekTrainers = [];
foreach ($allClasses as $c) {
    if ($c['trainer_id'] && !isset($weekTrainers[$c['trainer_id']])) {
        $weekTrainers[$c['trainer_id']] = $c['trainer_first'] . ' ' . $c['trainer_last'];
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────
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
    'HIIT'                    => 'High-Intensity Interval Training alternates short bursts of maximum effort with active rest, torching calories and boosting metabolism.',
    'Personal Training'       => 'One-on-one session tailored to your specific goals with dedicated coach guidance and personalised programming.',
    'Spin'                    => 'Indoor cycling with varied speed and resistance simulating climbs, sprints and flat terrain. Great cardio and leg workout.',
    'Strength & Conditioning' => 'Compound lifts and functional movements to build muscle, improve athletic performance and increase overall body strength.',
    'Zumba'                   => 'Dance fitness blending Latin rhythms with easy-to-follow moves. Fun, social and a great full-body workout.',
    'Boxing'                  => 'Technique-driven boxing fundamentals combined with conditioning drills. Builds speed, coordination and cardiovascular fitness.',
];

function typeColor(string $n, array $m): string { return $m[$n] ?? '#888'; }
function timeOfDay(string $schedule): string {
    $h = (int)(new DateTime($schedule))->format('H');
    if ($h >= 6  && $h <= 11) return 'morning';
    if ($h >= 12 && $h <= 16) return 'afternoon';
    return 'evening';
}

//dynamic HTML
$buildDynamicHTML = function() use ($days, $classesByDay, $today, $enrolledIds, $typeColors, $typeDescriptions, $role, $allClasses) {
    $usedTypes = array_unique(array_column($allClasses, 'class_name'));
    sort($usedTypes);
    ob_start();
    ?>

    <!-- Days strip -->
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

    <!-- Day panels -->
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
        <div class="sc-no-classes">
            <i class="fa fa-moon"></i>
            <p>No classes scheduled for this day.</p>
        </div>
        <?php else: ?>

        <div class="sc-filtered-empty" id="fempty-<?= $dayKey ?>" hidden>
            <i class="fa fa-filter"></i>
            <p>No classes match the current filters.</p>
        </div>

        <div class="sc-timeline" id="timeline-<?= $dayKey ?>">
            <?php foreach ($dayClasses as $cls):
                $dt = new DateTime($cls['schedule']);
                $spots = (int)$cls['capacity'] - (int)$cls['enrolled'];
                $full = $spots <= 0;
                $enrolled = in_array((int)$cls['id'], $enrolledIds);
                $fillPct = $cls['capacity'] > 0 ? round(($cls['enrolled'] / $cls['capacity']) * 100) : 0;
                $filling = !$full && $fillPct >= 70;
                $color = typeColor($cls['class_name'], $typeColors);
                $tod = timeOfDay($cls['schedule']);

                $modalData = json_encode([
                    'id'           => (int)$cls['id'],
                    'class_name'   => $cls['class_name'],
                    'color'        => $color,
                    'schedule'     => $cls['schedule'],
                    'duration_min' => (int)$cls['duration_min'],
                    'gym_name'     => $cls['gym_name'],
                    'gym_city'     => $cls['gym_city'],
                    'trainer_id'   => (int)$cls['trainer_id'],
                    'trainer_first'=> $cls['trainer_first'],
                    'trainer_last' => $cls['trainer_last'],
                    'trainer_photo'=> $cls['trainer_photo'] ?? '',
                    'capacity'     => (int)$cls['capacity'],
                    'enrolled'     => (int)$cls['enrolled'],
                    'avg_rating'   => (float)$cls['avg_rating'],
                    'review_count' => (int)$cls['review_count'],
                    'description'  => $typeDescriptions[$cls['class_name']] ?? '',
                    'is_enrolled'  => $enrolled,
                    'is_full'      => $full,
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            ?>
            <div class="sc-row"
                 id="row-<?= $cls['id'] ?>"
                 data-type="<?= htmlspecialchars($cls['class_name']) ?>"
                 data-trainer-id="<?= (int)$cls['trainer_id'] ?>"
                 data-timeofday="<?= $tod ?>"
                 data-class="<?= htmlspecialchars($modalData) ?>">

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
                                <?php if ($enrolled): ?>
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
                               class="sc-trainer-link"
                               onclick="event.stopPropagation()">
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

                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- type legend -->
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

// week data refresh
if (!empty($_GET['ajax'])) {
    $html = $buildDynamicHTML();

    $classesForJS = $allClasses ? array_combine(
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
                'is_enrolled'  => in_array((int)$c['id'], $enrolledIds),
                'is_full'      => ((int)$c['capacity'] - (int)$c['enrolled']) <= 0,
            ];
        }, $allClasses)
    ) : new stdClass();

    header('Content-Type: application/json');
    echo json_encode([
        'weekOffset'     => $weekOffset,
        'weekLabel'      => $weekMon->format('M j') . ' – ' . $weekSun->format('M j, Y'),
        'defaultDay'     => $defaultDay,
        'totalClasses'   => $totalClasses,
        'totalSpots'     => $totalSpots,
        'classes'        => $classesForJS,
        'filterTypes'    => $weekTypes,
        'filterTrainers' => $weekTrainers,
        'html'           => $html,
    ]);
    exit;
}
?>
<?php drawDashHeader($session, $db, 'schedule', ['schedule']); ?>

<!-- filter bar -->
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

    <button class="sc-filter-clear" id="filterClear" hidden>
        <i class="fa fa-xmark"></i> Clear
    </button>
    <span class="sc-filter-count" id="filterCount"></span>
</div>

<!-- main -->
<main class="sc-page <?= $role ? 'sc-has-navbar' : '' ?>">

    <!-- title -->
    <div class="sc-title-row">
        <div>
            <h1 class="sc-title">Schedule</h1>
            <p class="sc-subtitle">Browse and book upcoming fitness classes</p>
        </div>
        <div class="sc-week-stats">
            <div class="sc-stat"><span id="statClasses"><?= $totalClasses ?></span><small>classes</small></div>
            <div class="sc-stat"><span id="statSpots"><?= $totalSpots ?></span><small>spots open</small></div>
        </div>
    </div>

    <!--week navigator -->
    <div class="sc-week-nav">
        <button class="sc-week-arrow <?= $weekOffset <= -2 ? 'sc-week-arrow--disabled' : '' ?>"
                id="scPrevBtn" <?= $weekOffset <= -2 ? 'disabled' : '' ?> aria-label="Previous week">
            <i class="fa fa-chevron-left"></i>
        </button>

        <span class="sc-week-label" id="scWeekLabel">
            <?= $weekMon->format('M j') ?> – <?= $weekSun->format('M j, Y') ?>
        </span>

        <button class="sc-week-today" id="scTodayBtn" <?= $weekOffset === 0 ? 'hidden' : '' ?>>
            Today
        </button>

        <button class="sc-week-arrow <?= $weekOffset >= 8 ? 'sc-week-arrow--disabled' : '' ?>"
                id="scNextBtn" <?= $weekOffset >= 8 ? 'disabled' : '' ?> aria-label="Next week">
            <i class="fa fa-chevron-right"></i>
        </button>
    </div>

    <!-- days strip, panels,legend (dynamic)-->
    <div id="scDynamicContent">
        <?= $buildDynamicHTML() ?>
    </div>

</main>

<!-- detail modal -->
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

<!--JS data bridge -->
<script>
var SC = {
    isClient    : <?= json_encode($role === 'client') ?>,
    weekOffset  : <?= json_encode($weekOffset) ?>,
    defaultDay  : <?= json_encode($defaultDay) ?>,
    classes     : <?= json_encode(
        $allClasses ? array_combine(
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
                    'is_enrolled'  => in_array((int)$c['id'], $enrolledIds),
                    'is_full'      => ((int)$c['capacity'] - (int)$c['enrolled']) <= 0,
                ];
            }, $allClasses)
        ) : new stdClass()
    ) ?>,
};
</script>
<script src="../js/schedule.js"></script>

<?php drawFooter(); ?>
