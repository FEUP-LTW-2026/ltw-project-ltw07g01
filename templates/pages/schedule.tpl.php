<?php function drawSchedulePage(
        Session  $session,
        PDO      $db,
        string   $role,
        int      $weekOffset,
        DateTime $weekMon,
        DateTime $weekSun,
        array    $weekTypes,
        array    $weekTrainers,
        int      $totalClasses,
        int      $totalSpots,
        int      $totalEnrolled,
        array    $classTypes,
        array    $gymList,
        array    $trainers,
        callable $buildDynamicHTML,
        string   $defaultDay = '',
        array    $classesForJS = [],
        int      $classCredits = 0
): void
{ ?>

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
                    <h1 class="sc-title"><i class="fa fa-calendar-days"></i> Manage Classes</h1>
                    <p class="sc-subtitle">Create, edit and remove classes from the catalog</p>
                </div>
                <div class="sc-week-stats">
                    <div class="sc-stat"><span id="statClasses"><?= $totalClasses ?></span><small>classes</small></div>
                    <div class="sc-stat"><span id="statSpots"><?= $totalEnrolled ?></span><small>enrolled</small></div>
                    <button class="btn-admin-primary sc-create-class-btn" onclick="openEditModal(null)">
                        <i class="fa fa-plus"></i> New Class
                    </button>
                </div>
            <?php elseif ($role === 'trainer'): ?>
                <div>
                    <h1 class="sc-title">My Schedule</h1>
                    <p class="sc-subtitle">Create, edit and remove your assigned classes</p>
                </div>
                <div class="sc-week-stats">
                    <div class="sc-stat"><span id="statClasses"><?= $totalClasses ?></span><small>classes</small></div>
                    <div class="sc-stat"><span id="statSpots"><?= $totalSpots ?></span><small>spots open</small></div>
                    <button class="btn-admin-primary sc-create-class-btn" onclick="openEditModal(null)">
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
                    <div class="sc-stat"><span id="dash-credits-value"><?= $classCredits ?></span><small>credits</small>
                    </div>
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

    <?php if (in_array($role, ['admin', 'trainer'], true)): ?>
    <div class="sc-modal class-editor-modal" id="editModal" aria-hidden="true">
        <div class="sc-modal-backdrop" id="editModalBackdrop"></div>
        <div class="sc-modal-panel" id="editModalPanel">
            <button class="sc-modal-close" id="editModalClose"><i class="fa fa-xmark"></i></button>
            <div class="sc-modal-header">
                <span class="sc-modal-type" id="editModalLabel">New Class</span>
                <h2 class="sc-modal-title">Class Details</h2>
            </div>
            <div class="sc-modal-body">
                <form method="POST" id="editForm">
                    <input type="hidden" name="_action" id="editAction" value="create">
                    <input type="hidden" name="target_id" id="editTargetId" value="">
                    <div class="class-editor-fields">
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
                        <?php if ($role === 'admin'): ?>
                            <div class="admin-field">
                                <label>Trainer <span class="admin-optional">(optional)</span></label>
                                <select name="trainer_id" id="editTrainerId">
                                    <option value="">— no trainer —</option>
                                    <?php foreach ($trainers as $tr): ?>
                                        <option value="<?= $tr['id'] ?>"><?= htmlspecialchars($tr['first_name'] . ' ' . $tr['last_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
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
                        <div class="admin-field">
                            <label>Description <span class="admin-optional">(optional)</span></label>
                            <textarea name="description" id="editDescription" rows="3" maxlength="500"
                                      placeholder="Brief description of the class…"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="sc-modal-footer">
                <button class="sc-modal-book-btn" id="editSubmitBtn"
                        onclick="document.getElementById('editForm').submit()">
                    <i class="fa fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>

    <div class="sc-modal" id="enrollModal" aria-hidden="true">
        <div class="sc-modal-backdrop" id="enrollModalBackdrop"></div>
        <div class="sc-modal-panel" id="enrollModalPanel">
            <button class="sc-modal-close" id="enrollModalClose"><i class="fa fa-xmark"></i></button>
            <div class="sc-modal-header">
                <span class="sc-modal-type" id="enrollModalType"></span>
                <h2 class="sc-modal-title">Enrolled Students</h2>
            </div>
            <div class="sc-modal-body" id="enrollModalBody"></div>
        </div>
    </div>

<?php endif; ?>

    <?php if ($role !== 'admin'): ?>
    <div class="sc-modal" id="scModal" aria-hidden="true">
        <div class="sc-modal-backdrop" id="scModalBackdrop"></div>
        <div class="sc-modal-panel" id="scModalPanel">
            <button class="sc-modal-close" id="scModalClose"><i class="fa fa-xmark"></i></button>
            <div class="sc-modal-header" id="scModalHeader">
                <span class="sc-modal-type" id="scModalType"></span>
                <h2 class="sc-modal-title" id="scModalTitle">Class Details</h2>
                <p class="sc-modal-datetime" id="scModalDatetime"></p>
            </div>
            <div class="sc-modal-body">
                <div class="sc-modal-info-grid">
                    <div class="sc-modal-info-item" id="scModalTrainerItem">
                        <img id="scModalTrainerAvatar" src="/images/profile_pic.webp" alt="" class="sc-modal-trainer-avatar">
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
                        <div class="sc-cap-fill" id="scModalCapFill"></div>
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
            isClient: <?= json_encode($role === 'client') ?>,
            weekOffset: <?= json_encode($weekOffset) ?>,
            defaultDay: <?= json_encode($defaultDay) ?>,
            classes: <?= json_encode($classesForJS ?: new stdClass()) ?>,
        };
    </script>
    <?php if ($role === 'admin'): ?>
    <script>
        (function () {
            'use strict';

            var dynamicContent = document.getElementById('scDynamicContent');
            var prevBtn = document.getElementById('scPrevBtn');
            var nextBtn = document.getElementById('scNextBtn');
            var todayBtn = document.getElementById('scTodayBtn');
            var weekLabelEl = document.getElementById('scWeekLabel');
            var statClassesEl = document.getElementById('statClasses');
            var statSpotsEl = document.getElementById('statSpots');
            var filterTypeEl = document.getElementById('filterType');
            var filterTrainerEl = document.getElementById('filterTrainer');
            var filterTimeEl = document.getElementById('filterTime');
            var filterClearBtn = document.getElementById('filterClear');
            var filterCountEl = document.getElementById('filterCount');

            function selectDay(dayKey) {
                document.querySelectorAll('.sc-day-btn').forEach(function (b) {
                    b.classList.toggle('sc-day-btn--active', b.dataset.day === dayKey);
                });
                document.querySelectorAll('.sc-day-panel').forEach(function (p) {
                    p.classList.toggle('sc-day-panel--active', p.id === 'panel-' + dayKey);
                });
            }

            function applyFilters() {
                var rows = document.querySelectorAll('.sc-row');
                var panels = document.querySelectorAll('.sc-day-panel');
                var type = filterTypeEl.value;
                var trainer = filterTrainerEl ? filterTrainerEl.value : '';
                var time = filterTimeEl.value;
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
                    var dayKey = panel.id.replace('panel-', '');
                    var panelRows = panel.querySelectorAll('.sc-row');
                    var visible = panel.querySelectorAll('.sc-row:not(.sc-row--hidden)').length;
                    var emptyEl = document.getElementById('fempty-' + dayKey);
                    var countEl = document.getElementById('count-' + dayKey);
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
                var curType = filterTypeEl.value;
                var curTrainer = filterTrainerEl ? filterTrainerEl.value : '';
                filterTypeEl.innerHTML = '<option value="">All types</option>';
                types.forEach(function (t) {
                    var opt = document.createElement('option');
                    opt.value = t;
                    opt.textContent = t;
                    if (t === curType) opt.selected = true;
                    filterTypeEl.appendChild(opt);
                });
                if (filterTrainerEl) {
                    filterTrainerEl.innerHTML = '<option value="">All trainers</option>';
                    Object.keys(trainers).forEach(function (tid) {
                        var opt = document.createElement('option');
                        opt.value = tid;
                        opt.textContent = trainers[tid];
                        if (tid === curTrainer) opt.selected = true;
                        filterTrainerEl.appendChild(opt);
                    });
                }
            }

            function loadWeek(offset) {
                dynamicContent.classList.add('sc-loading');
                prevBtn.disabled = true;
                nextBtn.disabled = true;
                fetch('schedule.php?ajax=1&w=' + offset)
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (data) {
                        SC.weekOffset = data.weekOffset;
                        SC.classes = data.classes;
                        SC.defaultDay = data.defaultDay;
                        weekLabelEl.textContent = data.weekLabel;
                        statClassesEl.textContent = data.totalClasses;
                        statSpotsEl.textContent = data.totalEnrolled;
                        dynamicContent.innerHTML = data.html;
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

            prevBtn.addEventListener('click', function () {
                loadWeek(SC.weekOffset - 1);
            });
            nextBtn.addEventListener('click', function () {
                loadWeek(SC.weekOffset + 1);
            });
            if (todayBtn) todayBtn.addEventListener('click', function () {
                loadWeek(0);
            });

            dynamicContent.addEventListener('click', function (e) {
                var dayBtn = e.target.closest('.sc-day-btn');
                if (dayBtn) {
                    selectDay(dayBtn.dataset.day);
                    return;
                }
            });

            selectDay(SC.defaultDay);
            applyFilters();

            var editModal = document.getElementById('editModal');
            var editBackdrop = document.getElementById('editModalBackdrop');
            var editClose = document.getElementById('editModalClose');

            window.openEditModal = function (classId) {
                var cls = classId ? SC.classes[classId] : null;
                document.getElementById('editModalLabel').textContent = cls ? 'Edit Class' : 'New Class';
                document.getElementById('editAction').value = cls ? 'update' : 'create';
                document.getElementById('editTargetId').value = cls ? classId : '';
                document.getElementById('editClassType').value = cls ? cls.class_type_id : '';
                document.getElementById('editGymId').value = cls ? cls.gym_id : '';
                document.getElementById('editTrainerId').value = cls ? (cls.trainer_id || '') : '';
                document.getElementById('editDuration').value = cls ? cls.duration_min : '';
                document.getElementById('editCapacity').value = cls ? cls.capacity : '';
                document.getElementById('editDescription').value = cls ? (cls.description || '') : '';
                if (cls && cls.schedule) {
                    var d = new Date(cls.schedule);
                    var pad = function (n) {
                        return String(n).padStart(2, '0');
                    };
                    document.getElementById('editSchedule').value =
                        d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
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
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeEditModal();
                    if (window.closeEnrollModal) window.closeEnrollModal();
                }
            });
        }());
    </script>
<?php elseif ($role === 'trainer'): ?>
    <script src="../../js/schedule.js"></script>
    <script>
        (function () {
            'use strict';

            var editModal = document.getElementById('editModal');
            var editBackdrop = document.getElementById('editModalBackdrop');
            var editClose = document.getElementById('editModalClose');

            window.openEditModal = function (classId) {
                var cls = classId ? SC.classes[classId] : null;
                document.getElementById('editModalLabel').textContent = cls ? 'Edit Class' : 'New Class';
                document.getElementById('editAction').value = cls ? 'update' : 'create';
                document.getElementById('editTargetId').value = cls ? classId : '';
                document.getElementById('editClassType').value = cls ? cls.class_type_id : '';
                document.getElementById('editGymId').value = cls ? cls.gym_id : '';
                document.getElementById('editDuration').value = cls ? cls.duration_min : '';
                document.getElementById('editCapacity').value = cls ? cls.capacity : '';
                document.getElementById('editDescription').value = cls ? (cls.description || '') : '';
                if (cls && cls.schedule) {
                    var d = new Date(cls.schedule);
                    var pad = function (n) {
                        return String(n).padStart(2, '0');
                    };
                    document.getElementById('editSchedule').value =
                        d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
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

            if (editClose) editClose.addEventListener('click', closeEditModal);
            if (editBackdrop) editBackdrop.addEventListener('click', closeEditModal);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeEditModal();
                    if (window.closeEnrollModal) window.closeEnrollModal();
                }
            });
        }());
    </script>
<?php else: ?>
    <script src="../../js/schedule.js"></script>
<?php endif; ?>

    <?php if (in_array($role, ['admin', 'trainer'], true)): ?>
    <script>
        (function () {
            var enrollModal = document.getElementById('enrollModal');
            var enrollBackdrop = document.getElementById('enrollModalBackdrop');
            var enrollClose = document.getElementById('enrollModalClose');
            var enrollClassId = null;

            function escHtml(str) {
                var d = document.createElement('div');
                d.textContent = str;
                return d.innerHTML;
            }

            window.closeEnrollModal = function () {
                enrollModal.setAttribute('aria-hidden', 'true');
                enrollModal.classList.remove('sc-modal--open');
                document.body.style.overflow = '';
            };

            function loadEnrollments() {
                var body = document.getElementById('enrollModalBody');
                body.innerHTML = '<p class="sc-modal-loading">Loading…</p>';
                fetch('schedule.php?ajax=1&action=enrollments&class_id=' + enrollClassId)
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (data) {
                        if (!data.ok || !data.enrollments.length) {
                            body.innerHTML = '<p class="sc-enroll-empty"><i class="fa fa-users"></i> No one enrolled yet.</p>';
                            return;
                        }
                        var html = '<ul class="sc-enroll-list">';
                        data.enrollments.forEach(function (u) {
                            var photo = u.profile_photo || '../images/profile_pic.webp';
                            html += '<li class="sc-enroll-item" id="enroll-item-' + u.id + '">';
                            html += '<a href=' + u.id + '"/pages/profile.php?id=" class="sc-enroll-profile-link">';
                            html += '<img src="' + escHtml(photo) + '" class="sc-enroll-avatar" alt="">';
                            html += '<div class="sc-enroll-info"><strong>' + escHtml(u.first_name + ' ' + u.last_name) + '</strong><span>@' + escHtml(u.username) + '</span></div>';
                            html += '</a>';
                            html += '<button class="btn-admin-sm btn-admin-sm--danger" onclick="removeEnrollment(' + u.id + ')" title="Remove"><i class="fa fa-trash"></i></button>';
                            html += '</li>';
                        });
                        html += '</ul>';
                        body.innerHTML = html;
                    });
            }

            window.removeEnrollment = function (clientId) {
                if (!confirm('Remove this student from the class?')) return;
                var fd = new FormData();
                fd.append('ajax', '1');
                fd.append('action', 'unenroll');
                fd.append('class_id', enrollClassId);
                fd.append('client_id', clientId);
                fetch('schedule.php', {method: 'POST', body: fd})
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (data) {
                        if (!data.ok) return;
                        var item = document.getElementById('enroll-item-' + clientId);
                        if (item) item.remove();
                        var cls = SC.classes[enrollClassId];
                        if (cls) cls.enrolled = data.enrolled;
                        var countEl = document.getElementById('enroll-count-' + enrollClassId);
                        if (countEl) countEl.textContent = data.enrolled;
                        var spotsEl = document.getElementById('spots-' + enrollClassId);
                        if (spotsEl && cls) {
                            var spots = cls.capacity - data.enrolled;
                            spotsEl.textContent = data.enrolled + '/' + cls.capacity + (spots > 0 ? ' · ' + spots + ' left' : '');
                        }
                        var capFill = document.getElementById('capfill-' + enrollClassId);
                        if (capFill && cls) {
                            var fillPct = cls.capacity > 0 ? Math.round((data.enrolled / cls.capacity) * 100) : 0;
                            capFill.style.width = fillPct + '%';
                        }
                        if (!document.querySelector('.sc-enroll-item')) {
                            document.getElementById('enrollModalBody').innerHTML = '<p class="sc-enroll-empty"><i class="fa fa-users"></i> No one enrolled yet.</p>';
                        }
                    });
            };

            window.openEnrollmentsModal = function (classId) {
                enrollClassId = classId;
                var cls = SC.classes[classId];
                document.getElementById('enrollModalType').textContent = cls ? cls.class_name : '';
                enrollModal.setAttribute('aria-hidden', 'false');
                enrollModal.classList.add('sc-modal--open');
                document.body.style.overflow = 'hidden';
                loadEnrollments();
            };

            enrollClose.addEventListener('click', window.closeEnrollModal);
            enrollBackdrop.addEventListener('click', window.closeEnrollModal);
        }());
    </script>
<?php endif; ?>

    <?php drawFooter();
} ?>

<?php function drawScheduleDynamicHTML(
    $days,
    $classesByDay,
    $today,
    $enrolledIds,
    $typeColors,
    $typeDescriptions,
    $role,
    $allClasses
): string
{
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
            <span class="sc-day-dots">
                <?php if (empty($dayClasses)): ?>
                    <span class="sc-dot sc-dot--empty"></span>
                <?php else: ?>
                    <?php foreach (array_slice($typesOnDay, 0, 4) as $tn): ?>
                    <span class="sc-dot" style="background:<?= typeColor($tn, $typeColors) ?>"></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </span>
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
                    'description'  => $cls['description'] !== '' ? $cls['description'] : ($typeDescriptions[$cls['class_name']] ?? ''),
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
}
?>
