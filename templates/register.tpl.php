<?php function drawRegisterPage(object $session, string $error) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | CUBO GYM</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/login-register.css">
    <link href="https://fonts.googleapis.com/css2?family=League+Gothic&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php drawHeader($session); ?>

<main class="login-page">

    <div class="login-image">
        <img src="../images/register.png" alt="CUBO GYM">
    </div>

    <section class="login-box">
        <h1>SIGN UP</h1>

        <?php if ($error !== ''): ?>
            <p class="login-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="/pages/register.php">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>

            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" required>

            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>

            <button type="submit">SIGN UP</button>
        </form>

        <p class="login-register">
            Already have an account?
            <a href="/actions/login.php">Log in</a>
        </p>
    </section>

</main>

<?php drawFooter(); ?>

</body>
</html>
<?php } ?>