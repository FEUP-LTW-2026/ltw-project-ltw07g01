<?php function drawRegisterPage(Session $session, string $error): void { ?>
<main class="login-page">
    <div class="login-image">
        <img src="../../images/register.png" alt="CUBO GYM">
    </div>

    <section>
        <h1>SIGN UP</h1>

        <?php if ($error !== ''): ?>
            <p class="login-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="/actions/register.php">
            <?= csrf_field() ?>
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="John67" required
                   pattern="[\w.]{3,30}" title="3 to 30 characters: letters, numbers, underscores or dots">

            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" placeholder="John" required maxlength="50">

            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" placeholder="Pork" required maxlength="20">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="john67pork@example.com" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="6">

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">

            <button type="submit">SIGN UP</button>
        </form>

        <p class="login-register">
            Already have an account?
            <a href="/actions/login.php">Log in</a>
        </p>
    </section>
</main>
<?php } ?>
