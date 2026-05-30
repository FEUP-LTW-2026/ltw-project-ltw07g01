<?php
declare(strict_types=1);

require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/layout/common.tpl.php');
require_once(__DIR__ . '/../templates/actions/login.tpl.php');

$session = new Session();

if ($session->isLoggedIn()) {
    header('Location: /pages/dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $login    = trim($_POST['email']);
        $password = $_POST['password'];

        $db = getDatabaseConnection();

        $stmt = $db->prepare('
            SELECT id, first_name, last_name, password_hash
            FROM users
            WHERE email = ? OR username = ?
        ');

        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $session->setId((int)$user['id']);
            $session->setName($user['first_name']);

            header('Location: /pages/dashboard.php');
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>

<?php
drawHeader($session, ['login-register']);
drawLoginPage($session, $error);
drawFooter();



