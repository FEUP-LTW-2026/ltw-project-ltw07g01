<?php function drawLoginPage(Session $session, string $error): void { ?>
<main class="login-page">
    <div class="login-image">
        <img src="../images/login.png" alt="CUBO GYM">
    </div>

    <section>
        <h1>LOG IN</h1>

        <?php if ($error !== ''): ?>
            <p class="login-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="/actions/login.php">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">LOG IN</button>
        </form>

        <p class="login-register">
            Don't have an account?
            <a href="/actions/register.php">Sign up</a>
        </p>
    </section>
</main>
<?php } ?>
