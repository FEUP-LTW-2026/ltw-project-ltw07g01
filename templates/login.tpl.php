<?php function drawLoginPage(object $session, string $error) { ?>
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
<?php } ?>
