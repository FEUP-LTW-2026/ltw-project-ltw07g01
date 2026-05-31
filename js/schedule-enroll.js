(function () {
    var enrollModal   = document.getElementById('enrollModal');
    if (!enrollModal) return;

    var enrollBackdrop = document.getElementById('enrollModalBackdrop');
    var enrollClose    = document.getElementById('enrollModalClose');
    var enrollClassId  = null;

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
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok || !data.enrollments.length) {
                    body.innerHTML = '<p class="sc-enroll-empty"><i class="fa fa-users"></i> No one enrolled yet.</p>';
                    return;
                }
                var cls = SC.classes[enrollClassId];
                var isPast = cls && cls.schedule && new Date(cls.schedule) < new Date();
                var html = '<ul class="sc-enroll-list">';
                data.enrollments.forEach(function (u) {
                    var photo = u.profile_photo || '../images/profile_pic.webp';
                    html += '<li class="sc-enroll-item" id="enroll-item-' + u.id + '">';
                    html += '<a href="/pages/profile.php?id=' + u.id + '" class="sc-enroll-profile-link">';
                    html += '<img src="' + escHtml(photo) + '" class="sc-enroll-avatar" alt="">';
                    html += '<div class="sc-enroll-info"><strong>' + escHtml(u.first_name + ' ' + u.last_name) + '</strong>'
                          + '<span>@' + escHtml(u.username) + '</span></div>';
                    html += '</a>';
                    if (!isPast) {
                        html += '<button class="btn-admin-sm btn-admin-sm--danger" onclick="removeEnrollment(' + u.id + ')" title="Remove">'
                              + '<i class="fa fa-trash"></i></button>';
                    }
                    html += '</li>';
                });
                html += '</ul>';
                body.innerHTML = html;
            });
    }

    window.removeEnrollment = function (clientId) {
        var cls = SC.classes[enrollClassId];
        if (cls && cls.schedule && new Date(cls.schedule) < new Date()) return;
        if (!confirm('Remove this student from the class?')) return;
        var fd = new FormData();
        fd.append('ajax',       '1');
        fd.append('action',     'unenroll');
        fd.append('class_id',   enrollClassId);
        fd.append('client_id',  clientId);
        fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        fetch('schedule.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
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
                    document.getElementById('enrollModalBody').innerHTML =
                        '<p class="sc-enroll-empty"><i class="fa fa-users"></i> No one enrolled yet.</p>';
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

    enrollClose.addEventListener('click',   window.closeEnrollModal);
    enrollBackdrop.addEventListener('click', window.closeEnrollModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') window.closeEnrollModal();
    });
}());
