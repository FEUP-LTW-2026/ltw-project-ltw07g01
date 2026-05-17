<?php
    declare(strict_types=1);

    require_once('../utils/session.php');
    require_once('../templates/common.tpl.php');
    require_once('../templates/index.tpl.php');

    $session = new Session();
?>

<?php drawIndexPage($session); ?>

