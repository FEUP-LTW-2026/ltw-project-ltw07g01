<?php function drawLocations(Session $session, array $locations): void
{ ?>
    <main class="locations-page">

        <section class="locations-hero">
            <h1>OUR LOCATIONS</h1>
            <p>Porto & Braga — find your nearest CUBO GYM</p>
        </section>

        <div class="locations-grid">
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
        </div>

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

<?php function drawAdminLocations(array $locations, ?array $editItem, string $msg, string $error): void
{ ?>
    <main class="admin-page">
        <div class="admin-header">
            <div>
                <h1><i class="fa fa-location-dot"></i> Manage Locations</h1>
                <p class="admin-sub"><?= count($locations) ?> location<?= count($locations) !== 1 ? 's' : '' ?></p>
            </div>
            <button class="btn-admin-primary" onclick="openCreateForm()">
                <i class="fa fa-plus"></i> Add Location
            </button>
        </div>

        <?php if ($msg): ?>
        <div class="admin-alert admin-alert--ok"><i class="fa fa-circle-check"></i> <?= $msg ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="admin-alert admin-alert--err"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="admin-form-card" id="locForm" <?= ($editItem || $error) ? '' : 'hidden' ?>>
            <h2 id="formTitle"><?= $editItem ? 'Edit Location' : 'Add Location' ?></h2>
            <form method="POST" enctype="multipart/form-data" class="admin-form-grid">
                <input type="hidden" name="_action" value="<?= $editItem ? 'update' : 'create' ?>">
                <input type="hidden" name="target_id" value="<?= $editItem['id'] ?? '' ?>">
                <div class="admin-field">
                    <label>Name</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($editItem['name'] ?? '') ?>">
                </div>
                <div class="admin-field">
                    <label>City</label>
                    <input type="text" name="city" required value="<?= htmlspecialchars($editItem['city'] ?? '') ?>">
                </div>
                <div class="admin-field admin-field--full">
                    <label>Address <span class="admin-optional">(optional)</span></label>
                    <input type="text" name="address" value="<?= htmlspecialchars($editItem['address'] ?? '') ?>">
                </div>
                <div class="admin-field admin-field--full">
                    <label>Photo <span class="admin-optional">(optional)</span></label>
                    <div class="admin-photo-picker">
                        <img class="admin-photo-preview" id="locPhotoPreview"
                             src="<?= htmlspecialchars($editItem['photo'] ?? '../images/location-antas.png') ?>" alt="">
                        <label class="admin-photo-label">
                            <i class="fa fa-camera"></i> Choose photo
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" class="profile-file-input">
                        </label>
                    </div>
                </div>
                <div class="admin-form-actions">
                    <button type="submit" class="btn-admin-primary">
                        <?= $editItem ? '<i class="fa fa-save"></i> Save' : '<i class="fa fa-plus"></i> Add' ?>
                    </button>
                    <a href="/pages/locations.php" class="btn-admin-ghost">Cancel</a>
                </div>
            </form>
        </div>

        <div class="loc-admin-grid">
            <?php foreach ($locations as $loc):
                $photo = $loc['photo'] ?? '../images/location-antas.png';
            ?>
            <div class="loc-admin-card">
                <img class="loc-admin-card-img" src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($loc['name']) ?>">
                <div class="loc-admin-card-body">
                    <p class="loc-admin-card-city"><?= htmlspecialchars($loc['city']) ?></p>
                    <h3 class="loc-admin-card-name"><?= htmlspecialchars($loc['name']) ?></h3>
                    <?php if ($loc['address']): ?>
                    <p class="loc-admin-card-address"><?= htmlspecialchars($loc['address']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="loc-admin-card-actions">
                    <a href="/pages/locations.php?edit=<?= $loc['id'] ?>" class="btn-admin-sm" title="Edit">
                        <i class="fa fa-pen"></i>
                    </a>
                    <form method="POST" class="form-inline"
                          onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($loc['name'])) ?>?')">
                        <input type="hidden" name="_action" value="delete">
                        <input type="hidden" name="target_id" value="<?= $loc['id'] ?>">
                        <button type="submit" class="btn-admin-sm btn-admin-sm--danger" title="Remove">
                            <i class="fa fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($locations)): ?>
            <p class="admin-empty-msg">No locations registered yet.</p>
            <?php endif; ?>
        </div>
    </main>

    <script>
    function openCreateForm() {
        var f = document.getElementById('locForm');
        f.hidden = false;
        document.getElementById('formTitle').textContent = 'Add Location';
        document.getElementById('locPhotoPreview').src = '../images/location-antas.png';
        f.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    document.querySelectorAll('.admin-photo-picker input[type="file"]').forEach(function(inp) {
        inp.addEventListener('change', function() {
            var file = this.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            var reader = new FileReader();
            var preview = this.closest('.admin-photo-picker').querySelector('.admin-photo-preview');
            reader.onload = function(e) { preview.src = e.target.result; };
            reader.readAsDataURL(file);
        });
    });
    </script>
<?php } ?>
