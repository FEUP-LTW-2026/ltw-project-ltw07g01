<?php function drawConfirmPurchase(array $plan, string $planKey): void { ?>

<main class="confirm-page">
    <div class="confirm-card">
        <h1 class="confirm-title">Confirm Purchase</h1>

        <div class="confirm-details">
            <p class="confirm-plan-name"><?= htmlspecialchars($plan['name']) ?></p>
            <p class="confirm-price">
                <?= htmlspecialchars($plan['price']) ?>
                <span class="confirm-period"><?= htmlspecialchars($plan['period']) ?></span>
            </p>
            <p class="confirm-desc"><?= htmlspecialchars($plan['desc']) ?></p>
            <?php if ($plan['credits']): ?>
            <p class="confirm-credits">
                <i class="fa fa-ticket"></i>
                +<?= $plan['credits'] ?> class credit<?= $plan['credits'] !== 1 ? 's' : '' ?> added to your account
            </p>
            <?php endif; ?>
        </div>

        <form method="POST" action="/actions/subscribe.php">
            <input type="hidden" name="plan" value="<?= htmlspecialchars($planKey) ?>">
            <button type="submit" class="plan-btn">Confirm Purchase</button>
        </form>

        <a href="/pages/membership.php" class="confirm-cancel">Cancel</a>
    </div>
</main>

<?php } ?>
