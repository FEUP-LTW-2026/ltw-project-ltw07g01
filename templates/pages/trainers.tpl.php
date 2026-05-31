<?php function drawTrainersPage(Session $session, bool $isAdmin = false, string $msg = ''): void { ?>

    <div class="trainers-filters filter-bar">
        <span class="filter-label"><i class="fa fa-sliders"></i> Filter</span>
        <select class="filter-select" id="trainer-filter-gym">
            <option value="all">All gyms</option>
        </select>
        <select class="filter-select" id="trainer-filter-class">
            <option value="all">All classes</option>
        </select>
        <button class="filter-clear" id="trainer-filter-clear" hidden>
            <i class="fa fa-xmark"></i> Clear
        </button>
        <span class="filter-count" id="trainer-filter-count"></span>
    </div>

    <main class="trainers-page<?= $isAdmin ? ' admin-page' : '' ?>"
          id="trainersMain"
          data-is-admin="<?= $isAdmin ? '1' : '0' ?>"
          data-is-logged="<?= $session->isLoggedIn() ? '1' : '0' ?>">

        <?php if ($isAdmin): ?>
            <header class="admin-header">
                <div>
                    <h1><i class="fa fa-chalkboard-teacher"></i> Trainers</h1>
                    <p class="admin-sub" id="trainerCount"></p>
                </div>
                <button class="btn-admin-primary" onclick="openCreateForm()">
                    <i class="fa fa-plus"></i> New Trainer
                </button>
            </header>

            <?php if ($msg): ?>
                <div class="admin-alert admin-alert--ok" id="trainerMsg">
                    <i class="fa fa-circle-check"></i> <?= $msg ?>
                </div>
            <?php endif; ?>

            <div class="admin-alert admin-alert--err" id="trainerFormError" hidden></div>

            <section class="admin-form-card" id="trainerForm" hidden>
                <h2 id="formTitle">New Trainer</h2>
                <form method="POST" action="/api/trainers.php" enctype="multipart/form-data" class="admin-form-grid">
                    <?= csrf_field() ?>
                    <div class="admin-field">
                        <label>First Name</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="admin-field">
                        <label>Last Name</label>
                        <input type="text" name="last_name" required>
                    </div>
                    <div class="admin-field">
                        <label>Email</label>
                        <input type="text" name="email" inputmode="email" autocomplete="email" required>
                    </div>
                    <div class="admin-field" id="usernameField">
                        <label>Username</label>
                        <input type="text" name="username">
                    </div>
                    <div class="admin-field" id="passwordField">
                        <label>Password</label>
                        <input type="password" name="password" minlength="6" placeholder="Min 6 chars">
                    </div>
                    <div class="admin-field admin-field--full">
                        <label>Bio</label>
                        <textarea name="bio" rows="2"></textarea>
                    </div>
                    <div class="admin-field admin-field--full">
                        <label>Certifications</label>
                        <textarea name="certifications" rows="2"></textarea>
                    </div>
                    <div class="admin-field admin-field--full">
                        <label>Specializations</label>
                        <div class="admin-check-group" id="specializationsCheckboxes"></div>
                    </div>
                    <div class="admin-field admin-field--full">
                        <label>Gym Locations</label>
                        <div class="admin-check-group" id="gymsCheckboxes"></div>
                    </div>
                    <div class="admin-field admin-field--full">
                        <label>Photo <span class="admin-optional">(optional)</span></label>
                        <div class="admin-photo-picker">
                            <img class="admin-photo-preview" id="trainerPhotoPreview"
                                 src="../images/profile_pic.webp" alt="">
                            <label class="admin-photo-label">
                                <i class="fa fa-camera"></i> Choose photo
                                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif"
                                       class="profile-file-input">
                            </label>
                        </div>
                    </div>
                    <div class="admin-form-actions">
                        <button type="submit" class="btn-admin-primary" id="formSubmitBtn">
                            <i class="fa fa-plus"></i> Create
                        </button>
                        <button type="button" class="btn-admin-ghost" onclick="closeForm()">Cancel</button>
                    </div>
                </form>

                <div class="admin-promote-section" id="promoteSection" hidden>
                    <form method="POST" action="/api/admins.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="">
                        <button type="submit" class="btn-admin-ghost btn-admin-ghost--warn">
                            <i class="fa fa-arrow-up-right-dots"></i> Promote to Admin
                        </button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!$isAdmin): ?>
            <section class="trainers-hero">
                <h1>Our Trainers</h1>
                <p>Meet the people who help you train harder, move better, and stay consistent.</p>
            </section>
        <?php endif; ?>

        <div class="admin-search-bar">
            <input type="search" id="trainerSearch" placeholder="Search by name, username or gym…">
        </div>

        <div class="trainers-grid" id="trainersGrid"></div>

    </main>

    <script src="../../js/trainers.js"></script>
<?php } ?>
