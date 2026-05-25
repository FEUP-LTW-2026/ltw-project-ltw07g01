<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();
require_once('../database/connection.db.php');
require_once('../templates/common.tpl.php');
require_once('../templates/memberships.tpl.php');
$db = getDatabaseConnection();

$classesRemaining = null;
if ($session->isLoggedIn()) {
    $s = $db->prepare('SELECT
    gym_plan,
    gym_start,
    gym_end,
    classes_remaining
FROM memberships
WHERE client_id = :id');
    $s->execute(['id' => $session->getId()]);
    $mem = $s->fetch();
    if ($mem) {
        $classesRemaining = (int)$mem['classes_remaining'];
    }    
    drawDashHeader($session, $db, 'membership', ['membership']);
    
} else {
    drawHeader($session, ['membership']);
}
?>

<?php if (isset($_GET['subscribed'])): ?>
    <p class="subscribe-success">Your plan has been activated successfully!</p>
<?php endif; ?>

<?php drawMemberships($session, $classesRemaining); ?>

<?php drawFooter(); ?>
