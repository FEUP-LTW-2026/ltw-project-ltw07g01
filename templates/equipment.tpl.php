<?php function drawEquipment(array $equipment): void { ?>
<main class="equipment-page">
    <section class="equipment-filters filter-bar">
        <span class="filter-label"><i class="fa fa-sliders"></i> Filter</span>

        <select class="filter-select" id="filter-location">
            <option value="all">All Locations</option>
            <option value="Antas">Antas</option>
            <option value="Matosinhos">Matosinhos</option>
            <option value="Braga">Braga</option>
        </select>

        <select class="filter-select" id="filter-body">
            <option value="all">All Body Parts</option>
            <option value="Chest">Chest</option>
            <option value="Shoulders">Shoulders</option>
            <option value="Triceps">Triceps</option>
            <option value="Biceps">Biceps</option>
            <option value="Legs">Legs</option>
            <option value="Back">Back</option>
        </select>

        <select class="filter-select" id="filter-status">
            <option value="all">All Status</option>
            <option value="available">Available</option>
            <option value="out">Out of Service</option>
        </select>

        <button class="filter-clear" id="equipment-filter-clear" hidden>
            <i class="fa fa-xmark"></i> Clear
        </button>
        <span class="filter-count" id="equipment-filter-count"></span>
    </section>

    <section class="equipment-hero">
        <h1>Equipment</h1>
        <p>Check the current state of our machines and training areas.</p>
    </section>

    <?php
    $equipmentImages = [
        'Smith Machine'  => '/images/equipment/smith-machine.png',
        'Chest Press'    => '/images/equipment/chest-press.png',
        'Bench Press'    => '/images/equipment/bench-press.png',
        'Shoulder Press'  => '/images/equipment/shoulder-press.jpg',
        'Tricep Pushdown' => '/images/equipment/tricep-pushdown.png',
        'Lat Pulldown'    => '/images/equipment/lat-pulldown.png',
        'Rowing Machine'     => '/images/equipment/rowing-machine.png',
        'Bicep Curl Machine' => '/images/equipment/bicep-curl.png',
        'Leg Extension' => '/images/equipment/leg-extension.png',
        'Leg Press'     => '/images/equipment/leg-press.png',
    ];
    $equipmentMuscles = [
        'Smith Machine'       => 'Chest, shoulders and triceps. Also effective for squats targeting quads, glutes and hamstrings.',
        'Bench Press'         => 'Primary: pectorals. Secondary: anterior deltoids and triceps.',
        'Chest Press'         => 'Primary: pectorals. Secondary: triceps and anterior deltoids.',
        'Lat Pulldown'        => 'Primary: latissimus dorsi. Secondary: biceps and rear deltoids.',
        'Bicep Curl Machine'  => 'Primary: biceps brachii. Secondary: brachialis and brachioradialis.',
        'Tricep Pushdown'     => 'Primary: triceps brachii. Secondary: anconeus.',
        'Shoulder Press'      => 'Primary: deltoids. Secondary: triceps and upper trapezius.',
        'Leg Press'           => 'Primary: quadriceps and glutes. Secondary: hamstrings and calves.',
        'Leg Extension'       => 'Primary: quadriceps (rectus femoris, vastus group).',
        'Rowing Machine'      => 'Primary: latissimus dorsi and rhomboids. Secondary: biceps, rear deltoids and core.',
    ];
    $equipmentMuscleDiagrams = [
        'Smith Machine'       => ['/images/equipment/muscles/chest.png', '/images/equipment/muscles/deltoid.png', '/images/equipment/muscles/triceps.png', '/images/equipment/muscles/quadriceps.png'],
        'Bench Press'         => ['/images/equipment/muscles/chest.png', '/images/equipment/muscles/deltoid.png', '/images/equipment/muscles/triceps.png'],
        'Chest Press'         => ['/images/equipment/muscles/chest.png', '/images/equipment/muscles/triceps.png', '/images/equipment/muscles/deltoid.png'],
        'Lat Pulldown'        => ['/images/equipment/muscles/upper-back.png', '/images/equipment/muscles/biceps.png'],
        'Bicep Curl Machine'  => ['/images/equipment/muscles/biceps.png'],
        'Tricep Pushdown'     => ['/images/equipment/muscles/triceps.png'],
        'Shoulder Press'      => ['/images/equipment/muscles/deltoid.png', '/images/equipment/muscles/triceps.png', '/images/equipment/muscles/upper-back.png'],
        'Leg Press'           => ['/images/equipment/muscles/quadriceps.png', '/images/equipment/muscles/glutes.png', '/images/equipment/muscles/hamstrings.png'],
        'Leg Extension'       => ['/images/equipment/muscles/quadriceps.png'],
        'Rowing Machine'      => ['/images/equipment/muscles/middle-back.png', '/images/equipment/muscles/upper-back.png', '/images/equipment/muscles/biceps.png'],
    ];
    ?>
    <section class="equipment-grid">
        <?php foreach ($equipment as $item):
            $img       = $equipmentImages[$item['name']] ?? null;
            $muscles   = $equipmentMuscles[$item['name']] ?? null;
            $diagrams  = $equipmentMuscleDiagrams[$item['name']] ?? [];
            $available = (int)$item['is_available'] === 1;
        ?>
            <div class="equipment-card"
                 data-location="<?= htmlspecialchars($item['gym_name']) ?>"
                 data-body="<?= htmlspecialchars($item['body_part']) ?>"
                 data-status="<?= $available ? 'available' : 'out' ?>"
                 data-name="<?= htmlspecialchars($item['name']) ?>"
                 data-gym="<?= htmlspecialchars($item['gym_name']) ?>"
                 data-img="<?= htmlspecialchars($img ?? '') ?>"
                 data-muscles="<?= htmlspecialchars($muscles ?? '') ?>"
                 data-diagrams="<?= htmlspecialchars(implode(',', $diagrams)) ?>"
                 onclick="openEquipModal(this)"
                 style="cursor:pointer">

                <?php if ($img): ?>
                <div class="equipment-card-img">
                    <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                </div>
                <?php endif; ?>

                <h2><?= htmlspecialchars($item['name']) ?></h2>
                <p><?= htmlspecialchars($item['gym_name']) ?></p>

                <?php if ($available): ?>
                    <span class="status available">Available</span>
                <?php else: ?>
                    <span class="status unavailable">Out of service</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>

    <div class="equip-modal-backdrop" id="equipModalBackdrop" onclick="closeEquipModal()"></div>
    <div class="equip-modal" id="equipModal" aria-hidden="true">
        <button class="equip-modal-close" onclick="closeEquipModal()"><i class="fa fa-xmark"></i></button>
        <div class="equip-modal-img-wrap" id="equipModalImgWrap">
            <img id="equipModalImg" src="" alt="">
        </div>
        <div class="equip-modal-body">
            <h2 id="equipModalName"></h2>
            <div class="equip-modal-meta">
                <span><i class="fa fa-location-dot"></i> <span id="equipModalGym"></span></span>
                <span><i class="fa fa-person-running"></i> <span id="equipModalBody"></span></span>
            </div>
            <div class="equip-modal-muscles" id="equipModalMusclesWrap">
                <p class="equip-modal-muscles-label"><i class="fa fa-fire"></i> Target Muscles</p>
                <div class="equip-modal-diagrams" id="equipModalDiagrams"></div>
                <p id="equipModalMuscles"></p>
            </div>
            <span class="equip-modal-status" id="equipModalStatus"></span>
        </div>
    </div>
</main>
<script src="../js/equipment.js"></script>
<?php } ?>
