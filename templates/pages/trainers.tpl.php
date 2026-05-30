<?php function drawTrainersPage(Session $session, array $trainers, bool $isAdmin = false, array $adminData = []): void
{
    $classTypes = $adminData['classTypes'] ?? [];
    $gymList = $adminData['gymList'] ?? [];
    $editTrainer = $adminData['editTrainer'] ?? null;
    $editSpecs = $adminData['editSpecs'] ?? [];
    $editGyms = $adminData['editGyms'] ?? [];
    $msg = $adminData['msg'] ?? '';
    $error = $adminData['error'] ?? '';

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
    sort($allGyms);
    $allClasses = array_keys($allClasses);
    sort($allClasses);
    ?>

    <div class="trainers-filters filter-bar">
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
    </div>

    <main class="trainers-page<?= $isAdmin ? ' admin-page' : '' ?>">

        <?php if ($isAdmin): ?>
            <header class="admin-header">
                <div>
                    <h1><i class="fa fa-chalkboard-teacher"></i> Trainers</h1>
                    <p class="admin-sub"><?= count($trainers) ?> trainer<?= count($trainers) !== 1 ? 's' : '' ?>
                        registered</p>
                </div>
                <button class="btn-admin-primary" onclick="openCreateForm()">
                    <i class="fa fa-plus"></i> New Trainer
                </button>
            </header>

        <?php if ($msg): ?>
            <div class="admin-alert admin-alert--ok" id="trainerMsg"><i class="fa fa-circle-check"></i> <?= $msg ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="admin-alert admin-alert--err"><i
                        class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

            <section class="admin-form-card" id="trainerForm" <?= ($editTrainer || $error) ? '' : 'hidden' ?>>
                <h2 id="formTitle"><?= $editTrainer ? 'Edit Trainer' : 'New Trainer' ?></h2>
                <form method="POST" enctype="multipart/form-data" class="admin-form-grid">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="<?= $editTrainer ? 'update' : 'create' ?>"
                           id="formAction">
                    <input type="hidden" name="target_id" value="<?= $editTrainer['id'] ?? '' ?>">
                    <div class="admin-field">
                        <label>First Name</label>
                        <input type="text" name="first_name" required
                               value="<?= htmlspecialchars($editTrainer['first_name'] ?? '') ?>">
                    </div>
                    <div class="admin-field">
                        <label>Last Name</label>
                        <input type="text" name="last_name" required
                               value="<?= htmlspecialchars($editTrainer['last_name'] ?? '') ?>">
                    </div>
                    <div class="admin-field">
                        <label>Email</label>
                        <input type="email" name="email" required
                               value="<?= htmlspecialchars($editTrainer['email'] ?? '') ?>">
                    </div>
                    <?php if (!$editTrainer): ?>
                        <div class="admin-field">
                            <label>Username</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="admin-field">
                            <label>Password</label>
                            <input type="password" name="password" minlength="6" placeholder="Min 6 chars" required>
                        </div>
                    <?php endif; ?>
                    <div class="admin-field admin-field--full">
                        <label>Bio</label>
                        <textarea name="bio" rows="2"><?= htmlspecialchars($editTrainer['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="admin-field admin-field--full">
                        <label>Certifications</label>
                        <textarea name="certifications"
                                  rows="2"><?= htmlspecialchars($editTrainer['certifications'] ?? '') ?></textarea>
                    </div>
                    <div class="admin-field admin-field--full">
                        <label>Specializations</label>
                        <div class="admin-check-group">
                            <?php foreach ($classTypes as $ct): ?>
                                <label class="admin-check-label">
                                    <input type="checkbox" name="specializations[]" value="<?= $ct['id'] ?>"
                                            <?= in_array($ct['id'], $editSpecs) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($ct['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="admin-field admin-field--full">
                        <label>Gym Locations</label>
                        <div class="admin-check-group">
                            <?php foreach ($gymList as $g): ?>
                                <label class="admin-check-label">
                                    <input type="checkbox" name="gyms[]" value="<?= $g['id'] ?>"
                                            <?= in_array($g['id'], $editGyms) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($g['city'] . ' — ' . $g['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="admin-field admin-field--full">
                        <label>Photo <span class="admin-optional">(optional)</span></label>
                        <div class="admin-photo-picker">
                            <img class="admin-photo-preview" id="trainerPhotoPreview"
                                 src="<?= htmlspecialchars($editTrainer['profile_photo'] ?? '../images/profile_pic.webp') ?>"
                                 alt="">
                            <label class="admin-photo-label">
                                <i class="fa fa-camera"></i> Choose photo
                                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif"
                                       class="profile-file-input">
                            </label>
                        </div>
                    </div>
                    <div class="admin-form-actions">
                        <button type="submit" class="btn-admin-primary">
                            <?= $editTrainer ? '<i class="fa fa-save"></i> Save' : '<i class="fa fa-plus"></i> Create' ?>
                        </button>
                        <a href="/pages/trainers.php" class="btn-admin-ghost">Cancel</a>
                    </div>
                </form>

                <?php if ($editTrainer): ?>
                    <div class="admin-promote-section">
                        <form method="POST"
                              onsubmit="return confirm('Promote <?= htmlspecialchars(addslashes($editTrainer['first_name'])) ?> to admin? This cannot be undone.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="promote">
                            <input type="hidden" name="target_id" value="<?= (int)$editTrainer['id'] ?>">
                            <button type="submit" class="btn-admin-ghost btn-admin-ghost--warn">
                                <i class="fa fa-arrow-up-right-dots"></i> Promote to Admin
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!$isAdmin): ?>
            <section class="trainers-hero">
                <h1>Our Trainers</h1>
                <p>Meet the people who help you train harder, move better, and stay consistent.</p>
            </section>
        <?php endif; ?>

        <div class="trainers-grid">
            <?php foreach ($trainers

            as $trainer):
            $photo = $trainer['profile_photo'] ?? '../images/profile_pic.webp';
            $fullName = $trainer['first_name'] . ' ' . $trainer['last_name'];
            $gyms = $trainer['gyms'] ? explode(',', $trainer['gyms']) : [];
            $classes = $trainer['class_types'] ? explode(',', $trainer['class_types']) : [];
            $isLogged = $session->isLoggedIn();
            ?>
            <?php if ($isAdmin): ?>
            <div class="trainer-card-admin"
                 data-gyms="<?= htmlspecialchars(implode('|', array_map('trim', $gyms))) ?>"
                 data-classes="<?= htmlspecialchars(implode('|', array_map('trim', $classes))) ?>">
                <a class="trainer-card" href="/pages/trainer-profile.php?id=<?= (int)$trainer['id'] ?>">
                    <?php else: ?>
                        <<?= $isLogged ? 'a' : 'article' ?>
                        class="trainer-card"
                        data-gyms="<?= htmlspecialchars(implode('|', array_map('trim', $gyms))) ?>"
                        data-classes="<?= htmlspecialchars(implode('|', array_map('trim', $classes))) ?>"
                        <?= $isLogged ? 'href="/pages/trainer-profile.php?id=' . (int)$trainer['id'] . '"' : '' ?>>
                    <?php endif; ?>

                    <img class="trainer-photo" src="<?= htmlspecialchars($photo) ?>"
                         alt="<?= htmlspecialchars($fullName) ?>">
                    <div class="trainer-info">
                        <div class="trainer-name-row">
                            <h2><?= htmlspecialchars($fullName) ?></h2>
                            <p class="trainer-username">@<?= htmlspecialchars($trainer['username']) ?></p>
                        </div>
                        <?php if (!empty($trainer['bio'])): ?>
                            <p class="trainer-bio"><?= htmlspecialchars($trainer['bio']) ?></p>
                        <?php endif; ?>
                        <section class="trainer-section">
                            <h3>Gyms</h3>
                            <div class="trainer-tags">
                                <?php if (empty($gyms)): ?>
                                    <span>No gyms assigned</span>
                                <?php else: ?>
                                    <?php foreach ($gyms as $gym): ?>
                                        <span><?= htmlspecialchars(trim($gym)) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                        <section class="trainer-section">
                            <h3>Classes</h3>
                            <div class="trainer-tags">
                                <?php if (empty($classes)): ?>
                                    <span>No classes assigned</span>
                                <?php else: ?>
                                    <?php foreach ($classes as $class): ?>
                                        <span><?= htmlspecialchars(trim($class)) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>

                    <?php if ($isAdmin): ?>
                </a>
                <div class="admin-trainer-actions">
                    <a href="/pages/trainers.php?edit=<?= (int)$trainer['id'] ?>"
                       class="btn-admin-ghost btn-admin-ghost--sm"
                    <i class="fa fa-pen"></i> Edit
                    </a>
                    <form method="POST"
                          onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($trainer['first_name'])) ?>? This cannot be undone.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="delete">
                        <input type="hidden" name="target_id" value="<?= (int)$trainer['id'] ?>">
                        <button type="submit" class="btn-admin-sm btn-admin-sm--danger" title="Remove">
                            <i class="fa fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
        </<?= $isLogged ? 'a' : 'article' ?>>
    <?php endif; ?>
    <?php endforeach; ?>
        </div>
    </main>

    <script src="../../js/trainers.js"></script>
<?php } ?>
