<?php function drawEquipment(array $equipment): void
{ ?>
    <main class="equipment-page">
        <section class="equipment-filters filter-bar" aria-label="Equipment filters">
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
        require __DIR__ . '/../../utils/equipment-data.php';
        ?>
        <section class="equipment-grid">
            <?php foreach ($equipment as $item):
                $img = $equipmentImages[$item['name']] ?? null;
                $muscles = $equipmentMuscles[$item['name']] ?? null;
                $diagrams = array_map(fn($m) => '/images/equipment/muscles/' . $m . '.png', $equipmentMuscleDiagrams[$item['name']] ?? []);
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
            <div class="equip-modal-img-wrap" id="equipModalImgWrap" hidden>
                <img id="equipModalImg" src="/images/gym.png" alt="">
            </div>
            <div class="equip-modal-body">
                <h2 id="equipModalName">Equipment Details</h2>
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
    <script src="../../js/equipment.js"></script>
<?php } ?>

<?php function drawAdminEquipment(array $allEquip, array $byGym, array $gymList, ?array $editItem, string $msg, string $error): void
{
    require __DIR__ . '/../../utils/equipment-data.php';
    ?>
    <main class="admin-page">
        <div class="admin-header">
            <div>
                <h1><i class="fa fa-dumbbell"></i> Manage Equipment</h1>
                <p class="admin-sub"><?= count($allEquip) ?> item<?= count($allEquip) !== 1 ? 's' : '' ?>
                    across <?= count($byGym) ?> gym<?= count($byGym) !== 1 ? 's' : '' ?></p>
            </div>
            <button class="btn-admin-primary" onclick="openCreateForm()">
                <i class="fa fa-plus"></i> Add Equipment
            </button>
        </div>

        <?php if ($msg): ?>
            <div class="admin-alert admin-alert--ok" id="equipMsg"><i class="fa fa-circle-check"></i> <?= $msg ?></div>
            <script>setTimeout(function () {
                    var el = document.getElementById('equipMsg');
                    if (el) {
                        el.style.transition = 'opacity .4s';
                        el.style.opacity = '0';
                        setTimeout(function () {
                            el.remove();
                        }, 420);
                    }
                }, 3500);</script>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="admin-alert admin-alert--err"><i
                        class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="admin-form-card" id="equipForm" <?= ($editItem || $error) ? '' : 'hidden' ?>>
            <h2 id="formTitle"><?= $editItem ? 'Edit Equipment' : 'Add Equipment' ?></h2>
            <form method="POST" class="admin-form-grid">
                <input type="hidden" name="_action" value="<?= $editItem ? 'update' : 'create' ?>" id="formAction">
                <input type="hidden" name="target_id" value="<?= $editItem['id'] ?? '' ?>">
                <div class="admin-field">
                    <label>Name</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($editItem['name'] ?? '') ?>">
                </div>
                <div class="admin-field">
                    <label>Body Part</label>
                    <input type="text" name="body_part" required placeholder="e.g. Chest, Legs, Back"
                           value="<?= htmlspecialchars($editItem['body_part'] ?? '') ?>">
                </div>
                <div class="admin-field">
                    <label>Gym Location</label>
                    <select name="gym_id" required>
                        <option value="">— select gym —</option>
                        <?php foreach ($gymList as $g): ?>
                            <option value="<?= $g['id'] ?>"
                                    <?= isset($editItem) && $editItem['gym_id'] == $g['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g['city'] . ' — ' . $g['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-actions">
                    <button type="submit" class="btn-admin-primary">
                        <?= $editItem ? '<i class="fa fa-save"></i> Save' : '<i class="fa fa-plus"></i> Add' ?>
                    </button>
                    <a href="/pages/equipment.php" class="btn-admin-ghost">Cancel</a>
                </div>
            </form>
        </div>

        <?php foreach ($byGym as $gymData): ?>
            <div class="admin-table-wrap admin-table-wrap--spaced">
                <div class="equip-gym-header">
                <span class="equip-gym-name">
                    <i class="fa fa-location-dot"></i>
                    <?= htmlspecialchars($gymData['gym_city'] . ' — ' . $gymData['gym_name']) ?>
                </span>
                    <span class="equip-gym-count"><?= count($gymData['items']) ?> item<?= count($gymData['items']) !== 1 ? 's' : '' ?></span>
                </div>
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Body Part</th>
                        <th>Target Muscles</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($gymData['items'] as $eq): ?>
                        <tr>
                            <td><?= htmlspecialchars($eq['name']) ?></td>
                            <td class="admin-dim"><?= htmlspecialchars($eq['body_part']) ?></td>
                            <td class="equip-admin-muscles">
                                <?php foreach (array_map(fn($m) => '/images/equipment/muscles/' . $m . '.png', $equipmentMuscleDiagrams[$eq['name']] ?? []) as $mImg): ?>
                                    <img src="<?= htmlspecialchars($mImg) ?>" alt="" class="equip-admin-muscle-img">
                                <?php endforeach; ?>
                                <?php if (empty($equipmentMuscleDiagrams[$eq['name']] ?? [])): ?>
                                    <span class="admin-dim">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                            <span class="admin-badge admin-badge--<?= $eq['is_available'] ? 'active' : 'inactive' ?>">
                                <?= $eq['is_available'] ? 'Available' : 'Unavailable' ?>
                            </span>
                            </td>
                            <td>
                                <div class="admin-row-actions">
                                    <a href="/pages/equipment.php?edit=<?= $eq['id'] ?>" class="btn-admin-sm"
                                       title="Edit">
                                        <i class="fa fa-pen"></i>
                                    </a>
                                    <button class="btn-admin-sm btn-admin-sm--ok" title="Toggle availability"
                                            onclick="toggleEquip(<?= $eq['id'] ?>, this)">
                                        <i class="fa fa-<?= $eq['is_available'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                                    </button>
                                    <form method="POST" class="form-inline"
                                          onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($eq['name'])) ?>?')">
                                        <input type="hidden" name="_action" value="delete">
                                        <input type="hidden" name="target_id" value="<?= $eq['id'] ?>">
                                        <button type="submit" class="btn-admin-sm btn-admin-sm--danger" title="Remove">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <?php if (empty($allEquip)): ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <tbody>
                    <tr>
                        <td class="admin-empty">No equipment registered yet.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function openCreateForm() {
            var f = document.getElementById('equipForm');
            f.hidden = false;
            document.getElementById('formTitle').textContent = 'Add Equipment';
            f.scrollIntoView({behavior: 'smooth', block: 'start'});
        }

        function toggleEquip(id, btn) {
            var fd = new FormData();
            fd.append('_action', 'toggle');
            fd.append('target_id', id);
            fd.append('ajax', '1');
            fetch('/pages/equipment.php', {method: 'POST', body: fd})
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (!data.ok) return;
                    var avail = data.is_available;
                    var icon = btn.querySelector('i');
                    icon.className = 'fa fa-' + (avail ? 'toggle-on' : 'toggle-off');
                    var row = btn.closest('tr');
                    var badge = row.querySelector('.admin-badge');
                    badge.className = 'admin-badge admin-badge--' + (avail ? 'active' : 'inactive');
                    badge.textContent = avail ? 'Available' : 'Unavailable';
                });
        }
    </script>
<?php } ?>
