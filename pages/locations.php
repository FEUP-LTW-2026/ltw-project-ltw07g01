<?php
declare(strict_types=1);

require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/layout/common.tpl.php');
require_once(__DIR__ . '/../templates/pages/locations.tpl.php');

$session = new Session();
$db = getDatabaseConnection();

$isAdmin = false;
if ($session->isLoggedIn()) {
    $s = $db->prepare('SELECT 1 FROM admins WHERE user_id = :id');
    $s->execute([':id' => (int)$session->getId()]);
    $isAdmin = (bool)$s->fetch();
}

$msg   = '';
$error = '';

function saveLocationPhoto(PDO $db, int $id, ?array $file): void {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) return;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime    = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) return;
    $destDir = __DIR__ . '/../images/location_photos/';
    @mkdir($destDir, 0755, true);
    $fn = 'loc_' . $id . '_' . time() . '.' . $allowed[$mime];
    if (move_uploaded_file($file['tmp_name'], $destDir . $fn)) {
        $db->prepare('UPDATE gym_locations SET photo=? WHERE id=?')
           ->execute(['../images/location_photos/' . $fn, $id]);
    }
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $name    = trim($_POST['name']    ?? '');
        $city    = trim($_POST['city']    ?? '');
        $address = trim($_POST['address'] ?? '');
        if (!$name || !$city) {
            $error = 'Name and city are required.';
        } else {
            $db->prepare('INSERT INTO gym_locations (name, city, address) VALUES (?,?,?)')
               ->execute([$name, $city, $address ?: null]);
            $newId = (int)$db->lastInsertId();
            saveLocationPhoto($db, $newId, $_FILES['photo'] ?? null);
            header('Location: /pages/locations.php?msg=Location+added.'); exit;
        }

    } elseif ($action === 'update') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $name     = trim($_POST['name']    ?? '');
        $city     = trim($_POST['city']    ?? '');
        $address  = trim($_POST['address'] ?? '');
        if (!$targetId || !$name || !$city) {
            $error = 'Invalid data.';
        } else {
            $db->prepare('UPDATE gym_locations SET name=?, city=?, address=? WHERE id=?')
               ->execute([$name, $city, $address ?: null, $targetId]);
            saveLocationPhoto($db, $targetId, $_FILES['photo'] ?? null);
            header('Location: /pages/locations.php?msg=Location+updated.'); exit;
        }

    } elseif ($action === 'delete') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            $db->prepare('DELETE FROM gym_locations WHERE id=?')->execute([$targetId]);
            header('Location: /pages/locations.php?msg=Location+removed.'); exit;
        }
    }
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

$stmt = $db->prepare('SELECT id, name, city, address, photo FROM gym_locations ORDER BY city, name');
$stmt->execute();
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($isAdmin) {
    $editItem = null;
    if (isset($_GET['edit'])) {
        $editId = (int)$_GET['edit'];
        $s = $db->prepare('SELECT id, name, city, address, photo FROM gym_locations WHERE id=?');
        $s->execute([$editId]);
        $editItem = $s->fetch() ?: null;
    }

    drawDashHeader($session, $db, 'locations', ['locations']);
    ?>

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

    <?php
    drawFooter();

} else {
    $session->isLoggedIn()
        ? drawDashHeader($session, $db, 'locations', ['locations'])
        : drawHeader($session, ['locations']);

    drawLocations($session, $locations);
    drawFooter();
}
