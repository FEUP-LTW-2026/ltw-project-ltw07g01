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

    function scheduleActionUrl() {
        return (window.SC && SC.actionUrl) ? SC.actionUrl : 'schedule.php';
    }

    function showNoCreditsMessage(btn) {
        var target = btn.closest('.sc-card-actions') || btn.closest('.sc-modal-footer');
        var html = '<span class="sc-no-credits-msg"><i class="fa fa-triangle-exclamation"></i> '
            + 'No class credits remaining. <a href="/pages/membership.php">Get more</a></span>';

        if (!target) {
            alert('No class credits remaining.');
            return;
        }

        target.querySelectorAll('.sc-no-credits-msg').forEach(function (el) {
            el.remove();
        });
        target.insertAdjacentHTML('beforeend', html);
    }

    function selectDay(dayKey) {
        document.querySelectorAll('.sc-day-btn').forEach(function (b) {
            b.classList.toggle('sc-day-btn--active', b.dataset.day === dayKey);
        });
        document.querySelectorAll('.sc-day-panel').forEach(function (p) {
            p.classList.toggle('sc-day-panel--active', p.id === 'panel-' + dayKey);
        });
    }

    function applyFilters() {
        // se nao estamos na pagina do schedule nao faz nada
        if (!filterTypeEl) return;
        var rows = document.querySelectorAll('.sc-row');
        var panels = document.querySelectorAll('.sc-day-panel');
        var type = filterTypeEl.value;
        var trainer = filterTrainerEl.value;
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
            if (countEl) {
                countEl.textContent = visible + ' class' + (visible !== 1 ? 'es' : '');
            }
        });

        filterCountEl.textContent = hasFilter ? visibleTotal + ' result' + (visibleTotal !== 1 ? 's' : '') : '';
    }

    if (filterTypeEl && filterTrainerEl && filterTimeEl) {
        [filterTypeEl, filterTrainerEl, filterTimeEl].forEach(function (el) {
            el.addEventListener('change', applyFilters);
        });
        filterClearBtn.addEventListener('click', function () {
            filterTypeEl.value = '';
            filterTrainerEl.value = '';
            filterTimeEl.value = '';
            applyFilters();
        });
    }

    function updateWeekNav(offset) {
        prevBtn.disabled = offset <= -2;
        prevBtn.classList.toggle('sc-week-arrow--disabled', offset <= -2);
        nextBtn.disabled = offset >= 8;
        nextBtn.classList.toggle('sc-week-arrow--disabled', offset >= 8);
        if (todayBtn) todayBtn.hidden = (offset === 0);
    }

    function updateFilterOptions(types, trainers) {
        var currentType = filterTypeEl.value;
        var currentTrainer = filterTrainerEl.value;

        filterTypeEl.innerHTML = '<option value="">All types</option>';
        types.forEach(function (t) {
            var opt = document.createElement('option');
            opt.value = t; opt.textContent = t;
            if (t === currentType) opt.selected = true;
            filterTypeEl.appendChild(opt);
        });

        filterTrainerEl.innerHTML = '<option value="">All trainers</option>';
        Object.keys(trainers).forEach(function (tid) {
            var opt = document.createElement('option');
            opt.value = tid; opt.textContent = trainers[tid];
            if (tid === currentTrainer) opt.selected = true;
            filterTrainerEl.appendChild(opt);
        });
    }

    function loadWeek(offset) {
        // desabilita os botoes enquanto carrega para nao spammar requests
        dynamicContent.classList.add('sc-loading');
        prevBtn.disabled = true;
        nextBtn.disabled = true;

        var ajaxUrl = (window.SC && SC.ajaxUrl) ? SC.ajaxUrl : 'schedule.php';
        var sep = ajaxUrl.indexOf('?') === -1 ? '?' : '&';

        fetch(ajaxUrl + sep + 'ajax=1&w=' + offset)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                // atualiza o objecto global com os dados da nova semana
                SC.weekOffset = data.weekOffset;
                SC.classes = data.classes;
                SC.defaultDay = data.defaultDay;

                weekLabelEl.textContent = data.weekLabel;
                statClassesEl.textContent = data.totalClasses;
                statSpotsEl.textContent = data.totalSpots;

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

    if (prevBtn) {
        prevBtn.addEventListener('click', function () { loadWeek(SC.weekOffset - 1); });
        nextBtn.addEventListener('click', function () { loadWeek(SC.weekOffset + 1); });
        if (todayBtn) todayBtn.addEventListener('click', function () { loadWeek(0); });
    }

    if (dynamicContent) dynamicContent.addEventListener('click', function (e) {
        var dayBtn = e.target.closest('.sc-day-btn');
        if (dayBtn) { selectDay(dayBtn.dataset.day); return; }

        if (e.target.closest('button, a')) return;
        var row = e.target.closest('.sc-row');
        if (row) {
            var cls = JSON.parse(row.dataset.class);
            openModal(cls.id);
        }
    });

    if (dynamicContent) {
        selectDay(SC.defaultDay);
        applyFilters();
    }

    window.bookClass = function (classId, btn) {
        var idleHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Booking…';

        fetch(scheduleActionUrl(), {
            method  : 'POST',
            headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
            body    : 'ajax=1&class_id=' + classId + '&w=' + SC.weekOffset,
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) {
                btn.disabled  = false;
                btn.innerHTML = idleHtml;
                if (data.error === 'no_credits') {
                    showNoCreditsMessage(btn);
                }
                return;
            }

            var fillPct = data.capacity > 0 ? Math.round((data.enrolled / data.capacity) * 100) : 0;

            var capFill = document.getElementById('capfill-' + classId);
            if (capFill) capFill.style.width = fillPct + '%';

            var spotsEl = document.getElementById('spots-' + classId);
            if (spotsEl) {
                spotsEl.textContent = data.enrolled + '/' + data.capacity
                    + (data.spots > 0 ? ' · ' + data.spots + ' left' : '');
                spotsEl.classList.toggle('sc-spots--low', data.spots <= 3 && data.spots > 0);
            }

            var badgesEl = document.getElementById('badges-' + classId);
            if (badgesEl) {
                badgesEl.innerHTML = '<span class="sc-badge sc-badge--enrolled"><i class="fa fa-check"></i> Enrolled</span>';
            }

            var actionsEl = document.getElementById('actions-' + classId);
            if (actionsEl) {
                actionsEl.innerHTML =
                    '<span class="sc-enrolled-confirm"><i class="fa fa-circle-check"></i> You\'re enrolled</span>'
                    + '<button class="sc-details-btn" onclick="event.stopPropagation(); openModal(' + classId + ')">'
                    + 'Details <i class="fa fa-chevron-right"></i></button>';
            }

            if (SC.classes[classId]) {
                SC.classes[classId].enrolled = data.enrolled;
                SC.classes[classId].is_enrolled = true;
                SC.classes[classId].is_full = data.spots <= 0;
            }

            if (currentModalId === classId) openModal(classId);
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = idleHtml;
        });
    };

    window.cancelClass = function (classId, btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Cancelling…';

        fetch(scheduleActionUrl(), {
            method  : 'POST',
            headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
            body    : 'ajax=1&action=cancel&class_id=' + classId,
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-calendar-xmark"></i> Cancel Enrollment';
                return;
            }

            var fillPct = data.capacity > 0 ? Math.round((data.enrolled / data.capacity) * 100) : 0;
            var filling = fillPct >= 70;

            var capFill = document.getElementById('capfill-' + classId);
            if (capFill) capFill.style.width = fillPct + '%';

            var spotsEl = document.getElementById('spots-' + classId);
            if (spotsEl) {
                spotsEl.textContent = data.enrolled + '/' + data.capacity + ' · ' + data.spots + ' left';
                spotsEl.classList.remove('sc-spots--low');
            }

            var badgesEl = document.getElementById('badges-' + classId);
            if (badgesEl) {
                badgesEl.innerHTML = filling
                    ? '<span class="sc-badge sc-badge--filling"><i class="fa fa-fire"></i> Filling up</span>'
                    : '';
            }

            var actionsEl = document.getElementById('actions-' + classId);
            if (actionsEl) {
                actionsEl.innerHTML =
                    '<button class="sc-book-btn" onclick="event.stopPropagation(); bookClass(' + classId + ', this)">'
                    + '<i class="fa fa-calendar-check"></i> Book</button>'
                    + '<button class="sc-details-btn" onclick="event.stopPropagation(); openModal(' + classId + ')">'
                    + 'Details <i class="fa fa-chevron-right"></i></button>';
            }

            if (SC.classes[classId]) {
                SC.classes[classId].enrolled  = data.enrolled;
                SC.classes[classId].is_enrolled = false;
                SC.classes[classId].is_full   = data.spots <= 0;
            }

            if (currentModalId === classId) openModal(classId);
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-calendar-xmark"></i> Cancel Enrollment';
        });
    };

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function buildReviewDone(cls) {
        var stars = '';
        for (var i = 1; i <= 5; i++) {
            stars += '<i class="fa fa-star" style="color:' + (i <= cls.my_rating ? '#f59e0b' : '#333') + '"></i>';
        }
        return '<div class="sc-modal-review-done">'
            + '<p class="sc-review-done-label"><i class="fa fa-circle-check"></i> Your review</p>'
            + '<div class="sc-review-done-stars">' + stars + '</div>'
            + (cls.my_comment ? '<p class="sc-review-done-comment">&ldquo;' + escHtml(cls.my_comment) + '&rdquo;</p>' : '')
            + '</div>';
    }

    function buildReviewForm(classId) {
        var starBtns = [1,2,3,4,5].map(function(n) {
            return '<button type="button" class="sc-star-btn" data-rating="' + n + '"><i class="fa fa-star"></i></button>';
        }).join('');
        return '<div class="sc-modal-review-form">'
            + '<p class="sc-review-prompt">How was this class?</p>'
            + '<div class="sc-review-stars" id="reviewStars">'
            + '<input type="hidden" id="scReviewRating" value="0">'
            + starBtns + '</div>'
            + '<textarea class="sc-review-comment" id="scReviewComment" placeholder="Optional comment…" maxlength="500"></textarea>'
            + '<button class="sc-review-submit" id="scReviewSubmit" disabled'
            + ' onclick="submitReview(' + classId + ', this)"><i class="fa fa-paper-plane"></i> Submit Review</button>'
            + '</div>';
    }

    function setupReviewStars() {
        var starsEl   = document.getElementById('reviewStars');
        var ratingInput = document.getElementById('scReviewRating');
        var submitBtn = document.getElementById('scReviewSubmit');
        if (!starsEl) return;
        var selected = 0;

        // pinta as estrelas ate n, usado no hover e no click
        function paint(n) {
            starsEl.querySelectorAll('.sc-star-btn').forEach(function(b) {
                b.classList.toggle('sc-star-btn--lit', parseInt(b.dataset.rating) <= n);
            });
        }

        starsEl.querySelectorAll('.sc-star-btn').forEach(function(btn) {
            btn.addEventListener('mouseenter', function() { paint(parseInt(btn.dataset.rating)); });
            btn.addEventListener('click', function() {
                selected = parseInt(btn.dataset.rating);
                ratingInput.value = selected;
                paint(selected);
                submitBtn.disabled = false;
            });
        });
        // quando o rato sai volta a mostrar a selecao atual
        starsEl.addEventListener('mouseleave', function() { paint(selected); });
    }

    window.submitReview = function(classId, btn) {
        var rating  = parseInt(document.getElementById('scReviewRating').value || '0');
        var comment = (document.getElementById('scReviewComment').value || '').trim();
        if (!rating) return; // nao deixa submeter sem estrelas

        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Submitting…';

        fetch(scheduleActionUrl(), {
            method : 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body   : 'ajax=1&action=review&class_id=' + classId
                   + '&rating=' + rating
                   + '&comment=' + encodeURIComponent(comment),
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-paper-plane"></i> Submit Review';
                return;
            }
            var cls = SC.classes[classId];
            if (cls) {
                cls.my_rating  = rating;
                cls.my_comment = comment;
                // atualiza o rating medio em todas as aulas do mesmo trainer e tipo
                // porque as reviews sao agregadas por trainer+tipo de aula
                Object.keys(SC.classes).forEach(function(id) {
                    var c = SC.classes[id];
                    if (c.trainer_id === cls.trainer_id && c.class_name === cls.class_name) {
                        c.avg_rating   = data.avg_rating;
                        c.review_count = data.review_count;
                    }
                });
            }
            if (currentModalId === classId) openModal(classId);
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-paper-plane"></i> Submit Review';
        });
    };

    var modal = document.getElementById('scModal');
    var modalBackdrop = document.getElementById('scModalBackdrop');
    var modalClose = document.getElementById('scModalClose');
    var currentModalId = null;

    window.openModal = function (classId) {
        var cls = SC.classes[classId];
        if (!cls) return;
        currentModalId = classId;

        var color = cls.color || '#888';
        var spots = cls.capacity - cls.enrolled;
        var fillPct = cls.capacity > 0 ? Math.round((cls.enrolled / cls.capacity) * 100) : 0;

        document.getElementById('scModalHeader').style.setProperty('--modal-color', color);
        document.getElementById('scModalType').textContent  = cls.class_name;
        document.getElementById('scModalType').style.color  = color;
        document.getElementById('scModalTitle').textContent = cls.class_name;

        var d = new Date(cls.schedule);
        var dateStr = d.toLocaleDateString('en-GB', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
        var timeStr = d.toLocaleTimeString('en-GB', { hour:'2-digit', minute:'2-digit' });
        document.getElementById('scModalDatetime').textContent = dateStr + ' · ' + timeStr;

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
        document.getElementById('scModalDuration').textContent = cls.duration_min + ' min';
        document.getElementById('scModalCapacity').textContent = cls.enrolled + ' / ' + cls.capacity;

        var capFill = document.getElementById('scModalCapFill');
        capFill.style.width      = fillPct + '%';
        capFill.style.background = color;
        document.getElementById('scModalCapLabel').textContent = cls.is_full
            ? 'Class is full'
            : spots + ' spot' + (spots !== 1 ? 's' : '') + ' remaining';

        document.getElementById('scModalDesc').textContent = cls.description || 'No description available.';

        var ratingEl = document.getElementById('scModalRating');
        if (cls.review_count > 0) {
            var stars = '';
            for (var i = 1; i <= 5; i++) {
                stars += i <= Math.round(cls.avg_rating)
                    ? '<i class="fa fa-star" style="color:' + color + '"></i>'
                    : '<i class="fa fa-star" style="color:#333"></i>';
            }
            ratingEl.innerHTML = '<div class="sc-modal-stars">' + stars + '</div>'
                + '<span class="sc-modal-rating-val">' + cls.avg_rating + '</span>'
                + '<span class="sc-modal-rating-sub">(' + cls.review_count + ' review' + (cls.review_count !== 1 ? 's' : '') + ')</span>';
        } else {
            ratingEl.innerHTML = '<p class="sc-modal-no-reviews"><i class="fa fa-comment-slash"></i> No reviews yet for this class.</p>';
        }

        var footer = document.getElementById('scModalFooter');
        footer.innerHTML = '';
        if (SC.isClient) {
            // ve se a aula ja passou para decidir o que mostrar no footer
            var isPast = new Date(cls.schedule) < new Date();
            if (isPast) {
                // so pode dar review se esteve inscrito na aula
                if (cls.is_enrolled) {
                    if (cls.my_rating !== null && cls.my_rating !== undefined) {
                        footer.innerHTML = buildReviewDone(cls);
                    } else {
                        footer.innerHTML = buildReviewForm(classId);
                        setupReviewStars();
                    }
                }
            } else {
                if (cls.is_enrolled) {
                    footer.innerHTML = '<div class="sc-modal-enrolled-wrap">'
                        + '<span class="sc-modal-enrolled-msg"><i class="fa fa-circle-check"></i> You\'re enrolled in this class</span>'
                        + '<button class="sc-modal-cancel-btn" onclick="cancelClass(' + classId + ', this)">'
                        + '<i class="fa fa-calendar-xmark"></i> Cancel Enrollment</button>'
                        + '</div>';
                } else if (!cls.is_full) {
                    footer.innerHTML = '<button class="sc-modal-book-btn" style="background:' + color + '" '
                        + 'onclick="bookClass(' + classId + ', this)">'
                        + '<i class="fa fa-calendar-check"></i> Book this class</button>';
                } else {
                    footer.innerHTML = '<span class="sc-modal-full-msg">This class is fully booked.</span>';
                }
            }
        }

        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('sc-modal--open');
        document.body.style.overflow = 'hidden';
    };

    function closeModal() {
        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('sc-modal--open');
        document.body.style.overflow = '';
        currentModalId = null;
    }

    if (modalClose) modalClose.addEventListener('click', closeModal);
    if (modalBackdrop) modalBackdrop.addEventListener('click', closeModal);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeModal(); }
    });

})();
