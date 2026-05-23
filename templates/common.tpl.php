<?php function drawHeader(object $session) { ?>
    <header>
        <nav>
            <a href="<?= $session->isLoggedIn() ? '/pages/dashboard.php' : '/pages/index.php' ?>" class="logo">
                <img src="/images/logo.png" alt="CUBO GYM logo">
            </a>
            <input type="checkbox" id="menu-toggle">
            <label for="menu-toggle" class="menu-icon">
                <span></span>
                <span></span>
                <span></span>
            </label>
            <ul>
                <li><a href="/pages/membership.php">Membership</a></li>
                <li><a href="/pages/about.php">About Us</a></li>
                <li><a href="/pages/locations.php">Locations</a></li>
                <?php if ($session->isLoggedIn()): ?>
                    <li class="profile-dropdown">
                        <a href="/pages/profile.php">
                            <?= htmlspecialchars($session->getName()) ?>
                            <i class="fa-solid fa-chevron-down"></i>
                        </a>

                        <div class="dropdown-menu">
                            <a href="/pages/logout.php">Logout</a>
                        </div>
                    </li>
              <?php endif; ?>
            </ul>
        </nav>
    </header>
<?php } ?>

<?php function drawDashNavbar(object $session, object $db, string $activePage = '', bool $showPfp = true) {
    $userId = $session->isLoggedIn() ? (int)$session->getId() : 0;
    $profilePhoto = '../images/profile_pic.webp';
    $fullName = '';
    $username = '';
    $profileUrl = 'profile.php';

    if ($userId) {
        $role = null;
        foreach (['admins' => 'admin', 'trainers' => 'trainer', 'clients' => 'client'] as $tbl => $r) {
            $s = $db->prepare("SELECT 1 FROM $tbl WHERE user_id = :id");
            $s->execute([':id' => $userId]);
            if ($s->fetch()) { $role = $r; break; }
        }
        $s = $db->prepare('SELECT username, first_name, last_name, profile_photo FROM users WHERE id = :id');
        $s->execute([':id' => $userId]);
        $u = $s->fetch();
        if ($u) {
            $profilePhoto = $u['profile_photo'] ?? $profilePhoto;
            $fullName = $u['first_name'] . ' ' . $u['last_name'];
            $username = $u['username'];
        }
        if (isset($role) && $role === 'trainer') {
            $profileUrl = 'trainer-profile.php?id=' . $userId;
        }
    }
    ?>
    <header class="dash-navbar">
        <a href="dashboard.php" class="logo">
            <img src="/images/logo.png" alt="CUBO GYM logo">
        </a>
        <div class="dash-navbar-right">
            <button class="hamburger-btn" id="hamburgerBtn" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
            <?php if ($showPfp && $userId): ?>
            <a href="<?= htmlspecialchars($profileUrl) ?>" class="dash-nav-profile" title="My Profile">
                <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="Profile" class="dash-nav-pfp">
            </a>
            <?php endif; ?>
        </div>
    </header>
    <div class="nav-popup-backdrop" id="navBackdrop"></div>
    <div class="nav-popup" id="navPopup">
        <div class="nav-popup-user">
            <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="Profile" class="nav-popup-avatar">
            <div>
                <p class="nav-popup-name"><?= htmlspecialchars($fullName) ?></p>
                <p class="nav-popup-handle"><?= $username ? '@' . htmlspecialchars($username) : '' ?></p>
            </div>
        </div>
        <nav class="nav-popup-links">
            <a href="dashboard.php"  class="nav-popup-link <?= $activePage === 'home'       ? 'active' : '' ?>"><i class="fa fa-home"></i> Home</a>
            <a href="<?= htmlspecialchars($profileUrl) ?>" class="nav-popup-link <?= $activePage === 'profile'    ? 'active' : '' ?>"><i class="fa fa-user"></i> Profile</a>
            <a href="schedule.php"   class="nav-popup-link <?= $activePage === 'schedule'   ? 'active' : '' ?>"><i class="fa fa-calendar"></i> Schedule</a>
            <a href="facilities.php" class="nav-popup-link <?= $activePage === 'facilities' ? 'active' : '' ?>"><i class="fa fa-dumbbell"></i> Facilities</a>
            <a href="locations.php"  class="nav-popup-link <?= $activePage === 'locations'  ? 'active' : '' ?>"><i class="fa fa-location-dot"></i> Locations</a>
            <a href="membership.php" class="nav-popup-link <?= $activePage === 'membership' ? 'active' : '' ?>"><i class="fa fa-id-card"></i> Membership</a>
            <a href="about.php"      class="nav-popup-link <?= $activePage === 'about'      ? 'active' : '' ?>"><i class="fa fa-circle-info"></i> About Us</a>
            <hr class="nav-popup-divider">
            <a href="logout.php" class="nav-popup-link nav-popup-link--logout"><i class="fa fa-right-from-bracket"></i> Logout</a>
        </nav>
    </div>
    <script src="../js/nav.js"></script>
    <?php
} ?>

<?php function drawFooter() { ?>
    <footer>
        <div class="footer-logo">
            <a href="/pages/index.php">
                <img src="/images/logo.png" alt="CUBO GYM logo">
            </a>
        </div>

        <div class="footer-links">
            <a href="/pages/membership.php">Membership</a>
            <a href="/pages/about.php">About Us</a>
            <a href="/pages/locations.php">Locations</a>
            <a href="/pages/contact.php">Contact</a>
        </div>

        <div class="footer-info">
            <h3>INFO</h3>
            <p>MON-FRI — 6:00 - 22:30</p>
            <p>SAT — 9:00 - 20:00</p>
            <p>SUN — 10:00 - 18:00</p>
            <br>
            <p>RUA DAS MARAVILHAS 67, 4400-069</p>
            <br>
            <p>GERAL@CUBOGYM.COM</p>
            <p>+351 211 317 632</p>
        </div>

        <div class="footer-socials">
            <h3>FOLLOW US</h3>
            <div class="social-icons">
                <a href="#"><i class="fa-brands fa-instagram"></i></a>
                <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#"><i class="fa-brands fa-linkedin-in"></i></a>
                <a href="#"><i class="fa-brands fa-tiktok"></i></a>
            </div>
        </div>

        <div class="footer-right">
            <a href="/pages/privacy.php">Privacy Policy</a>
            <a href="/pages/terms.php">Terms of Service</a>
            <a href="/pages/complaints.php">Complaint Book</a>
            <p>&copy; All rights reserved.</p>
        </div>
    </footer>
<?php } ?>