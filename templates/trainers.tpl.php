<?php function drawTrainersPage(Session $session, array $trainers): void { ?>
<?php
    $allGyms = [];
    $allClasses = [];

    foreach ($trainers as $trainer) {
        foreach (explode(',', $trainer['gyms'] ?? '') as $gym) {
            $gym = trim($gym);
            if ($gym !== '') $allGyms[$gym] = true;
        }
        foreach (explode(',', $trainer['class_types'] ?? '') as $class) {
            $class = trim($class);
            if ($class !== '') $allClasses[$class] = true;
        }
    }

    $allGyms = array_keys($allGyms);
    $allClasses = array_keys($allClasses);
    sort($allGyms);
    sort($allClasses);
?>

<main class="trainers-page">

    <section class="trainers-hero">
        <h1>Our Trainers</h1>
        <p>
            Meet the people who help you train harder,
            move better, and stay consistent.
        </p>
    </section>

    <section class="trainers-filters filter-bar">
        <span class="filter-label"><i class="fa fa-sliders"></i> Filter</span>

        <select class="filter-select" id="trainer-filter-gym">
            <option value="all">All gyms</option>
            <?php foreach ($allGyms as $gym): ?>
                <option value="<?= htmlspecialchars($gym) ?>"><?= htmlspecialchars($gym) ?></option>
            <?php endforeach; ?>
        </select>

        <select class="filter-select" id="trainer-filter-class">
            <option value="all">All classes</option>
            <?php foreach ($allClasses as $class): ?>
                <option value="<?= htmlspecialchars($class) ?>"><?= htmlspecialchars($class) ?></option>
            <?php endforeach; ?>
        </select>

        <button class="filter-clear" id="trainer-filter-clear" hidden>
            <i class="fa fa-xmark"></i> Clear
        </button>
        <span class="filter-count" id="trainer-filter-count"></span>
    </section>

    <section class="trainers-grid">
        <?php foreach ($trainers as $trainer): ?>
            <?php
                $photo = $trainer['profile_photo'] ?? '../images/profile_pic.webp';
                $fullName =
                    $trainer['first_name'] . ' ' .
                    $trainer['last_name'];
                $gyms =
                    $trainer['gyms']
                    ? explode(',', $trainer['gyms'])
                    : [];
                $classes =
                    $trainer['class_types']
                    ? explode(',', $trainer['class_types'])
                    : [];
                $isLogged = $session->isLoggedIn();
                $cardTag = $isLogged ? 'a' : 'article';
                $cardHref = $isLogged
                    ? 'href="/pages/trainer-profile.php?id=' . (int)$trainer['id'] . '"'
                    : '';
            ?>
            <<?= $cardTag ?>
                class="trainer-card"
                data-gyms="<?= htmlspecialchars(implode('|', array_map('trim', $gyms))) ?>"
                data-classes="<?= htmlspecialchars(implode('|', array_map('trim', $classes))) ?>"
                <?= $cardHref ?>>
                <img class="trainer-photo"
                     src="<?= htmlspecialchars($photo) ?>"
                     alt="<?= htmlspecialchars($fullName) ?>">
                
                    <div class="trainer-info">
                    <div class="trainer-name-row">
                        <h2>
                            <?= htmlspecialchars($fullName) ?>
                        </h2>
                        <p class="trainer-username">
                            @<?= htmlspecialchars($trainer['username']) ?>
                        </p>

                    </div>

                    <?php if (!empty($trainer['bio'])): ?>
                        <p class="trainer-bio">
                            <?= htmlspecialchars($trainer['bio']) ?>
                        </p>
                    <?php endif; ?>

                    <div class="trainer-section">
                        <h3>Gyms</h3>
                        <div class="trainer-tags">
                            <?php if (empty($gyms)): ?>
                                <span>No gyms assigned</span>
                            <?php else: ?>
                                <?php foreach ($gyms as $gym): ?>
                                    <span>
                                        <?= htmlspecialchars(trim($gym)) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="trainer-section">
                        <h3>Classes</h3>
                        <div class="trainer-tags">
                            <?php if (empty($classes)): ?>
                                <span>No classes assigned</span>
                            <?php else: ?>
                                <?php foreach ($classes as $class): ?>
                                    <span>
                                        <?= htmlspecialchars(trim($class)) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </<?= $cardTag ?>>
        <?php endforeach; ?>
    </section>
</main>
<script src="../js/trainers.js"></script>
<?php } ?>
