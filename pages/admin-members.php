<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();
require_once('../database/connection.db.php');
require_once('../templates/common.tpl.php');

if (!$session->isLoggedIn()) {
    header('Location: /actions/login.php');
    exit;
}

$db = getDatabaseConnection();
$adminId = (int)$session->getId();

$s = $db->prepare('SELECT 1 FROM admins WHERE user_id = :id');
$s->execute([':id' => $adminId]);
if (!$s->fetch()) {
    header('Location: /pages/dashboard.php');
    exit;
}

$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');
        $username  = trim($_POST['username']   ?? '');
        $password  = $_POST['password']        ?? '';

        if (!$firstName || !$lastName || !$email || !$username || strlen($password) < 6) {
            $error = 'All fields are required. Password must be at least 6 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (!preg_match('/^[\w.]{3,30}$/', $username)) {
            $error = 'Username must be 3-30 characters (letters, numbers, underscores, dots).';
        } else {
            $s = $db->prepare('SELECT 1 FROM users WHERE email = ? OR username = ?');
            $s->execute([$email, $username]);
            if ($s->fetch()) {
                $error = 'Email or username is already taken.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare('INSERT INTO users (username, email, password_hash, first_name, last_name) VALUES (?,?,?,?,?)')
                   ->execute([$username, $email, $hash, $firstName, $lastName]);
                $newId = (int)$db->lastInsertId();
                $db->prepare('INSERT INTO clients (user_id) VALUES (?)')->execute([$newId]);
                header('Location: /pages/admin-members.php?msg=Member+created+successfully.');
                exit;
            }
        }

    } elseif ($action === 'update') {
        $targetId  = (int)($_POST['target_id']  ?? 0);
        $firstName = trim($_POST['first_name']  ?? '');
        $lastName  = trim($_POST['last_name']   ?? '');
        $email     = trim($_POST['email']       ?? '');
        $plan      = $_POST['gym_plan']         ?? '';
        $start     = trim($_POST['gym_start']   ?? '');
        $end       = trim($_POST['gym_end']     ?? '') ?: null;

        if (!$targetId || !$firstName || !$lastName || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid data.';
        } else {
            $s = $db->prepare('SELECT 1 FROM users WHERE email = ? AND id != ?');
            $s->execute([$email, $targetId]);
            if ($s->fetch()) {
                $error = 'That email is already in use.';
            } else {
                $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?')
                   ->execute([$firstName, $lastName, $email, $targetId]);

                // atualiza membership
                if ($plan === 'none' || $plan === '') {
                    $db->prepare('DELETE FROM memberships WHERE client_id = ?')->execute([$targetId]);
                } else {
                    $start = $start ?: date('Y-m-d');
                    $db->prepare(
                        'INSERT INTO memberships (client_id, gym_plan, gym_start, gym_end)
                         VALUES (?,?,?,?)
                         ON CONFLICT(client_id) DO UPDATE SET gym_plan=excluded.gym_plan, gym_start=excluded.gym_start, gym_end=excluded.gym_end'
                    )->execute([$targetId, $plan, $start, $end]);
                }

                header('Location: /pages/admin-members.php?msg=Member+updated.');
                exit;
            }
        }

    } elseif ($action === 'promote') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            // garante que ainda e client e nao trainer
            $s = $db->prepare('SELECT 1 FROM clients WHERE user_id = ?');
            $s->execute([$targetId]);
            if ($s->fetch()) {
                $db->prepare('DELETE FROM memberships WHERE client_id = ?')->execute([$targetId]);
                $db->prepare('DELETE FROM client_classes WHERE client_id = ?')->execute([$targetId]);
                $db->prepare('DELETE FROM clients WHERE user_id = ?')->execute([$targetId]);
                $db->prepare('INSERT OR IGNORE INTO trainers (user_id) VALUES (?)')->execute([$targetId]);
                header('Location: /pages/trainers.php?msg=Member+promoted+to+trainer.');
                exit;
            }
        }

    } elseif ($action === 'delete') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId) {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
            header('Location: /pages/admin-members.php?msg=Member+removed.');
            exit;
        }
    }
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

$members = $db->query(
    'SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.created_at,
            m.gym_plan, m.gym_start, m.gym_end
     FROM users u
     JOIN clients c ON c.user_id = u.id
     LEFT JOIN memberships m ON m.client_id = u.id
     ORDER BY u.created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);

drawDashHeader($session, $db, 'admin-members', []);
?>

<main class="admin-page">

    <div class="admin-header">
        <div>
            <h1><i class="fa fa-users"></i> Manage Members</h1>
            <p class="admin-sub"><?= count($members) ?> member<?= count($members) !== 1 ? 's' : '' ?> registered</p>
        </div>
        <button class="btn-admin-primary" onclick="toggleForm()">
            <i class="fa fa-plus"></i> New Member
        </button>
    </div>

    <?php if ($msg): ?>
    <div class="admin-alert admin-alert--ok"><i class="fa fa-circle-check"></i> <?= $msg ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="admin-alert admin-alert--err"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="admin-form-card" id="memberForm" hidden>
        <h2 id="formTitle">New Member</h2>
        <form method="POST" class="admin-form-grid">
            <input type="hidden" name="_action" id="formAction" value="create">
            <input type="hidden" name="target_id" id="formTargetId" value="">

            <div class="admin-field">
                <label>First Name</label>
                <input type="text" name="first_name" id="f_first" required>
            </div>
            <div class="admin-field">
                <label>Last Name</label>
                <input type="text" name="last_name" id="f_last" required>
            </div>
            <div class="admin-field">
                <label>Email</label>
                <input type="email" name="email" id="f_email" required>
            </div>
            <div class="admin-field" id="usernameField">
                <label>Username</label>
                <input type="text" name="username" id="f_username">
            </div>
            <div class="admin-field" id="passwordField">
                <label>Password</label>
                <input type="password" name="password" id="f_password" minlength="6" placeholder="Min 6 characters">
            </div>

            <!-- membership - visivel so na edicao -->
            <div class="admin-field admin-field--full" id="membershipSection" style="display:none">
                <label style="margin-bottom:.5rem;display:block">Membership</label>
                <div class="admin-form-grid" style="padding:0">
                    <div class="admin-field">
                        <label>Plan</label>
                        <select name="gym_plan" id="f_plan">
                            <option value="none">— No membership —</option>
                            <option value="basic">Basic</option>
                            <option value="pro">Pro</option>
                            <option value="ultra">Ultra</option>
                        </select>
                    </div>
                    <div class="admin-field">
                        <label>Start Date</label>
                        <input type="date" name="gym_start" id="f_start">
                    </div>
                    <div class="admin-field">
                        <label>End Date <span style="font-weight:400;text-transform:none">(optional)</span></label>
                        <input type="date" name="gym_end" id="f_end">
                    </div>
                </div>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="btn-admin-primary" id="formSubmit">
                    <i class="fa fa-plus"></i> Create
                </button>
                <button type="button" class="btn-admin-ghost" onclick="closeForm()">Cancel</button>
            </div>
        </form>

        <!-- promover a trainer - so aparece na edicao -->
        <div id="promoteSection" style="display:none;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid #1e1e1e">
            <p style="font-size:.82rem;color:var(--text-dim);margin:0 0 .75rem">
                <i class="fa fa-triangle-exclamation" style="color:#f59e0b"></i>
                Promoting removes the member's plan and class history and grants trainer access.
            </p>
            <form method="POST" id="promoteForm"
                  onsubmit="return confirm('Promote this member to trainer? This cannot be undone.')">
                <input type="hidden" name="_action" value="promote">
                <input type="hidden" name="target_id" id="promoteTargetId" value="">
                <button type="submit" class="btn-admin-ghost" style="color:#f59e0b;border-color:#f59e0b22">
                    <i class="fa fa-arrow-up-right-dots"></i> Promote to Trainer
                </button>
            </form>
        </div>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Plan</th>
                    <th>Expires</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></td>
                    <td class="admin-dim">@<?= htmlspecialchars($m['username']) ?></td>
                    <td><?= htmlspecialchars($m['email']) ?></td>
                    <td>
                        <?php if ($m['gym_plan']): ?>
                        <span class="admin-badge admin-badge--<?= $m['gym_plan'] ?>"><?= strtoupper($m['gym_plan']) ?></span>
                        <?php else: ?>
                        <span class="admin-badge admin-badge--none">None</span>
                        <?php endif; ?>
                    </td>
                    <td class="admin-dim">
                        <?= $m['gym_end'] ? date('d M Y', strtotime($m['gym_end'])) : '—' ?>
                    </td>
                    <td class="admin-dim"><?= (new DateTime($m['created_at']))->format('d M Y') ?></td>
                    <td>
                        <div class="admin-row-actions">
                            <button class="btn-admin-sm" title="Edit"
                                onclick="editMember(<?= $m['id'] ?>, <?= htmlspecialchars(json_encode($m['first_name'])) ?>, <?= htmlspecialchars(json_encode($m['last_name'])) ?>, <?= htmlspecialchars(json_encode($m['email'])) ?>, <?= htmlspecialchars(json_encode($m['gym_plan'] ?? '')) ?>, <?= htmlspecialchars(json_encode($m['gym_start'] ? date('Y-m-d', strtotime($m['gym_start'])) : '')) ?>, <?= htmlspecialchars(json_encode($m['gym_end'] ? date('Y-m-d', strtotime($m['gym_end'])) : '')) ?>)">
                                <i class="fa fa-pen"></i>
                            </button>
                            <a href="/pages/profile.php?id=<?= $m['id'] ?>" class="btn-admin-sm" title="View profile">
                                <i class="fa fa-eye"></i>
                            </a>
                            <form method="POST" style="display:inline"
                                  onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($m['first_name'])) ?>? This cannot be undone.')">
                                <input type="hidden" name="_action" value="delete">
                                <input type="hidden" name="target_id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn-admin-sm btn-admin-sm--danger" title="Remove">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($members)): ?>
                <tr><td colspan="7" class="admin-empty">No members yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<script>
function toggleForm() {
    var f = document.getElementById('memberForm');
    f.hidden = !f.hidden;
    if (!f.hidden) {
        document.getElementById('formTitle').textContent  = 'New Member';
        document.getElementById('formAction').value       = 'create';
        document.getElementById('formTargetId').value     = '';
        document.getElementById('formSubmit').innerHTML   = '<i class="fa fa-plus"></i> Create';
        document.getElementById('usernameField').style.display  = '';
        document.getElementById('passwordField').style.display  = '';
        document.getElementById('membershipSection').style.display = 'none';
        document.getElementById('promoteSection').style.display   = 'none';
        ['f_first','f_last','f_email','f_username','f_password','f_start','f_end'].forEach(function(id){
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('f_plan').value = 'none';
    }
}

function closeForm() {
    document.getElementById('memberForm').hidden = true;
}

function editMember(id, first, last, email, plan, gymStart, gymEnd) {
    var f = document.getElementById('memberForm');
    f.hidden = false;
    document.getElementById('formTitle').textContent  = 'Edit Member';
    document.getElementById('formAction').value       = 'update';
    document.getElementById('formTargetId').value     = id;
    document.getElementById('formSubmit').innerHTML   = '<i class="fa fa-save"></i> Save';
    document.getElementById('usernameField').style.display  = 'none';
    document.getElementById('passwordField').style.display  = 'none';
    document.getElementById('membershipSection').style.display = '';
    document.getElementById('promoteSection').style.display   = '';
    document.getElementById('promoteTargetId').value          = id;
    document.getElementById('f_first').value  = first;
    document.getElementById('f_last').value   = last;
    document.getElementById('f_email').value  = email;
    document.getElementById('f_plan').value   = plan || 'none';
    document.getElementById('f_start').value  = gymStart || '';
    document.getElementById('f_end').value    = gymEnd   || '';
    f.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

<?php if ($error): ?>
document.getElementById('memberForm').hidden = false;
<?php endif; ?>
</script>

<?php drawFooter(); ?>
