<?php function drawEquipmentPage(object $session, array $equipment) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment | CUBO GYM</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/equipment.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Gothic&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="../js/equipment.js" defer></script>
</head>
<body>

<?php drawHeader($session); ?>

<main class="equipment-page">
    <section class="equipment-hero">
        <h1>Equipment</h1>
        <p>Check the current state of our machines and training areas.</p>
    </section>

    <section class="equipment-filters">
        <select id="filter-location">
            <option value="all">All Locations</option>
            <option value="Antas">Antas</option>
            <option value="Matosinhos">Matosinhos</option>
            <option value="Braga">Braga</option>
        </select>

        <select id="filter-body">
            <option value="all">All Body Parts</option>
            <option value="Chest">Chest</option>
            <option value="Shoulders">Shoulders</option>
            <option value="Triceps">Triceps</option>
            <option value="Biceps">Biceps</option>
            <option value="Legs">Legs</option>
            <option value="Back">Back</option>
        </select>

        <select id="filter-status">
            <option value="all">All Status</option>
            <option value="available">Available</option>
            <option value="out">Out of Service</option>
        </select>
    </section>

    <section class="equipment-grid">
        <?php foreach ($equipment as $item): ?>
            <div class="equipment-card"
                 data-location="<?= htmlspecialchars($item['gym_name']) ?>"
                 data-body="<?= htmlspecialchars($item['body_part']) ?>"
                 data-status="<?= (int)$item['is_available'] === 1 ? 'available' : 'out' ?>">

                <h2><?= htmlspecialchars($item['name']) ?></h2>
                <p><?= htmlspecialchars($item['gym_name']) ?></p>

                <?php if ((int)$item['is_available'] === 1): ?>
                    <span class="status available">Available</span>
                <?php else: ?>
                    <span class="status unavailable">Out of service</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
</main>

<?php drawFooter(); ?>

</body>
</html>
<?php } ?>