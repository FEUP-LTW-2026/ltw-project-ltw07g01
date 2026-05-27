<?php function drawProfilePage(
    Session $session,
    PDO $db,
    array $user,
    bool $isOwnProfile,
    int $viewId,
    int $currentUserId,
    string $profilePhoto,
    string $fullName,
    string $memberSince,
    string $homeGym,
    string $archetype,
    string $bio,
    string $bodyWeight,
    string $height,
    string $memberTag,
    int $totalVisits,
    int $classesAttended,
    int $earnedBadgeCount,
    array $selectedBadges,
    int $daysRange,
    string $periodLabel,
    array $minutesByDay,
    array $daysLabels,
    string $periodTotalFormatted,
    string $totalWorkoutFormatted,
    string $avgFormatted,
    int $totalCredits = 0
): void { ?>
<?php if (!$isOwnProfile): ?>
<a class="profile-back-btn" href="#" onclick="history.back(); return false;" title="Go back"><i class="fa fa-arrow-left"></i></a>
<?php endif; ?>
<main class="profile-page">
    <aside class="sidebar-container">
        <section class="profile-card">
            <div class="profile-info">
                <figure class="profile-avatar">
                    <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="User Profile Picture">
                </figure>
                <div class="user-meta">
                    <h2 class="user-name"><?= htmlspecialchars($fullName) ?></h2>
                    <p class="user-handle">@<?= htmlspecialchars($user['username']) ?></p>
                    <span class="member-tag"><?= htmlspecialchars($memberTag) ?></span>
                </div>
            </div>

            <div class="user-identity">
                <span class="archetype-tag"><?= htmlspecialchars($archetype) ?></span>
                <?php if ($bio): ?>
                    <p class="user-bio"><?= nl2br(htmlspecialchars($bio)) ?></p>
                <?php else: ?>
                    <p class="user-bio user-bio--empty">No bio yet.</p>
                <?php endif; ?>
            </div>
        </section>
        <?php if ($isOwnProfile): ?>
        <div class="membership-actions">
            <a href="../pages/membership.php" class="btn-update-membership">
                <i class="fa fa-arrow-up-right-dots"></i> Update Membership
            </a>
            <?php if ($memberTag !== 'NO MEMBERSHIP'): ?>
            <button class="btn-cancel-membership" id="btnCancelSub" onclick="cancelSubscription()">
                <i class="fa fa-ban"></i> Cancel Membership
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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
                <span class="detail-label">Home Gym</span>
                <p class="detail-value"><?= htmlspecialchars($homeGym) ?></p>
            </div>
            <div class="detail-item">
                <span class="detail-label">Class Credits</span>
                <p class="detail-value"><?= $totalCredits ?></p>
            </div>
        </div>

        <div class="metrics-section">
            <h3>MY METRICS</h3>
            <div class="metrics-grid">
                <div class="metric-card">
                    <span class="metric-label">Body Weight</span>
                    <span class="metric-value"><?= htmlspecialchars((string)$bodyWeight) ?></span>
                </div>
                <div class="metric-card">
                    <span class="metric-label">Height</span>
                    <span class="metric-value"><?= htmlspecialchars((string)$height) ?></span>
                </div>
            </div>
        </div>

        <div class="hall-of-fame">
            <h3>HALL OF FAME</h3>
            <div class="stats-row">
                <div class="stat-box">
                    <span class="stat-number"><?= $totalVisits ?></span>
                    <span class="stat-label">LIFE TIME VISITS</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?= $classesAttended ?></span>
                    <span class="stat-label">CLASSES ATTENDED</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?= $earnedBadgeCount ?></span>
                    <span class="stat-label">EARNED BADGES</span>
                </div>
            </div>

            <div class="badge-container">
                <?php foreach ($selectedBadges as $badge): ?>
                    <span class="badge" title="<?= htmlspecialchars($badge['title']) ?>">
                        <?= $badge['icon'] ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stats-section">
            <header class="stats-header">
                <div class="header-titles">
                    <h3>TIME SPENT TRAINING</h3>
                    <p class="subtitle">Calculated via QR Check-in/out</p>
                </div>
                <form class="stats-filter-form" method="get">
                    <label for="stats-range" class="stats-filter-label">Period</label>
                    <select id="stats-range" name="days" class="stats-filter">
                        <option value="7" <?= $daysRange === 7 ? 'selected' : '' ?>>Last 7 days</option>
                        <option value="30" <?= $daysRange === 30 ? 'selected' : '' ?>>Last 30 days</option>
                    </select>
                </form>
                <div class="chart-badge">
                    <span class="chart-badge-label">Total Gym Time</span>
                    <strong><?= htmlspecialchars($totalWorkoutFormatted) ?></strong>
                </div>
            </header>

            <div class="chart-container">
                <div class="chart" id="profileChart">
                    <?php foreach ($minutesByDay as $day => $mins):
                        $barHeight = $mins > 0 ? min(320, max(25, $mins * 2)) : 0;
                        $time = formatMinutes($mins);
                    ?>
                    <div class="bar-group">
                        <div class="bar" style="height: <?= $barHeight ?>px;" data-time="<?= htmlspecialchars($time) ?>"></div>
                        <span class="day-label"><?= htmlspecialchars($daysLabels[$day]) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="chart-legend">
                    <div class="legend-item">
                        <span class="legend-label" id="chartPeriodLabel"><?= htmlspecialchars($periodLabel) ?> Total: </span>
                        <strong id="chartPeriodTotal"><?= htmlspecialchars($periodTotalFormatted) ?></strong>
                    </div>
                    <div class="legend-item">
                        <span class="legend-label">Daily Avg: </span>
                        <strong id="chartAvg"><?= htmlspecialchars($avgFormatted) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isOwnProfile): ?>
        <div class="profile-actions">
            <a href="../actions/edit-profile.php" class="btn-edit-profile">Edit Profile</a>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
(function () {
    var viewId = <?= json_encode($viewId !== $currentUserId ? $viewId : null) ?>;

    function updateChart(days) {
        var url = 'profile.php?ajax=chart&days=' + days + (viewId ? '&id=' + viewId : '');
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var chart = document.getElementById('profileChart');
                chart.innerHTML = '';
                data.minutesByDay.forEach(function (mins, i) {
                    var h = mins > 0 ? Math.min(320, Math.max(25, mins * 2)) : 0;
                    var group = document.createElement('div');
                    group.className = 'bar-group';
                    var bar = document.createElement('div');
                    bar.className = 'bar';
                    bar.style.height = h + 'px';
                    bar.dataset.time = data.times[i];
                    var label = document.createElement('span');
                    label.className = 'day-label';
                    label.textContent = data.labels[i];
                    group.appendChild(bar);
                    group.appendChild(label);
                    chart.appendChild(group);
                });
                document.getElementById('chartPeriodLabel').textContent = data.periodLabel + ' Total: ';
                document.getElementById('chartPeriodTotal').textContent = data.periodTotal;
                document.getElementById('chartAvg').textContent = data.avg;
            });
    }

    document.getElementById('stats-range').addEventListener('change', function () {
        updateChart(this.value);
    });

    window.cancelSubscription = function () {
        if (!confirm('Are you sure you want to cancel your membership? Your gym plan will be deactivated immediately.')) return;
        var btn = document.getElementById('btnCancelSub');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Cancelling…'; }
        fetch('profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ajax=1&action=cancel_subscription',
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-ban"></i> Cancel Membership'; }
                return;
            }
            if (btn) btn.remove();
            var tagEl = document.querySelector('.member-tag');
            if (tagEl) tagEl.textContent = 'NO MEMBERSHIP';
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-ban"></i> Cancel Membership'; }
        });
    };
}());
</script>
<?php drawFooter();
} ?>
