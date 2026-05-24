<?php
declare(strict_types=1);
require_once('../utils/session.php');
$session = new Session();
require_once('../database/connection.db.php');
require_once('../templates/common.tpl.php');
require_once('../templates/memberships.tpl.php');
$db = getDatabaseConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership | Cubo Gym</title>
    <link rel="stylesheet" href="../css/membership.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Gothic&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php if ($session->isLoggedIn()): ?>
    <?php drawDashNavbar($session, $db, 'membership'); ?>
<?php else: ?>
    <?php drawHeader($session); ?>
<?php endif; ?>

<?php drawMemberships($session); ?>

<?php drawFooter(); ?>

</body>
</html>
