(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    function autoFormatDMY(input) {
        var digits = input.value.replace(/\D/g, '').substring(0, 8);
        var v = digits.substring(0, 2);
        if (digits.length > 2) v += '-' + digits.substring(2, 4);
        if (digits.length > 4) v += '-' + digits.substring(4, 8);
        input.value = v;
    }

    ['f_start', 'f_end'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', function () { autoFormatDMY(this); });
    });

    function fadeOutAlert(el, delay) {
        setTimeout(function () {
            el.style.opacity = '0';
            el.addEventListener('transitionend', function () { el.remove(); }, { once: true });
        }, delay);
    }

    document.querySelectorAll('.admin-alert--ok, .admin-alert--err').forEach(function (el) {
        if (!el.classList.contains('admin-alert--ajax')) {
            fadeOutAlert(el, 3500);
        }
    });

    document.getElementById('memberSearch').addEventListener('input', function () {
        var q = this.value.toLowerCase();
        document.querySelectorAll('.admin-table tbody tr').forEach(function (row) {
            row.style.display = (!q || row.textContent.toLowerCase().includes(q)) ? '' : 'none';
        });
    });

    document.querySelectorAll('.admin-photo-picker input[type="file"]').forEach(function (inp) {
        inp.addEventListener('change', function () {
            var file = this.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            var reader  = new FileReader();
            var preview = this.closest('.admin-photo-picker').querySelector('.admin-photo-preview');
            reader.onload = function (e) { preview.src = e.target.result; };
            reader.readAsDataURL(file);
        });
    });

    window.toggleForm = function () {
        var f = document.getElementById('memberForm');
        f.hidden = !f.hidden;
        if (!f.hidden) {
            document.getElementById('formTitle').textContent        = 'New Member';
            document.getElementById('formAction').value             = 'create';
            document.getElementById('formTargetId').value           = '';
            document.getElementById('formSubmit').innerHTML         = '<i class="fa fa-plus"></i> Create';
            document.getElementById('usernameField').style.display  = '';
            document.getElementById('passwordField').style.display  = '';
            document.getElementById('membershipSection').style.display = '';
            document.getElementById('promoteSection').style.display = 'none';
            document.getElementById('formPhotoPreview').src         = '../images/profile_pic.webp';
            ['f_first', 'f_last', 'f_email', 'f_username', 'f_password', 'f_start', 'f_end'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            document.getElementById('f_plan').value    = 'none';
            document.getElementById('f_credits').value = '0';
        }
    };

    window.closeForm = function () {
        document.getElementById('memberForm').hidden = true;
    };

    window.editMember = function (id, first, last, email, plan, gymStart, gymEnd, photo, credits) {
        var f = document.getElementById('memberForm');
        f.hidden = false;
        document.getElementById('formTitle').textContent             = 'Edit Member';
        document.getElementById('formAction').value                  = 'update';
        document.getElementById('formTargetId').value                = id;
        document.getElementById('formSubmit').innerHTML              = '<i class="fa fa-save"></i> Save';
        document.getElementById('usernameField').style.display       = 'none';
        document.getElementById('passwordField').style.display       = 'none';
        document.getElementById('membershipSection').style.display   = 'flex';
        document.getElementById('promoteSection').style.display      = 'block';
        document.getElementById('promoteTargetId').value             = id;
        document.getElementById('formPhotoPreview').src              = photo || '../images/profile_pic.webp';
        document.getElementById('f_first').value   = first;
        document.getElementById('f_last').value    = last;
        document.getElementById('f_email').value   = email;
        document.getElementById('f_plan').value    = plan || 'none';
        document.getElementById('f_start').value   = gymStart || '';
        document.getElementById('f_end').value     = gymEnd || '';
        document.getElementById('f_credits').value = credits !== undefined ? credits : 0;
        f.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    // Main form — create or update member
    document.getElementById('memberFormEl').addEventListener('submit', async function (e) {
        e.preventDefault();
        var action   = document.getElementById('formAction').value;
        var targetId = document.getElementById('formTargetId').value;
        var fd = new FormData(this);
        var url;
        if (action === 'update' && targetId) {
            url = '/api/members.php?id=' + targetId;
            fd.append('_method', 'PUT');
        } else {
            url = '/api/members.php';
        }
        var btn = document.getElementById('formSubmit');
        btn.disabled = true;
        try {
            var res  = await fetch(url, { method: 'POST', body: fd });
            var data = await res.json();
            if (res.ok) {
                window.location.href = '/pages/admin-members.php?msg=' + encodeURIComponent(data.msg);
            } else {
                showMemberNotification(data.error || 'An error occurred.', 'err');
                btn.disabled = false;
            }
        } catch (_) {
            showMemberNotification('Network error. Please try again.', 'err');
            btn.disabled = false;
        }
    });

    // Delete forms
    document.querySelectorAll('.form-inline').forEach(function (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            var name = this.closest('tr')?.querySelector('td')?.textContent?.trim() || 'this member';
            if (!confirm('Remove ' + name + '? This cannot be undone.')) return;
            var fd  = new FormData(this);
            var res = await fetch(this.action, { method: 'POST', body: fd });
            if (res.status === 204 || res.ok) {
                this.closest('tr').remove();
            } else {
                var data = await res.json().catch(function () { return {}; });
                showMemberNotification(data.error || 'Error removing member.', 'err');
            }
        });
    });

    // Promote to trainer
    document.getElementById('promoteForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!confirm('Promote this member to trainer? This cannot be undone.')) return;
        var targetId = document.getElementById('promoteTargetId').value;
        var fd = new FormData(this);
        var res  = await fetch('/api/members.php?action=promote_trainer', { method: 'POST', body: fd });
        var data = await res.json();
        if (data.ok) {
            window.location.href = '/pages/trainers.php?msg=' + encodeURIComponent(data.msg);
        } else {
            showMemberNotification(data.error || 'Error promoting member.', 'err');
        }
    });

    // Promote to admin
    document.getElementById('promoteAdminBtn').addEventListener('click', function () {
        var targetId = document.getElementById('promoteTargetId').value;
        if (!confirm('Promote this member to admin? This cannot be undone.')) return;
        var btn = this;
        btn.disabled = true;
        var fd = new FormData();
        fd.append('user_id',    targetId);
        fd.append('csrf_token', csrfToken);
        fetch('/api/admins.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.msg || res.id) {
                    showMemberNotification('Member promoted to admin successfully.', 'ok');
                    document.getElementById('memberForm').hidden = true;
                    var row = document.querySelector('tr[data-member-id="' + targetId + '"]');
                    if (row) row.remove();
                } else {
                    showMemberNotification(res.error || 'Error promoting member.', 'err');
                    btn.disabled = false;
                }
            })
            .catch(function () {
                showMemberNotification('Network error. Please try again.', 'err');
                btn.disabled = false;
            });
    });

    function showMemberNotification(msg, type) {
        var existing = document.querySelector('.admin-alert--ajax');
        if (existing) existing.remove();
        var el = document.createElement('div');
        el.className = 'admin-alert admin-alert--' + type + ' admin-alert--ajax';
        el.innerHTML = '<i class="fa fa-' + (type === 'ok' ? 'circle-check' : 'triangle-exclamation') + '"></i> ' + msg;
        var header = document.querySelector('.admin-header');
        header.parentNode.insertBefore(el, header.nextSibling);
        fadeOutAlert(el, 4000);
    }
}());
