<?php function drawDashboardPage(
        Session $session,
        PDO     $db,
        string  $role,
        array   $user,
        array   $clientData = [],
        array   $upcomingClasses = [],
        array   $recentClasses = [],
        array   $recentVisits = [],
        int     $totalVisits = 0,
        int     $totalClasses = 0,
        float   $totalHours = 0,
        int     $earnedBadgeCount = 0,
        array   $displayBadges = [],
        array   $dashClassesForSC = [],
        array   $trainerClasses = [],
        int     $totalStudents = 0,
        int     $classesTaught = 0,
        string  $avgRating = '—',
        array   $recentStudents = [],
        array   $specializations = [],
        int     $totalMembers = 0,
        int     $totalTrainers = 0,
        int     $totalGyms = 0,
        int     $unavailableEquip = 0,
        int     $upcomingClassesCount = 0,
        int     $visitsThisWeek = 0,
        int     $newMemberships = 0,
        array   $popularClasses = [],
        array   $recentReviews = [],
        array   $gymStats = [],
        array   $nextClasses = [],
        int     $classCredits = 0,
        string  $memberTag = ''
): void
{ ?>

    <main class="<?= $role === 'admin' ? 'admin-page' : 'dashboard-page dashboard-' . $role ?>">

        <div class="dash-header">
            <div class="dash-greeting">
                <span class="dash-role-tag"><?= $role === 'client' && $memberTag ? htmlspecialchars($memberTag) : strtoupper($role) ?></span>
                <h1>Welcome back, <span class="dash-name"><?= htmlspecialchars($user['first_name']) ?></span></h1>
                <p class="dash-date"><?= date('l, F j, Y') ?></p>
            </div>
            <?php if ($role === 'client'): ?>
                <div class="dash-credits-badge">
                    <i class="fa fa-ticket"></i>
                    <div>
                        <span class="dash-credits-count" id="dash-credits-value"><?= $classCredits ?></span>
                        <span class="dash-credits-label">Credits</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($role === 'client'): ?>

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
                            <div class="dash-empty"><span><i class="fa fa-dumbbell"></i></span>
                                <p>No past classes yet.</p></div>
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
                                            <i class="fa fa-star<?= $i <= $cls['my_rating'] ? '' : '-o' ?>"></i>
                                        <?php endfor; ?>
                                    </span>
                                            <?php else: ?>
                                                <span class="review-badge review-badge--pending"><i
                                                            class="fa fa-pen"></i> Review</span>
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
                            <div class="dash-empty"><span><i class="fa fa-landmark"></i></span>
                                <p>No visits recorded yet.</p></div>
                        <?php else: ?>
                            <ul class="dash-visit-list">
                                <?php foreach ($recentVisits as $visit):
                                    $inDt = new DateTime($visit['checked_in']);
                                    $outDt = $visit['checked_out'] ? new DateTime($visit['checked_out']) : null;
                                    $dur = $outDt ? round(($outDt->getTimestamp() - $inDt->getTimestamp()) / 60) . ' min' : 'Active';
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
                        <a href="../../actions/edit-profile.php" class="dash-link-small">Edit →</a>
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
                            <span class="metric-value"><?= htmlspecialchars($clientData['archetype'] ?? '—') ?></span>
                        </div>
                        <div class="metric-card">
                            <label class="metric-label">Home Gym</label>
                            <span class="metric-value"><?= $clientData['gym_name'] ? htmlspecialchars($clientData['gym_city'] . ' — ' . $clientData['gym_name']) : '—' ?></span>
                        </div>
                    </div>
                </section>

                <section class="dash-card">
                    <div class="dash-card-header">
                        <h2><i class="fa fa-medal"></i> My Badges</h2>
                        <a href="../../actions/edit-profile.php#badges" class="dash-link-small">Manage →</a>
                    </div>
                    <?php if (empty($displayBadges)): ?>
                        <div class="dash-empty"><span><i class="fa fa-bullseye"></i></span>
                            <p>Keep training to earn badges!</p></div>
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

            </div>

        <?php endif; ?>


        <?php if ($role === 'trainer'): ?>

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
                        <a href="schedule.php" class="dash-link-small">See Schedule →</a>
                    </div>
                    <?php if (empty($trainerClasses)): ?>
                        <div class="dash-empty"><span><i class="fa fa-inbox"></i></span>
                            <p>No upcoming classes scheduled.</p></div>
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
                        <div class="dash-empty"><span><i class="fa fa-user"></i></span>
                            <p>No students enrolled yet.</p></div>
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

        <?php endif; ?>


        <?php if ($role === 'admin'): ?>

            <div class="dash-stats-row dash-stats-row--admin">
                <div class="dash-stat-card">
                    <span class="dash-stat-icon"><i class="fa fa-users"></i></span>
                    <div><span class="dash-stat-value"><?= $totalMembers ?></span><span
                                class="dash-stat-label">Members</span></div>
                </div>
                <div class="dash-stat-card">
                    <span class="dash-stat-icon"><i class="fa fa-chalkboard-teacher"></i></span>
                    <div><span class="dash-stat-value"><?= $totalTrainers ?></span><span class="dash-stat-label">Trainers</span>
                    </div>
                </div>
                <div class="dash-stat-card">
                    <span class="dash-stat-icon"><i class="fa fa-calendar-days"></i></span>
                    <div><span class="dash-stat-value"><?= $upcomingClassesCount ?></span><span class="dash-stat-label">Upcoming Classes</span>
                    </div>
                </div>
                <div class="dash-stat-card">
                    <span class="dash-stat-icon"><i class="fa fa-location-dot"></i></span>
                    <div><span class="dash-stat-value"><?= $totalGyms ?></span><span class="dash-stat-label">Gym Locations</span>
                    </div>
                </div>
                <div class="dash-stat-card">
                    <span class="dash-stat-icon"><i class="fa fa-rotate"></i></span>
                    <div><span class="dash-stat-value"><?= $visitsThisWeek ?></span><span class="dash-stat-label">Visits This Week</span>
                    </div>
                </div>
                <div class="dash-stat-card">
                    <span class="dash-stat-icon"><i class="fa fa-user-plus"></i></span>
                    <div><span class="dash-stat-value"><?= $newMemberships ?></span><span class="dash-stat-label">New Members (30d)</span>
                    </div>
                </div>
            </div>

            <div class="admin-mgmt-grid">
                <a href="/pages/admin-members.php" class="admin-mgmt-card">
                    <i class="fa fa-users"></i><strong>Members</strong>
                    <span>Create, edit and remove member accounts</span>
                </a>
                <a href="/pages/trainers.php" class="admin-mgmt-card">
                    <i class="fa fa-chalkboard-teacher"></i><strong>Trainers</strong>
                    <span>Manage trainers, specializations and gyms</span>
                </a>
                <a href="/pages/schedule.php" class="admin-mgmt-card">
                    <i class="fa fa-calendar-days"></i><strong>Classes</strong>
                    <span>Create and edit the class catalog</span>
                </a>
                <a href="/pages/equipment.php" class="admin-mgmt-card">
                    <i class="fa fa-dumbbell"></i><strong>Equipment</strong>
                    <span>Add items and toggle availability</span>
                </a>
                <a href="/pages/locations.php" class="admin-mgmt-card">
                    <i class="fa fa-location-dot"></i><strong>Locations</strong>
                    <span>Add and edit gym locations</span>
                </a>
            </div>

            <div class="dash-grid-3">

                <section class="dash-card">
                    <div class="dash-card-header"><h2><i class="fa fa-building"></i> Gym Locations</h2></div>
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
                        <?php if (empty($gymStats)): ?>
                            <li class="dash-empty-item">No gyms registered.</li>
                        <?php endif; ?>
                    </ul>
                </section>

                <section class="dash-card">
                    <div class="dash-card-header"><h2><i class="fa fa-fire"></i> Popular Classes</h2></div>
                    <?php if (empty($popularClasses)): ?>
                        <div class="dash-empty"><span><i class="fa fa-chart-bar"></i></span>
                            <p>No data yet.</p></div>
                    <?php else: ?>
                        <?php $maxEnroll = max(array_column($popularClasses, 'enrollments')); ?>
                        <ul class="dash-popular-list">
                            <?php foreach ($popularClasses as $i => $pc):
                                $pct = $maxEnroll > 0 ? round(($pc['enrollments'] / $maxEnroll) * 100) : 0; ?>
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
                        <h2><i class="fa fa-star"></i> Class Reviews</h2>
                        <a href="/pages/schedule.php" class="dash-card-link">Manage</a>
                    </div>
                    <?php if (empty($recentReviews)): ?>
                        <div class="dash-empty"><span><i class="fa fa-comments"></i></span>
                            <p>No reviews yet.</p></div>
                    <?php else: ?>
                        <ul class="dash-review-list">
                            <?php foreach ($recentReviews as $rv): ?>
                                <li class="dash-review-item">
                                    <div class="dash-review-header">
                                        <span class="dash-review-user">@<?= htmlspecialchars($rv['username']) ?></span>
                                        <span class="dash-review-class"><?= htmlspecialchars($rv['class_name']) ?></span>
                                        <span class="dash-review-stars">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <i class="fa fa-star"
                                       style="color:<?= $s <= $rv['rating'] ? '#f59e0b' : '#333' ?>;font-size:.7rem"></i>
                                <?php endfor; ?>
                            </span>
                                    </div>
                                    <?php if ($rv['comment']): ?>
                                        <p class="dash-review-comment"><?= htmlspecialchars($rv['comment']) ?></p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

            </div>

            <?php if (!empty($nextClasses)): ?>
                <section class="dash-card dash-card--mt">
                    <div class="dash-card-header">
                        <h2><i class="fa fa-calendar-check"></i> Upcoming Classes</h2>
                        <a href="/pages/schedule.php" class="dash-card-link">Manage</a>
                    </div>
                    <div class="admin-table-wrap admin-table-wrap--transparent">
                        <table class="admin-table">
                            <thead>
                            <tr>
                                <th>Type</th>
                                <th>Gym</th>
                                <th>Trainer</th>
                                <th>Date &amp; Time</th>
                                <th>Enrolled</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($nextClasses as $nc): ?>
                                <tr>
                                    <td>
                                        <span class="admin-badge admin-badge--active"><?= htmlspecialchars($nc['class_type'] ?? '—') ?></span>
                                    </td>
                                    <td class="admin-dim"><?= $nc['gym_city'] ? htmlspecialchars($nc['gym_city'] . ' — ' . $nc['gym_name']) : '—' ?></td>
                                    <td class="admin-dim"><?= $nc['tr_first'] ? htmlspecialchars($nc['tr_first'] . ' ' . $nc['tr_last']) : '—' ?></td>
                                    <td class="admin-dim"><?= date('d M, H:i', strtotime($nc['schedule'])) ?></td>
                                    <td class="admin-dim"><?= $nc['enrolled'] ?>/<?= $nc['capacity'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($unavailableEquip > 0): ?>
                <div class="admin-alert admin-alert--err admin-alert--mt">
                    <i class="fa fa-triangle-exclamation"></i>
                    <?= $unavailableEquip ?> equipment item<?= $unavailableEquip !== 1 ? 's' : '' ?> marked as
                    unavailable.
                    <a href="/pages/equipment.php" class="admin-alert-link">Review &rarr;</a>
                </div>
            <?php endif; ?>

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
            isClient: <?= $role === 'client' ? 'true' : 'false' ?>,
            weekOffset: 0,
            defaultDay: '',
            classes: <?= json_encode($dashClassesForSC ?: new stdClass()) ?>,
        };
    </script>
    <script src="../../js/schedule.js"></script>
<?php endif; ?>

    <?php drawFooter();
} ?>
