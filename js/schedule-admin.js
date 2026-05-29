'use strict';

var SC = JSON.parse(document.getElementById('schedule-data').textContent);

(function () {
    var dynamicContent = document.getElementById('scDynamicContent');
    var prevBtn        = document.getElementById('scPrevBtn');
    var nextBtn        = document.getElementById('scNextBtn');
    var todayBtn       = document.getElementById('scTodayBtn');
    var weekLabelEl    = document.getElementById('scWeekLabel');
    var statClassesEl  = document.getElementById('statClasses');
    var statSpotsEl    = document.getElementById('statSpots');
    var filterTypeEl   = document.getElementById('filterType');
    var filterTrainerEl= document.getElementById('filterTrainer');
    var filterTimeEl   = document.getElementById('filterTime');
    var filterClearBtn = document.getElementById('filterClear');
    var filterCountEl  = document.getElementById('filterCount');

    function selectDay(dayKey) {
        document.querySelectorAll('.sc-day-btn').forEach(function (b) {
            b.classList.toggle('sc-day-btn--active', b.dataset.day === dayKey);
        });
        document.querySelectorAll('.sc-day-panel').forEach(function (p) {
            p.classList.toggle('sc-day-panel--active', p.id === 'panel-' + dayKey);
        });
    }

    function applyFilters() {
        var rows    = document.querySelectorAll('.sc-row');
        var panels  = document.querySelectorAll('.sc-day-panel');
        var type    = filterTypeEl.value;
        var trainer = filterTrainerEl ? filterTrainerEl.value : '';
        var time    = filterTimeEl.value;
        var hasFilter = !!(type || trainer || time);
        filterClearBtn.hidden = !hasFilter;
        var visibleTotal = 0;
        rows.forEach(function (row) {
            var show = true;
            if (type    && row.dataset.type      !== type)    show = false;
            if (trainer && row.dataset.trainerId !== trainer) show = false;
            if (time    && row.dataset.timeofday !== time)    show = false;
            row.classList.toggle('sc-row--hidden', !show);
            if (show) visibleTotal++;
        });
        panels.forEach(function (panel) {
            var dayKey    = panel.id.replace('panel-', '');
            var panelRows = panel.querySelectorAll('.sc-row');
            var visible   = panel.querySelectorAll('.sc-row:not(.sc-row--hidden)').length;
            var emptyEl   = document.getElementById('fempty-' + dayKey);
            var countEl   = document.getElementById('count-'  + dayKey);
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
        prevBtn.disabled = true;
        nextBtn.disabled = true;
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
        if (e.target.closest('button, a')) return;
        var row = e.target.closest('.sc-row');
        if (row) {
            var cls = JSON.parse(row.dataset.class);
            openModal(cls.id);
        }
    });

    selectDay(SC.defaultDay);
    applyFilters();

    var modal       = document.getElementById('scModal');
    var modalClose  = document.getElementById('scModalClose');
    var modalBackdrop = document.getElementById('scModalBackdrop');

    window.openModal = function (classId) {
        var cls = SC.classes[classId];
        if (!cls) return;

        var color   = cls.color || '#888';
        var spots   = (cls.capacity || 0) - (cls.enrolled || 0);
        var fillPct = cls.capacity > 0 ? Math.round(((cls.enrolled || 0) / cls.capacity) * 100) : 0;

        document.getElementById('scModalHeader').style.setProperty('--modal-color', color);
        document.getElementById('scModalType').textContent  = cls.class_name;
        document.getElementById('scModalType').style.color  = color;
        document.getElementById('scModalTitle').textContent = cls.class_name;

        var d = new Date(cls.schedule);
        document.getElementById('scModalDatetime').textContent =
            d.toLocaleDateString('en-GB', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
            + ' · ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

        var trainerItem = document.getElementById('scModalTrainerItem');
        if (cls.trainer_first) {
            trainerItem.style.display = 'flex';
            document.getElementById('scModalTrainerAvatar').src = cls.trainer_photo || '../images/profile_pic.webp';
            document.getElementById('scModalTrainerName').textContent = cls.trainer_first + ' ' + cls.trainer_last;
            document.getElementById('scModalTrainerLink').href = 'trainer-profile.php?id=' + cls.trainer_id;
        } else {
            trainerItem.style.display = 'none';
        }

        document.getElementById('scModalLocation').textContent = cls.gym_city + ' — ' + cls.gym_name;
        document.getElementById('scModalDuration').textContent  = (cls.duration_min || cls.duration) + ' min';
        document.getElementById('scModalCapacity').textContent  = (cls.enrolled || 0) + ' / ' + (cls.capacity || 0);

        var capFill = document.getElementById('scModalCapFill');
        capFill.style.width      = fillPct + '%';
        capFill.style.background = color;
        document.getElementById('scModalCapLabel').textContent = spots <= 0
            ? 'Class is full'
            : spots + ' spot' + (spots !== 1 ? 's' : '') + ' remaining';

        var photoWrap = document.getElementById('scModalClassPhotoWrap');
        var photoImg  = document.getElementById('scModalClassPhoto');
        if (photoWrap && photoImg) {
            if (cls.photo) { photoImg.src = cls.photo; photoWrap.style.display = 'block'; }
            else           { photoWrap.style.display = 'none'; }
        }

        document.getElementById('scModalDesc').textContent = cls.description || 'No description available.';

        var ratingEl = document.getElementById('scModalRating');
        var rc = parseInt(cls.review_count) || 0;
        if (rc > 0) {
            var stars = '';
            for (var i = 1; i <= 5; i++) {
                stars += i <= Math.round(cls.avg_rating)
                    ? '<i class="fa fa-star" style="color:' + color + '"></i>'
                    : '<i class="fa fa-star" style="color:#333"></i>';
            }
            ratingEl.innerHTML = '<div class="sc-modal-stars">' + stars + '</div>'
                + '<span class="sc-modal-rating-val">' + parseFloat(cls.avg_rating).toFixed(1) + '</span>'
                + '<span class="sc-modal-rating-sub">(' + rc + ' review' + (rc !== 1 ? 's' : '') + ')</span>';
        } else {
            ratingEl.innerHTML = '<p class="sc-modal-no-reviews"><i class="fa fa-comment-slash"></i> No reviews yet for this class.</p>';
        }

        document.getElementById('scModalFooter').innerHTML = '';
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('sc-modal--open');
        document.body.style.overflow = 'hidden';
    };

    function closeModal() {
        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('sc-modal--open');
        document.body.style.overflow = '';
    }

    if (modalClose)   modalClose.addEventListener('click', closeModal);
    if (modalBackdrop) modalBackdrop.addEventListener('click', closeModal);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal();
            if (window.closeEditModal)   window.closeEditModal();
            if (window.closeEnrollModal) window.closeEnrollModal();
        }
    });
}());
