<?php
    require_once(__DIR__ . '/utils/session.php');

    $session = new Session();
    $destination = $session->isLoggedIn() ? '/pages/dashboard.php' : '/pages/index.php';

    header('Location: ' . $destination);
    exit();
?>
