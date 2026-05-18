<?php function drawHeader(object $session) { ?>
    <header>
        <nav class="navbar">
            <a href="/pages/index.php" class="logo">
                <img src="/images/logo.png" alt="CUBO GYM logo">
            </a>
            <input type="checkbox" id="menu-toggle">
            <label for="menu-toggle" class="menu-icon">
                <span></span>
                <span></span>
                <span></span>
            </label>
            <ul class="nav-links">
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

<?php function drawFooter() { ?>
    <footer class="footer">
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