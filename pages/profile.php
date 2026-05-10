<?php
    declare(strict_types=1);
    require_once('../utils/session.php');
    $session = new Session();
    require_once('../templates/common.tmp.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | Cubo Gym</title>
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Gothic&display=swap" rel="stylesheet">
</head>
<body>

<?php drawHeader($session); ?>

    <main class="profile-page">
        <aside class="sidebar-container">
            <section class="profile-card">
                <div class="profile-info">
                    <figure class="profile-avatar">
                        <img src="../images/profile_pic.webp" alt="User Profile Picture">
                    </figure>
                    <div class="user-meta">
                        <h2 class="user-name">João Silva</h2>
                        <p class="user-handle">@jsilva_gym</p>
                        <span class="member-tag">ULTRA MEMBER</span>
                    </div>
                </div>

                <div class="qr-container">
                    <button class="btn-qr" id="entry-key">
                        <span class="icon"></span> Digital Entry Key
                    </button>

                    <div class="qr-display-area">
                        <img src="../images/random_qr.png" alt="User Access QR Code" class="qr-image">
                        <p class="qr-status">Ready to Scan</p>
                    </div>
                </div>

                <div class="user-identity">
                    <span class="archetype-tag">POWERLIFTER</span>
                    <blockquote class="user-motto">"Training for my first 500kg deadlift."</blockquote>
                </div>

            </section>
        </aside>
        <div class="main-content">

            <div class="profile-details">
                <div class="detail-item">
                    <span class="detail-label">Full Name</span>
                    <p class="detail-value">João Marques Silva</p>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email Address</span>
                    <p class="detail-value">joao.silva@gmail.com</p>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Member Since</span>
                    <p class="detail-value">January 2024</p>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Home Gym</span>
                    <p class="detail-value">Cubo Gym - Porto, Paranhos</p>
                </div>
            </div>

            <div class="hall-of-fame">
                <h3>HALL OF FAME</h3>
                <div class="stats-row">
                    <div class="stat-box">
                        <span class="stat-number">142</span>
                        <span class="stat-label">LIFE TIME VISITS</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number">26</span>
                        <span class="stat-label">CLASSES ATTENDED</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number">12</span>
                        <span class="stat-label">EARNED BADGES</span>
                    </div>
                </div>

                <div class="badge-container">
                    <span class="badge" title="Early Bird: 50 morning sessions"></span>
                    <span class="badge" title="A+ Student: 20 classes completed"></span>
                    <span class="badge" title="Century Club: 100 visits"></span>
                </div>
            </div>

            <div class="stats-section">
                <header class="stats-header">
                    <div class="header-titles">
                        <h3>TIME SPENT TRAINING</h3>
                        <p class="subtitle">Calculated via QR Check-in/out</p>
                    </div>
                    <select class="stats-filter">
                        <option>Last 7 Days</option>
                        <option>Last 30 Days</option>
                    </select>
                </header>

                <div class="chart-container">
                    <div class="chart">
                        <div class="bar-group">
                            <div class="bar" style="height: 45%;" data-time="55min"></div>
                            <span class="day-label">M</span>
                        </div>
                        <div class="bar-group">
                            <div class="bar" style="height: 80%;" data-time="1h 40min"></div>
                            <span class="day-label">T</span>
                        </div>
                        <div class="bar-group">
                            <div class="bar" style="height: 0%;" data-time="Rest"></div>
                            <span class="day-label">W</span>
                        </div>
                        <div class="bar-group">
                            <div class="bar" style="height: 65%;" data-time="1h 15min"></div>
                            <span class="day-label">T</span>
                        </div>
                        <div class="bar-group">
                            <div class="bar" style="height: 90%;" data-time="2h 10min"></div>
                            <span class="day-label">F</span>
                        </div>
                        <div class="bar-group">
                            <div class="bar" style="height: 30%;" data-time="45min"></div>
                            <span class="day-label">S</span>
                        </div>
                        <div class="bar-group">
                            <div class="bar" style="height: 0%;" data-time="Rest"></div>
                            <span class="day-label">S</span>
                        </div>
                    </div>

                    <div class="chart-legend">
                        <div class="legend-item">
                            <span class="legend-label">Weekly Total: </span>
                            <strong>6h 45min</strong>
                        </div>
                        <div class="legend-item">
                            <span class="legend-label">Daily Avg: </span>
                            <strong>1h 21min</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="profile-actions">
                <a href="edit-profile.php" class="btn-edit-profile">Edit Profile</a>
            </div>
        </div>
    </main>

<?php drawFooter(); ?>

</body>
</html>
