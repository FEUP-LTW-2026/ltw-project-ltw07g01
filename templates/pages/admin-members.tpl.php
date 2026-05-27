<?php function drawAdminMembersPage(Session $session, PDO $db, array $members, string $msg, string $error): void
{ ?>

    <main class="admin-page">

        <div class="admin-header">
            <div>
                <h1><i class="fa fa-users"></i> Manage Members</h1>
                <p class="admin-sub"><?= count($members) ?> member<?= count($members) !== 1 ? 's' : '' ?> registered</p>
            </div>
            <button class="btn-admin-primary" onclick="toggleForm()">
                <i class="fa fa-plus"></i> New Member
            </button>
        </div>

        <?php if ($msg): ?>
            <div class="admin-alert admin-alert--ok"><i class="fa fa-circle-check"></i> <?= $msg ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="admin-alert admin-alert--err"><i
                        class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="admin-form-card" id="memberForm" hidden>
            <h2 id="formTitle">New Member</h2>
            <form method="POST" enctype="multipart/form-data" class="admin-form-grid">
                <input type="hidden" name="_action" id="formAction" value="create">
                <input type="hidden" name="target_id" id="formTargetId" value="">

                <div class="admin-field">
                    <label>First Name</label>
                    <input type="text" name="first_name" id="f_first" required>
                </div>
                <div class="admin-field">
                    <label>Last Name</label>
                    <input type="text" name="last_name" id="f_last" required>
                </div>
                <div class="admin-field">
                    <label>Email</label>
                    <input type="email" name="email" id="f_email" required>
                </div>
                <div class="admin-field" id="usernameField">
                    <label>Username</label>
                    <input type="text" name="username" id="f_username">
                </div>
                <div class="admin-field" id="passwordField">
                    <label>Password</label>
                    <input type="password" name="password" id="f_password" minlength="6" placeholder="Min 6 characters">
                </div>

                <div class="admin-field admin-field--full" id="membershipSection">
                    <label class="admin-label-block">Membership</label>
                    <div class="admin-form-grid admin-form-grid--flush">
                        <div class="admin-field">
                            <label>Plan</label>
                            <select name="gym_plan" id="f_plan">
                                <option value="none">— No membership —</option>
                                <option value="basic">Basic</option>
                                <option value="pro">Pro</option>
                                <option value="ultra">Ultra</option>
                            </select>
                        </div>
                        <div class="admin-field">
                            <label>Start Date</label>
                            <input type="text" name="gym_start" id="f_start" pattern="\d{4}-\d{2}-\d{2}" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="admin-field">
                            <label>End Date <span class="admin-optional">(optional)</span></label>
                            <input type="text" name="gym_end" id="f_end" pattern="\d{4}-\d{2}-\d{2}" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="admin-field">
                            <label>Class Credits</label>
                            <input type="number" name="classes_remaining" id="f_credits" min="0" value="0">
                        </div>
                    </div>
                </div>

                <div class="admin-field admin-field--full">
                    <label>Photo <span class="admin-optional">(optional)</span></label>
                    <div class="admin-photo-picker">
                        <img class="admin-photo-preview" id="formPhotoPreview" src="../../images/profile_pic.webp"
                             alt="">
                        <label class="admin-photo-label">
                            <i class="fa fa-camera"></i> Choose photo
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif"
                                   class="profile-file-input">
                        </label>
                    </div>
                </div>

                <div class="admin-form-actions">
                    <button type="submit" class="btn-admin-primary" id="formSubmit">
                        <i class="fa fa-plus"></i> Create
                    </button>
                    <button type="button" class="btn-admin-ghost" onclick="closeForm()">Cancel</button>
                </div>
            </form>

            <div id="promoteSection" class="admin-promote-section">
                <form method="POST" id="promoteForm"
                      onsubmit="return confirm('Promote this member to trainer? This cannot be undone.')">
                    <input type="hidden" name="_action" value="promote">
                    <input type="hidden" name="target_id" id="promoteTargetId" value="">
                    <button type="submit" class="btn-admin-ghost btn-admin-ghost--warn">
                        <i class="fa fa-arrow-up-right-dots"></i> Promote to Trainer
                    </button>
                </form>
                <button type="button" class="btn-admin-ghost btn-admin-ghost--warn" id="promoteAdminBtn">
                    <i class="fa fa-user-shield"></i> Promote to Admin
                </button>
            </div>
        </div>

        <div class="admin-search-bar">
            <input type="search" id="memberSearch" placeholder="Search by name, username or email…">
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Plan</th>
                    <th>Expires</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($members as $m): ?>
                    <tr data-member-id="<?= $m['id'] ?>">
                        <td><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></td>
                        <td class="admin-dim">@<?= htmlspecialchars($m['username']) ?></td>
                        <td><?= htmlspecialchars($m['email']) ?></td>
                        <td>
                            <?php if ($m['gym_plan']): ?>
                                <span class="admin-badge admin-badge--<?= $m['gym_plan'] ?>"><?= strtoupper($m['gym_plan']) ?></span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge--none">None</span>
                            <?php endif; ?>
                        </td>
                        <td class="admin-dim">
                            <?= $m['gym_end'] ? date('d M Y', strtotime($m['gym_end'])) : '—' ?>
                        </td>
                        <td class="admin-dim"><?= (new DateTime($m['created_at']))->format('d M Y') ?></td>
                        <td>
                            <div class="admin-row-actions">
                                <button class="btn-admin-sm" title="Edit"
                                        onclick="editMember(<?= $m['id'] ?>, <?= htmlspecialchars(json_encode($m['first_name'])) ?>, <?= htmlspecialchars(json_encode($m['last_name'])) ?>, <?= htmlspecialchars(json_encode($m['email'])) ?>, <?= htmlspecialchars(json_encode($m['gym_plan'] ?? '')) ?>, <?= htmlspecialchars(json_encode($m['gym_start'] ? date('Y-m-d', strtotime($m['gym_start'])) : '')) ?>, <?= htmlspecialchars(json_encode($m['gym_end'] ? date('Y-m-d', strtotime($m['gym_end'])) : '')) ?>, <?= htmlspecialchars(json_encode($m['profile_photo'] ?? '')) ?>, <?= (int)$m['classes_remaining'] ?>)">
                                    <i class="fa fa-pen"></i>
                                </button>
                                <a href="/pages/profile.php?id=<?= $m['id'] ?>" class="btn-admin-sm"
                                   title="View profile">
                                    <i class="fa fa-eye"></i>
                                </a>
                                <form method="POST" class="form-inline"
                                      onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($m['first_name'])) ?>? This cannot be undone.')">
                                    <input type="hidden" name="_action" value="delete">
                                    <input type="hidden" name="target_id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn-admin-sm btn-admin-sm--danger" title="Remove">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="7" class="admin-empty">No members yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <script>
        document.getElementById('memberSearch').addEventListener('input', function () {
            var q = this.value.toLowerCase();
            document.querySelectorAll('.admin-table tbody tr').forEach(function (row) {
                var text = row.textContent.toLowerCase();
                row.style.display = (!q || text.includes(q)) ? '' : 'none';
            });
        });

        document.querySelectorAll('.admin-photo-picker input[type="file"]').forEach(function (inp) {
            inp.addEventListener('change', function () {
                var file = this.files[0];
                if (!file || !file.type.startsWith('image/')) return;
                var reader = new FileReader();
                var preview = this.closest('.admin-photo-picker').querySelector('.admin-photo-preview');
                reader.onload = function (e) {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        });

        function toggleForm() {
            var f = document.getElementById('memberForm');
            f.hidden = !f.hidden;
            if (!f.hidden) {
                document.getElementById('formTitle').textContent = 'New Member';
                document.getElementById('formAction').value = 'create';
                document.getElementById('formTargetId').value = '';
                document.getElementById('formSubmit').innerHTML = '<i class="fa fa-plus"></i> Create';
                document.getElementById('usernameField').style.display = '';
                document.getElementById('passwordField').style.display = '';
                document.getElementById('membershipSection').style.display = '';
                document.getElementById('promoteSection').style.display = 'none';
                document.getElementById('formPhotoPreview').src = '../images/profile_pic.webp';
                ['f_first', 'f_last', 'f_email', 'f_username', 'f_password', 'f_start', 'f_end'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.value = '';
                });
                document.getElementById('f_plan').value = 'none';
                document.getElementById('f_credits').value = '0';
            }
        }

        function closeForm() {
            document.getElementById('memberForm').hidden = true;
        }

        function editMember(id, first, last, email, plan, gymStart, gymEnd, photo, credits) {
            var f = document.getElementById('memberForm');
            f.hidden = false;
            document.getElementById('formTitle').textContent = 'Edit Member';
            document.getElementById('formAction').value = 'update';
            document.getElementById('formTargetId').value = id;
            document.getElementById('formSubmit').innerHTML = '<i class="fa fa-save"></i> Save';
            document.getElementById('usernameField').style.display = 'none';
            document.getElementById('passwordField').style.display = 'none';
            document.getElementById('membershipSection').style.display = 'flex';
            document.getElementById('promoteSection').style.display = 'block';
            document.getElementById('promoteTargetId').value = id;
            document.getElementById('formPhotoPreview').src = photo || '../images/profile_pic.webp';
            document.getElementById('f_first').value = first;
            document.getElementById('f_last').value = last;
            document.getElementById('f_email').value = email;
            document.getElementById('f_plan').value = plan || 'none';
            document.getElementById('f_start').value = gymStart || '';
            document.getElementById('f_end').value = gymEnd || '';
            document.getElementById('f_credits').value = credits !== undefined ? credits : 0;
            f.scrollIntoView({behavior: 'smooth', block: 'start'});
        }

        <?php if ($error): ?>
        document.getElementById('memberForm').hidden = false;
        <?php endif; ?>

        document.getElementById('promoteAdminBtn').addEventListener('click', function () {
            var targetId = document.getElementById('promoteTargetId').value;
            if (!confirm('Promote this member to admin? This cannot be undone.')) return;

            var btn = this;
            btn.disabled = true;

            var data = new FormData();
            data.append('_action', 'promote_admin');
            data.append('target_id', targetId);
            data.append('_ajax', '1');

            var responsePromise = fetch('/pages/admin-members.php', {method: 'POST', body: data})
                .then(function (r) {
                    return r.json();
                });

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
            setTimeout(function () {
                el.remove();
            }, 5000);
        }
    </script>

    <?php drawFooter();
} ?>
