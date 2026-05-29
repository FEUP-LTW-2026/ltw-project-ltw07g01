<?php function drawAdminProfilePage(Session $session, PDO $db, array $user, string $profilePhoto, string $fullName): void
{ ?>

    <main class="profile-page admin-profile-page">
        <aside class="sidebar-container">
            <section class="profile-card">
                <div class="profile-info">
                    <figure class="profile-avatar">
                        <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="Profile photo">
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
            <section class="profile-details" aria-label="Account details">
                <div class="detail-item">
                    <span class="detail-label">First Name</span>
                    <p class="detail-value"><?= htmlspecialchars($user['first_name']) ?></p>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Last Name</span>
                    <p class="detail-value"><?= htmlspecialchars($user['last_name']) ?></p>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Username</span>
                    <p class="detail-value">@<?= htmlspecialchars($user['username']) ?></p>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email</span>
                    <p class="detail-value"><?= htmlspecialchars($user['email']) ?></p>
                </div>
            </section>

            <div class="profile-actions">
                <a href="/actions/edit-admin-profile.php" class="btn-edit-profile">Edit Profile</a>
            </div>
        </div>
    </main>

    <?php drawFooter();
} ?>
