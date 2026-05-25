<?php
declare(strict_types=1);
require_once(__DIR__ . '/../utils/session.php');
$session = new Session();

require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');

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
    'SELECT ct.id, ct.name
     FROM trainer_specializations ts
     JOIN class_types ct ON ct.id = ts.class_type_id
     WHERE ts.trainer_id = :id
     ORDER BY ct.name'
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

$stmt = $db->prepare('
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
    ORDER BY cl.schedule ASC
');
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
    'Yoga'                    => '#a78bfa',
    'Cycling'                 => '#60a5fa',
    'Pilates'                 => '#f472b6',
    'Personal Training'       => '#34d399',
    'Strength & Conditioning' => '#fbbf24',
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
    ) : new stdClass();
};

$buildScheduleHTML = function() use ($days, $classesByDay, $today, $enrolledIds, $typeColors, $typeDescriptions, $role, $allClasses) {
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
        <button class="sc-day-btn <?= $isToday ? 'sc-day-btn--today' : '' ?> <?= empty($dayClasses) ? 'sc-day-btn--empty' : '' ?>" data-day="<?= $dayKey ?>">
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
            <span class="sc-panel-count" id="count-<?= $dayKey ?>"><?= count($dayClasses) ?> class<?= count($dayClasses) !== 1 ? 'es' : '' ?></span>
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
                $enrolled = in_array((int)$cls['id'], $enrolledIds, true);
                $fillPct = $cls['capacity'] > 0 ? round(((int)$cls['enrolled'] / (int)$cls['capacity']) * 100) : 0;
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
            <div class="sc-row" id="row-<?= $cls['id'] ?>" data-type="<?= htmlspecialchars($cls['class_name']) ?>" data-trainer-id="<?= (int)$cls['trainer_id'] ?>" data-timeofday="<?= $tod ?>" data-class="<?= htmlspecialchars($modalData) ?>">
                <div class="sc-time-col">
                    <span class="sc-time"><?= $dt->format('H:i') ?></span>
                    <span class="sc-dur"><?= (int)$cls['duration_min'] ?>min</span>
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
                            <span class="sc-location">
                                <i class="fa fa-location-dot"></i>
                                <?= htmlspecialchars($cls['gym_city'] . ' - ' . $cls['gym_name']) ?>
                            </span>
                        </div>
                        <div class="sc-capacity-row">
                            <div class="sc-cap-bar">
                                <div class="sc-cap-fill" id="capfill-<?= $cls['id'] ?>" style="width:<?= $fillPct ?>%;background:<?= $color ?>"></div>
                            </div>
                            <span class="sc-spots <?= ($spots <= 3 && !$full) ? 'sc-spots--low' : '' ?>" id="spots-<?= $cls['id'] ?>">
                                <?= (int)$cls['enrolled'] ?>/<?= (int)$cls['capacity'] ?><?= !$full ? " · $spots left" : '' ?>
                            </span>
                        </div>
                        <div class="sc-card-actions" id="actions-<?= $cls['id'] ?>">
                            <?php if ($role === 'client' && !$enrolled && !$full): ?>
                            <button class="sc-book-btn" onclick="event.stopPropagation(); bookClass(<?= $cls['id'] ?>, this)">
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
?>
<?php drawDashHeader($session, $db, 'profile', ['schedule'], 'trainer-theme profile-body'); ?>

<main class="profile-page">
    <aside class="sidebar-container">
        <section class="profile-card">
            <div class="profile-info">
                <figure class="profile-avatar">
                    <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="Trainer Profile Picture">
                </figure>
                <div class="user-meta">
                    <h2 class="user-name"><?= htmlspecialchars($fullName) ?></h2>
                    <p class="user-handle">@<?= htmlspecialchars($user['username']) ?></p>
                    <span class="member-tag trainer-tag">TRAINER</span>
                </div>
            </div>

            <div class="user-identity">
                <div class="specializations-list">
                    <?php if (empty($specializations)): ?>
                        <span class="specialization-tag specialization-tag--empty">No specializations yet</span>
                    <?php else: ?>
                        <?php foreach ($specializations as $spec): ?>
                            <span class="specialization-tag"><?= htmlspecialchars($spec['name']) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
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
                <span class="detail-label">Gym Location<?= count($homeGyms) > 1 ? 's' : '' ?></span>
                <p class="detail-value">
                    <?= $homeGyms ? htmlspecialchars(implode(' · ', $homeGyms)) : 'No gym assigned' ?>
                </p>
            </div>
        </div>

        <?php if (!empty($certifications)): ?>
        <div class="certifications-section">
            <h3>CERTIFICATIONS</h3>
            <div class="certifications-grid">
                <?php foreach ($certifications as $cert): ?>
                    <div class="certification-card">
                        <span class="cert-icon"><i class="fa fa-certificate"></i></span>
                        <span class="cert-name"><?= htmlspecialchars($cert) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="certifications-section">
            <h3>CERTIFICATIONS</h3>
            <p class="no-certifications">No certifications listed yet.</p>
        </div>
        <?php endif; ?>

        <section class="trainer-schedule-section">
            <div class="sc-title-row trainer-schedule-title-row">
                <div>
                    <h3 class="trainer-schedule-title">SCHEDULE</h3>
                    <p class="sc-subtitle">Classes taught by <?= htmlspecialchars($fullName) ?></p>
                </div>
                <div class="sc-week-stats trainer-schedule-stats">
                    <div class="sc-stat"><span id="statClasses"><?= $totalClasses ?></span><small>classes</small></div>
                    <div class="sc-stat"><span id="statSpots"><?= $totalSpots ?></span><small>spots open</small></div>
                </div>
            </div>

            <div class="sc-filter-bar trainer-profile-filter-bar" id="scFilterBar">
                <span class="sc-filter-label"><i class="fa fa-sliders"></i> Filter</span>
                <select class="sc-filter-select" id="filterType">
                    <option value="">All types</option>
                    <?php foreach ($weekTypes as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="sc-filter-select" id="filterTrainer" hidden>
                    <option value="">All trainers</option>
                    <option value="<?= $userId ?>"><?= htmlspecialchars($fullName) ?></option>
                </select>
                <select class="sc-filter-select" id="filterTime">
                    <option value="">Any time</option>
                    <option value="morning">Morning (6-11h)</option>
                    <option value="afternoon">Afternoon (12-16h)</option>
                    <option value="evening">Evening (17h+)</option>
                </select>
                <button class="sc-filter-clear" id="filterClear" hidden>
                    <i class="fa fa-xmark"></i> Clear
                </button>
                <span class="sc-filter-count" id="filterCount"></span>
            </div>

            <div class="sc-week-nav">
                <button class="sc-week-arrow <?= $weekOffset <= -2 ? 'sc-week-arrow--disabled' : '' ?>"
                        id="scPrevBtn" <?= $weekOffset <= -2 ? 'disabled' : '' ?> aria-label="Previous week">
                    <i class="fa fa-chevron-left"></i>
                </button>
                <span class="sc-week-label" id="scWeekLabel">
                    <?= $weekMon->format('M j') ?> - <?= $weekSun->format('M j, Y') ?>
                </span>
                <button class="sc-week-today" id="scTodayBtn" <?= $weekOffset === 0 ? 'hidden' : '' ?>>
                    Today
                </button>
                <button class="sc-week-arrow <?= $weekOffset >= 8 ? 'sc-week-arrow--disabled' : '' ?>"
                        id="scNextBtn" <?= $weekOffset >= 8 ? 'disabled' : '' ?> aria-label="Next week">
                    <i class="fa fa-chevron-right"></i>
                </button>
            </div>

            <div id="scDynamicContent">
                <?= $buildScheduleHTML() ?>
            </div>
        </section>

        <?php if ($isOwnProfile): ?>
        <div class="profile-actions">
            <a href="../actions/edit-trainer-profile.php" class="btn-edit-profile">Edit Profile</a>
        </div>
        <?php endif; ?>
    </div>
</main>

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

<script>
var SC = {
    isClient   : <?= json_encode($role === 'client') ?>,
    weekOffset : <?= json_encode($weekOffset) ?>,
    defaultDay : <?= json_encode($defaultDay) ?>,
    ajaxUrl    : <?= json_encode('trainer-profile.php?id=' . $userId) ?>,
    actionUrl  : '/pages/schedule.php',
    classes    : <?= json_encode($classesForJS()) ?>,
};
</script>
<script src="../js/schedule.js"></script>

<?php drawFooter(); ?>
