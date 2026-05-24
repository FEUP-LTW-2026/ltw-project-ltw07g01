<?php
declare(strict_types=1);

require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');
require_once(__DIR__ . '/../templates/login.tpl.php');

$session = new Session();

if ($session->isLoggedIn()) {
    header('Location: /pages/profile.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $db = getDatabaseConnection();

    $stmt = $db->prepare('
        SELECT id, first_name, last_name, password_hash
        FROM users
        WHERE email = ?
    ');

    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $session->setId((int)$user['id']);
        $session->setName($user['first_name']);

        header('Location: /pages/index.php');
        exit();
    } else {
        $error = 'Invalid email or password.';
    }
}
?>

<?php drawLoginPage($session, $error); ?>

