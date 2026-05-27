<?php function drawEditTrainerProfilePage(
    Session $session,
    PDO $db,
    array $user,
    int $userId,
    string $profilePhoto,
    string $fullName,
    string $bio,
    string $certifications,
    array $allSpecializations,
    array $currentSpecIds,
    array $allGyms,
    array $currentGymIds,
    string $error
): void { ?>

<main class="profile-page trainer-theme">
    <aside class="sidebar-container">
        <section class="profile-card">
            <div class="profile-info">
                <figure class="profile-avatar profile-avatar--edit" id="avatarWrapper">
                    <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="Trainer Profile Picture" id="avatarPreview">
                    <label class="avatar-upload-overlay" for="profile_photo" title="Change photo">
                        <i class="fa fa-camera"></i>
                    </label>
                </figure>
                <div class="user-meta">
                    <h2 class="user-name"><?= htmlspecialchars($fullName) ?></h2>
                    <p class="user-handle">@<?= htmlspecialchars($user['username']) ?></p>
                    <span class="member-tag trainer-tag">TRAINER</span>
                </div>
            </div>
        </section>
    </aside>

    <div class="main-content">
        <?php if ($error !== ''): ?>
            <div class="admin-alert admin-alert--err"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="edit-form">
            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" class="profile-file-input">

            <div class="profile-details">
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
                    <textarea id="bio" name="bio" class="detail-input bio-textarea" maxlength="300" rows="4" placeholder="Tell members about yourself..."><?= htmlspecialchars($bio) ?></textarea>
                </div>
            </div>

            <div class="specializations-section">
                <h3>SPECIALIZATIONS</h3>
                <p class="detail-label">Select all areas you are qualified to teach.</p>
                <div class="specialization-toggle-group">
                    <?php foreach ($allSpecializations as $spec): ?>
                        <label class="spec-toggle <?= in_array((int)$spec['id'], array_map('intval', $currentSpecIds)) ? 'selected' : '' ?>">
                            <input type="checkbox" name="specializations[]" value="<?= $spec['id'] ?>"
                                   <?= in_array((int)$spec['id'], array_map('intval', $currentSpecIds)) ? 'checked' : '' ?>>
                            <span class="spec-name"><?= htmlspecialchars($spec['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="certifications-edit-section">
                <h3>CERTIFICATIONS</h3>
                <p class="detail-label">Enter one certification per line.</p>
                <textarea name="certifications" class="detail-input bio-textarea" rows="6" placeholder="e.g. NASM Certified Personal Trainer&#10;ACE Group Fitness Instructor"><?= htmlspecialchars($certifications) ?></textarea>
            </div>

            <div class="gyms-section">
                <h3>GYM LOCATIONS</h3>
                <p class="detail-label">Select the gyms where you work.</p>
                <div class="specialization-toggle-group">
                    <?php foreach ($allGyms as $gym): ?>
                        <label class="spec-toggle <?= in_array((int)$gym['id'], array_map('intval', $currentGymIds)) ? 'selected' : '' ?>">
                            <input type="checkbox" name="gym_ids[]" value="<?= $gym['id'] ?>"
                                   <?= in_array((int)$gym['id'], array_map('intval', $currentGymIds)) ? 'checked' : '' ?>>
                            <span class="spec-name"><?= htmlspecialchars($gym['city'] . ' - ' . $gym['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="password-section">
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
            </div>

            <div class="profile-actions">
                <button type="submit" class="btn-save-changes">Save Changes</button>
                <a href="../../pages/trainer-profile.php?id=<?= $userId ?>" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
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

    document.querySelectorAll('.spec-toggle input[type="checkbox"]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            cb.closest('.spec-toggle').classList.toggle('selected', cb.checked);
        });
    });

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
