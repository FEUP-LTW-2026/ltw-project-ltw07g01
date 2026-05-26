<?php
declare(strict_types=1);
require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');
require_once(__DIR__ . '/../templates/equipment.tpl.php');

$session = new Session();

if (!$session->isLoggedIn()) {
    header('Location: /actions/login.php');
    exit();
}

$db = getDatabaseConnection();
$userId  = (int)$session->getId();
$isAdmin = false;
$s = $db->prepare('SELECT 1 FROM admins WHERE user_id = :id');
$s->execute([':id' => $userId]);
$isAdmin = (bool)$s->fetch();

$msg   = '';
$error = '';

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $name     = trim($_POST['name']      ?? '');
        $gymId    = (int)($_POST['gym_id']   ?? 0);
        $bodyPart = trim($_POST['body_part'] ?? '');
        if (!$name || !$gymId || !$bodyPart) {
            $error = 'All fields are required.';
        } else {
            $db->prepare('INSERT INTO equipment (name, gym_id, body_part, is_available) VALUES (?,?,?,1)')
               ->execute([$name, $gymId, $bodyPart]);
            header('Location: /pages/equipment.php?msg=Equipment+added.'); exit;
        }

    } elseif ($action === 'update') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $name     = trim($_POST['name']       ?? '');
        $gymId    = (int)($_POST['gym_id']    ?? 0);
        $bodyPart = trim($_POST['body_part']  ?? '');
        if (!$targetId || !$name || !$gymId || !$bodyPart) {
            $error = 'Invalid data.';
        } else {
            $db->prepare('UPDATE equipment SET name=?, gym_id=?, body_part=? WHERE id=?')
               ->execute([$name, $gymId, $bodyPart, $targetId]);
            header('Location: /pages/equipment.php?msg=Equipment+updated.'); exit;
        }

    } elseif ($action === 'toggle') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            $db->prepare('UPDATE equipment SET is_available = NOT is_available WHERE id=?')->execute([$targetId]);
            header('Location: /pages/equipment.php?msg=Status+updated.'); exit;
        }

    } elseif ($action === 'delete') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            $db->prepare('DELETE FROM equipment WHERE id=?')->execute([$targetId]);
            header('Location: /pages/equipment.php?msg=Equipment+removed.'); exit;
        }
    }
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

if ($isAdmin) {
    $editItem = null;
    if (isset($_GET['edit'])) {
        $editId = (int)$_GET['edit'];
        $s = $db->prepare('SELECT id, name, gym_id, body_part, is_available FROM equipment WHERE id=?');
        $s->execute([$editId]);
        $editItem = $s->fetch() ?: null;
    }

    $gymList = $db->query('SELECT id, name, city FROM gym_locations ORDER BY city, name')->fetchAll(PDO::FETCH_ASSOC);

    $allEquip = $db->query(
        'SELECT e.id, e.name, e.body_part, e.is_available, e.gym_id,
                gl.name AS gym_name, gl.city AS gym_city
         FROM equipment e
         JOIN gym_locations gl ON gl.id = e.gym_id
         ORDER BY gl.city, gl.name, e.body_part, e.name'
    )->fetchAll(PDO::FETCH_ASSOC);

    $byGym = [];
    foreach ($allEquip as $eq) {
        $key = $eq['gym_id'];
        if (!isset($byGym[$key])) {
            $byGym[$key] = ['gym_name' => $eq['gym_name'], 'gym_city' => $eq['gym_city'], 'items' => []];
        }
        $byGym[$key]['items'][] = $eq;
    }

    drawDashHeader($session, $db, 'equipment', ['admin']);
    ?>

    <main class="admin-page">

        <div class="admin-header">
            <div>
                <h1><i class="fa fa-dumbbell"></i> Manage Equipment</h1>
                <p class="admin-sub"><?= count($allEquip) ?> item<?= count($allEquip) !== 1 ? 's' : '' ?> across <?= count($byGym) ?> gym<?= count($byGym) !== 1 ? 's' : '' ?></p>
            </div>
            <button class="btn-admin-primary" onclick="openCreateForm()">
                <i class="fa fa-plus"></i> Add Equipment
            </button>
        </div>

        <?php if ($msg): ?>
        <div class="admin-alert admin-alert--ok"><i class="fa fa-circle-check"></i> <?= $msg ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="admin-alert admin-alert--err"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
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
        <div class="admin-table-wrap" style="margin-bottom:1.25rem">
            <div style="padding:.75rem 1.125rem;background:#0d0d0d;border-bottom:1px solid #1e1e1e;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-dim);">
                    <i class="fa fa-location-dot" style="color:var(--admin-accent)"></i>
                    <?= htmlspecialchars($gymData['gym_city'] . ' — ' . $gymData['gym_name']) ?>
                </span>
                <span style="font-size:.75rem;color:var(--text-dim)"><?= count($gymData['items']) ?> item<?= count($gymData['items']) !== 1 ? 's' : '' ?></span>
            </div>
            <table class="admin-table">
                <thead>
                    <tr><th>Name</th><th>Body Part</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($gymData['items'] as $eq): ?>
                    <tr>
                        <td><?= htmlspecialchars($eq['name']) ?></td>
                        <td class="admin-dim"><?= htmlspecialchars($eq['body_part']) ?></td>
                        <td>
                            <span class="admin-badge admin-badge--<?= $eq['is_available'] ? 'active' : 'inactive' ?>">
                                <?= $eq['is_available'] ? 'Available' : 'Unavailable' ?>
                            </span>
                        </td>
                        <td>
                            <div class="admin-row-actions">
                                <a href="/pages/equipment.php?edit=<?= $eq['id'] ?>" class="btn-admin-sm" title="Edit">
                                    <i class="fa fa-pen"></i>
                                </a>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="_action" value="toggle">
                                    <input type="hidden" name="target_id" value="<?= $eq['id'] ?>">
                                    <button type="submit" class="btn-admin-sm btn-admin-sm--ok" title="Toggle availability">
                                        <i class="fa fa-<?= $eq['is_available'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline"
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
            <table class="admin-table"><tbody>
                <tr><td class="admin-empty">No equipment registered yet.</td></tr>
            </tbody></table>
        </div>
        <?php endif; ?>

    </main>

    <script>
    function openCreateForm() {
        var f = document.getElementById('equipForm');
        f.hidden = false;
        document.getElementById('formTitle').textContent = 'Add Equipment';
        f.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    </script>

    <?php
    drawFooter();

} else {
    $stmt = $db->prepare('
        SELECT equipment.name, equipment.body_part, equipment.is_available, gym_locations.name AS gym_name
        FROM equipment
        JOIN gym_locations ON equipment.gym_id = gym_locations.id
        ORDER BY gym_locations.name, equipment.body_part, equipment.name
    ');
    $stmt->execute();
    $equipment = $stmt->fetchAll();

    drawDashHeader($session, $db, 'equipment', ['equipment']);
    drawEquipment($equipment);
    drawFooter();
}
