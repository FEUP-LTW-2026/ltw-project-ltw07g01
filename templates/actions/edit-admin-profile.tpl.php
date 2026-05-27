<?php function drawEditAdminProfilePage(Session $session, PDO $db, array $user, string $profilePhoto, string $fullName, string $error): void { ?>

<main class="profile-page">
    <aside class="sidebar-container">
        <section class="profile-card">
            <div class="profile-info">
                <figure class="profile-avatar profile-avatar--edit" id="avatarWrapper">
                    <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="Profile photo" id="avatarPreview">
                    <label class="avatar-upload-overlay" for="profile_photo" title="Change photo">
                        <i class="fa fa-camera"></i>
                    </label>
                </figure>
                <div class="user-meta">
                    <h2 class="user-name"><?= htmlspecialchars($fullName) ?></h2>
                    <p class="user-handle">@<?= htmlspecialchars($user['username']) ?></p>
                    <span class="member-tag">ADMIN</span>
                </div>
            </div>
            <div class="user-identity">
                <span class="archetype-tag"><i class="fa fa-user-shield"></i> Administrator</span>
            </div>
        </section>
    </aside>

    <div class="main-content">

        <?php if ($error): ?>
        <div class="admin-alert admin-alert--err">
            <i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="edit-form">
            <input type="file" id="profile_photo" name="profile_photo"
                   accept="image/jpeg,image/png,image/webp,image/gif" class="profile-file-input">

            <div class="profile-details">
                <div class="detail-item">
                    <label class="detail-label" for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="detail-input" required
                        value="<?= htmlspecialchars($user['first_name']) ?>">
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="detail-input" required
                        value="<?= htmlspecialchars($user['last_name']) ?>">
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="detail-input" required
                        pattern="[\w.]{3,30}" title="3–30 characters: letters, numbers, underscores, dots"
                        value="<?= htmlspecialchars($user['username']) ?>">
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email</span>
                    <p class="detail-value"><?= htmlspecialchars($user['email']) ?></p>
                </div>
            </div>

            <div class="password-section">
                <h3>CHANGE PASSWORD</h3>
                <span class="detail-label">Leave blank to keep your current password.</span>
                <div class="detail-item">
                    <label class="detail-label" for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password"
                           class="detail-input" autocomplete="current-password">
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password"
                           class="detail-input" minlength="6" autocomplete="new-password">
                </div>
                <div class="detail-item">
                    <label class="detail-label" for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           class="detail-input" autocomplete="new-password">
                </div>
            </div>

            <div class="profile-actions">
                <button type="submit" class="btn-save-changes">Save Changes</button>
                <a href="/pages/admin-profile.php" class="btn-cancel">Cancel</a>
            </div>
        </form>

    </div>
</main>

<script>
document.getElementById('profile_photo').addEventListener('change', function () {
    var file = this.files[0];
    if (file && file.type.startsWith('image/')) {
        var reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById('avatarPreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

document.querySelector('.edit-form').addEventListener('submit', function (e) {
    var np  = document.getElementById('new_password').value;
    var cp  = document.getElementById('confirm_password').value;
    var cur = document.getElementById('current_password').value;
    if (np !== '' && np !== cp) {
        e.preventDefault();
        alert('New passwords do not match.');
    }
    if (np !== '' && cur === '') {
        e.preventDefault();
        alert('Enter your current password to set a new one.');
    }
});
</script>

<?php drawFooter();
} ?>
