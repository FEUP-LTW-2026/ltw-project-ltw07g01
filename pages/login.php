<?php
declare(strict_types=1);

require_once(__DIR__ . '/../utils/session.php');
require_once(__DIR__ . '/../database/connection.db.php');
require_once(__DIR__ . '/../templates/common.tpl.php');

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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | CUBO GYM</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Gothic&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php drawHeader($session); ?>

<main class="login-page">

    <div class="login-image">
        <img src="../images/login.png" alt="CUBO GYM">
    </div>

    <section class="login-box">
        <h1>LOG IN</h1>

        <?php if ($error !== ''): ?>
            <p class="login-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">LOG IN</button>

        </form>

        <p class="login-register">
            Don't have an account?
            <a href="register.php">Sign up</a>
        </p>
    </section>

</main>

<?php drawFooter(); ?>

</body>
</html>