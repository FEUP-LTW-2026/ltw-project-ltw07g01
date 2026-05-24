<?php function drawAboutUsPage(object $session, object $db) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | Cubo Gym</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/about.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Gothic&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php if ($session->isLoggedIn()): ?>
    <?php drawDashNavbar($session, $db, 'about'); ?>
<?php else: ?>
    <?php drawHeader($session); ?>
<?php endif; ?>

<main class="about-page">

    <section class="about-hero">
        <h1>OUR STORY</h1>
        <p>Built for every body. Driven by results.</p>
    </section>

    <section class="about-intro">
        <div class="about-intro-text">
            <h2>We are<br><span>CUBO</span> GYM</h2>
            <div class="divider"></div>
            <p>Founded in Porto, CUBO GYM was born from a simple belief: fitness should be accessible, motivating, and community-driven.</p>
            <p>From day one, we set out to build more than a gym — a space where every member, regardless of level, feels at home and challenged.</p>
            <p>Today we operate across multiple locations in Porto and Braga, with over 10,000 members and 25+ certified trainers.</p>
        </div>
        <div class="about-intro-image">
            <img src="../images/gym.png" alt="Interior of CUBO GYM">
        </div>
    </section>

    <section class="about-values">
        <h2>WHAT DRIVES US</h2>
        <div>
            <article>
                <i class="fa fa-users"></i>
                <h3>Community</h3>
                <p>We train together, grow together. Every class, every session builds connections that last beyond the gym floor.</p>
            </article>
            <article>
                <i class="fa fa-medal"></i>
                <h3>Quality</h3>
                <p>Top-tier equipment, certified trainers, and programmes designed to deliver real, measurable results.</p>
            </article>
            <article>
                <i class="fa fa-bullseye"></i>
                <h3>Results</h3>
                <p>Whatever your goal — weight loss, strength, endurance — our trainers and classes are built around your success.</p>
            </article>
            <article>
                <i class="fa fa-heart"></i>
                <h3>Inclusion</h3>
                <p>All levels. All backgrounds. No judgement. CUBO GYM is a place where everyone belongs.</p>
            </article>
        </div>
    </section>

    <section class="about-classes">
        <div class="class-card">
            <img src="../images/cycling.jpg" alt="Cycling class">
            <div class="class-overlay">
                <h3>Cycling</h3>
                <p>High-energy indoor cycling sessions for all levels.</p>
            </div>
        </div>
        <div class="class-card">
            <img src="../images/pilatu.jpg" alt="Pilates class">
            <div class="class-overlay">
                <h3>Pilates</h3>
                <p>Core strength and flexibility in a focused environment.</p>
            </div>
        </div>
        <div class="class-card">
            <img src="../images/bigGuy.jpg" alt="Strength training">
            <div class="class-overlay">
                <h3>Strength</h3>
                <p>Build muscle and power with expert-led training.</p>
            </div>
        </div>
    </section>

    <section class="join">
        <h2>READY TO START?</h2>
        <p>Join thousands of members already training at CUBO GYM.</p>
        <div>
            <?php if ($session->isLoggedIn()): ?>
                <a href="schedule.php" class="btn-filled">View Schedule</a>
                <a href="membership.php" class="btn-border">Membership Plans</a>
            <?php else: ?>
                <a href="/actions/register.php" class="btn-filled">Join Now</a>
                <a href="membership.php" class="btn-border">Membership Plans</a>
            <?php endif; ?>
        </div>
    </section>

</main>

<?php drawFooter(); ?>

</body>
</html>
<?php } ?>
