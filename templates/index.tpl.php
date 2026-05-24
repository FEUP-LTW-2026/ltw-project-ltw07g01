<?php function drawIndexPage(object $session) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CUBO GYM</title>
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Gothic&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="../js/index.js" defer></script>
</head>
<body>

<?php drawHeader($session); ?>

    <main>
        <section class="hero">
            <div class="hero-title">
                <h1>CUBO</h1>
                <h2>GYM</h2>
            </div>

            <div class="hero-buttons">
                <?php if ($session->isLoggedIn()): ?>
                    <p class="hero-slogan">ALL LEVELS. ALL GOALS. ONE GYM.</p>
                
                <?php else: ?>
                    <a href="/actions/login.php">LOG IN</a>
                    <div class="hero-divider"></div>
                    <a href="/actions/register.php">SIGN UP</a>
                
                <?php endif; ?>
            </div>
        </section>

        <section class="stats">
            <div class="stat-card">
                <img src="../images/membros.png" alt="logo">
                <p class="stat-number">+<span class = "counter" data-target="10000">0</span></p>
                <p class="stat-text">members</p>
            </div>

            <div class="stat-card">
                <img src="../images/star.png" alt="logo">
                <p class="stat-number"><span class="counter" data-target="4.7">0</span>/5</p>
                <p class="stat-text">average rating</p>
            </div>

            <div class="stat-card">
                <img src="../images/calendar.png" alt="logo">
                <p class="stat-number">+<span class="counter" data-target="200">0</span></p>
                <p class="stat-text">weekly classes</p>
            </div>

            <div class="stat-card">
                <img src="../images/halter.png" alt="logo">
                <p class="stat-number">+<span class="counter" data-target="25">0</span> certified</p>
                <p class="stat-text">personal trainers</p>
            </div>
        </section>

        <section class="about">
            <div class="about-text">
                <h2>We are<br><span>CUBO</span> GYM</h2>
                <div class="about-divider"></div>
                <p>All levels. All goals. One gym.</p>
                <p>Your journey starts here.</p>
                <p>No limits. No judgments.</p>

                <a href="about.php" class="about-button">Our Story</a>
            </div>

            <div class="about-image">
                <img src="../images/gym.png" alt="Interior of CUBO GYM">
            </div>
        </section>

        <section class="cards">
            <div class="card1">
                <h2>Amazing Group Classes</h2>
                <div class="about-divider1"></div>
                <p>Join our group classes and train in a motivating and energetic environment. Our sessions are designed for all fitness levels, helping you stay consistent while improving strength, endurance, and overall performance.</p>

                <a href="classes.php" class="card-button">View Classes</a>
            </div>
            <div class="card">
                <h2>Running Community</h2>
                <div class="about-divider"></div>
                <p>Take your workouts outside with our runs. Explore new routes, improve your stamina, and enjoy the energy of training in a dynamic group environment.</p>

                <a href="runs.php" class="card-button">View Runs</a>
            </div>
            <div class="card">
                <h2>Membership Plans</h2>
                <div class="about-divider"></div>
                <p>Choose the perfect plan for your fitness journey. Flexible options to suit your lifestyle and goals.</p>

                <a href="membership.php" class="card-button">Membership Plans</a>
            </div>
            <div class="card1">
                <h2>Certified Personal Trainers</h2>
                <div class="about-divider1"></div>
                <p>Work with our certified personal trainers to achieve your fitness goals. Get personalized attention and guidance tailored to your needs.</p>

                <a href="trainers.php" class="card-button">View Trainers</a>
            </div>
        </section>
    </main>
    
<?php drawFooter(); ?>

</body>
</html>
<?php } ?>