<?php
    require_once(__DIR__ . '/utils/session.php');
    $session = new Session();

    header('Location:/pages/index.php');
    exit();
?>
