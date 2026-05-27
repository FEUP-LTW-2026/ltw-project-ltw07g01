<?php function drawLocations(Session $session, array $locations): void
{ ?>
    <main class="locations-page">

        <section class="locations-hero">
            <h1>OUR LOCATIONS</h1>
            <p>Porto & Braga — find your nearest CUBO GYM</p>
        </section>

        <section class="locations-grid">
            <?php foreach ($locations as $loc): ?>
                <article>
                    <div class="loc-header">
                        <i class="fa fa-location-dot"></i>
                        <div>
                            <h2><?= htmlspecialchars($loc['name']) ?></h2>
                            <span><?= htmlspecialchars($loc['city']) ?></span>
                        </div>
                    </div>
                    <p><?= htmlspecialchars($loc['address'] ?? '—') ?></p>
                    <img src="<?= htmlspecialchars($loc['photo'] ?? '../images/location-antas.png') ?>"
                         alt="<?= htmlspecialchars($loc['name']) ?> gym interior">
                    <ul>
                        <li><i class="fa fa-clock"></i> Mon–Fri 6:00–23:00</li>
                        <li><i class="fa fa-clock"></i> Sat–Sun 8:00–20:00</li>
                        <li><i class="fa fa-dumbbell"></i> Free weights & machines</li>
                        <li><i class="fa fa-person-running"></i> Cardio zone</li>
                        <li><i class="fa fa-shower"></i> Locker rooms & showers</li>
                    </ul>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="locations-join">
            <h2>TRAIN ANYWHERE</h2>
            <p>All memberships include access to every CUBO GYM location.</p>
            <?php if (!$session->isLoggedIn()): ?>
                <a href="/actions/register.php" class="btn-filled">Join Now</a>
            <?php else: ?>
                <a href="/pages/schedule.php" class="btn-filled">Browse Classes</a>
            <?php endif; ?>
        </section>

    </main>
<?php } ?>
