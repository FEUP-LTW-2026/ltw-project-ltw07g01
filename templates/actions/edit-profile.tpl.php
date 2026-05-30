<?php function drawEditProfilePage(
    Session $session,
    PDO $db,
    array $user,
    string $profilePhoto,
    string $fullName,
    string $memberTag,
    string $bio,
    string $bodyWeight,
    string $height,
    array $gyms,
    array $archetypeOptions,
    array $availableBadges,
    array $selectedBadgeCodes,
    string $error
): void { ?>

<main class="profile-page">
    <aside class="sidebar-container">
        <section class="profile-card">
            <div class="profile-info">
                <figure class="profile-avatar profile-avatar--edit" id="avatarWrapper">
                    <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="User Profile Picture" id="avatarPreview">
                    <label class="avatar-upload-overlay" for="profile_photo" title="Change photo">
                        <i class="fa fa-camera"></i>
                    </label>
                </figure>
                <div class="user-meta">
                    <h2 class="user-name"><?= htmlspecialchars($fullName) ?></h2>
                    <p class="user-handle">@<?= htmlspecialchars($user['username']) ?></p>
                    <span class="member-tag"><?= htmlspecialchars($memberTag) ?></span>
                </div>
            </div>
        </section>
    </aside>

    <div class="main-content">
        <?php if ($error !== ''): ?>
            <div class="admin-alert admin-alert--err"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="edit-form">
            <?= csrf_field() ?>
            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" class="profile-file-input">
            <section class="profile-details" aria-label="Personal information">
                <div class="detail-item">
                    <label class="detail-label" for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="detail-input" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="detail-input" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="detail-input" value="<?= htmlspecialchars($user['username']) ?>" required pattern="[\w.]{3,30}" title="3–30 characters: letters, numbers, underscores, dots">
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="detail-input" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="detail-item detail-item--bio">
                    <label class="detail-label" for="bio">Bio <span class="bio-char-count">(<span id="bioCount"><?= mb_strlen($bio) ?></span>/300)</span></label>
                    <textarea id="bio" name="bio" class="detail-input bio-textarea" maxlength="300" rows="4" placeholder="Tell the gym about yourself..."><?= htmlspecialchars($bio) ?></textarea>
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="preferred_gym_id">Home Gym</label>
                    <select id="preferred_gym_id" name="preferred_gym_id" class="detail-select">
                        <option value="">No gym selected</option>
                        <?php foreach ($gyms as $gym): ?>
                            <option value="<?= $gym['id'] ?>" <?= $user['preferred_gym_id'] == $gym['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($gym['city'] . ' - ' . $gym['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="archetype">Archetype</label>
                    <select id="archetype" name="archetype" class="detail-select">
                        <option value="">No archetype</option>
                        <?php foreach ($archetypeOptions as $option): ?>
                            <option value="<?= $option['id'] ?>" <?= $user['archetype_id'] == $option['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($option['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </section>

            <div class="badge-picker">
            </div>

            <section class="metrics-section">
                <h3>METRICS</h3>
                <div class="metrics-grid">
                    <div class="metric-card">
                        <label class="metric-label" for="body_weight">Body Weight (kg)</label>
                        <input type="number" id="body_weight" name="body_weight" class="metric-input" step="0.1" value="<?= htmlspecialchars((string)$bodyWeight) ?>" required>
                    </div>
                    <div class="metric-card">
                        <label class="metric-label" for="height">Height (cm)</label>
                        <input type="number" id="height" name="height" class="metric-input" step="0.1" value="<?= htmlspecialchars((string)$height) ?>" required>
                    </div>
                </div>
            </section>

            <section class="display-badges-section">
                <h3>DISPLAY BADGES</h3>
                <p class="detail-label">Select which earned badges should appear on your profile.</p>
                <div class="badge-toggle-group">
                    <?php if (empty($availableBadges)): ?>
                        <p>No badges are available yet.</p>
                    <?php else: ?>
                        <?php foreach ($availableBadges as $badge): ?>
                            <label class="badge badge-toggle <?= in_array($badge['code'], $selectedBadgeCodes, true) ? 'selected' : '' ?>">
                                <input type="checkbox" name="display_badges[]" value="<?= $badge['code'] ?>" <?= in_array($badge['code'], $selectedBadgeCodes, true) ? 'checked' : '' ?>>
                                <span class="badge-icon"><?= $badge['icon'] ?></span>
                                <span class="badge-name"><?= htmlspecialchars($badge['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="password-section">
                <h3>CHANGE PASSWORD</h3>
                <p class="detail-label">Leave blank to keep your current password.</p>
                <div class="detail-item">
                    <label class="detail-label" for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="detail-input" autocomplete="current-password">
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="detail-input" autocomplete="new-password" minlength="6">
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="detail-input" autocomplete="new-password">
                </div>
            </section>

            <div class="profile-actions">
                <button type="submit" class="btn-save-changes">Save Changes</button>
                <a href="../../pages/profile.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.badge-toggle input[type="checkbox"]').forEach(function (cb) {
        cb.addEventListener('change', function (e) {
            var label = e.target.closest('.badge-toggle');
            if (!label) return;
            if (e.target.checked) {
                label.classList.add('selected');
            } else {
                label.classList.remove('selected');
            }
        });
    });

    var bioTextarea = document.getElementById('bio');
    var bioCount = document.getElementById('bioCount');
    if (bioTextarea && bioCount) {
        bioTextarea.addEventListener('input', function () {
            bioCount.textContent = bioTextarea.value.length;
        });
    }

    var photoInput = document.getElementById('profile_photo');
    var avatarPreview = document.getElementById('avatarPreview');
    if (photoInput && avatarPreview) {
        photoInput.addEventListener('change', function () {
            var file = photoInput.files[0];
            if (file && file.type.startsWith('image/')) {
                var reader = new FileReader();
                reader.onload = function (e) { avatarPreview.src = e.target.result; };
                reader.readAsDataURL(file);
            }
        });
    }

    var newPw = document.getElementById('new_password');
    var confirmPw = document.getElementById('confirm_password');
    var currentPw = document.getElementById('current_password');
    if (newPw && confirmPw && currentPw) {
        document.querySelector('.edit-form').addEventListener('submit', function (e) {
            if (newPw.value !== '' && newPw.value !== confirmPw.value) {
                e.preventDefault();
                alert('New passwords do not match.');
            }
            if (newPw.value !== '' && currentPw.value === '') {
                e.preventDefault();
                alert('Please enter your current password to set a new one.');
            }
        });
    }
});
</script>

<?php drawFooter();
} ?>
