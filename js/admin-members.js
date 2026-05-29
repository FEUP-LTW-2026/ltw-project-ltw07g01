(function () {
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

    document.getElementById('promoteAdminBtn').addEventListener('click', function () {
        var targetId = document.getElementById('promoteTargetId').value;
        if (!confirm('Promote this member to admin? This cannot be undone.')) return;
        var btn = this;
        btn.disabled = true;
        var data = new FormData();
        data.append('_action',   'promote_admin');
        data.append('target_id', targetId);
        data.append('_ajax',     '1');
        var responsePromise = fetch('/pages/admin-members.php', { method: 'POST', body: data })
            .then(function (r) { return r.json(); });
        setTimeout(function () {
            responsePromise.then(function (res) {
                showMemberNotification(
                    res.ok ? 'Member promoted to admin successfully.' : (res.error || 'Error promoting member.'),
                    res.ok ? 'ok' : 'err'
                );
                if (res.ok) {
                    document.getElementById('memberForm').hidden = true;
                    var row = document.querySelector('tr[data-member-id="' + targetId + '"]');
                    if (row) row.remove();
                } else {
                    btn.disabled = false;
                }
            }).catch(function () {
                showMemberNotification('Network error. Please try again.', 'err');
                btn.disabled = false;
            });
        }, 3000);
    });

    function showMemberNotification(msg, type) {
        var existing = document.querySelector('.admin-alert--ajax');
        if (existing) existing.remove();
        var el = document.createElement('div');
        el.className = 'admin-alert admin-alert--' + type + ' admin-alert--ajax';
        el.innerHTML = '<i class="fa fa-' + (type === 'ok' ? 'circle-check' : 'triangle-exclamation') + '"></i> ' + msg;
        var header = document.querySelector('.admin-header');
        header.parentNode.insertBefore(el, header.nextSibling);
        setTimeout(function () { el.remove(); }, 5000);
    }
}());
