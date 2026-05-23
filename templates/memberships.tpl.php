<?php function drawMemberships(object $session) { ?>

<div class="banner">
    <h2>MEMBERSHIPS</h2>
    <p>Choose the right plan to achieve your goals</p>
</div>

<div class="types-membership-container">
    <h3 class="types-memberships">
        <a href="#section-gym">GYM</a>
        <a href="#section-pilates">PILATES</a>
        <a href="#section-cycling">CYCLING</a>
    </h3>
</div>

<section class="type-section" id="section-gym">
    <img src="../images/bigGuy.jpg" alt="Gym section">
    <h3>Gym</h3>
    <div>
        <article class="plan">
            <h2>BASIC</h2>
            <p class="price">29 €</p>
            <p class="plan-info">The solid foundation for your transformation. Real results.</p>
            <ul>
                <li>Weight room</li>
                <li>Free schedule access</li>
                <li>Training app included</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=gym-basic' : 'login.php' ?>" class="plan-btn">GET STARTED</a>
        </article>
        <article class="plan plan-popular">
            <span class="popular-badge">MOST POPULAR</span>
            <h2>PRO</h2>
            <p class="price">39 €</p>
            <p class="plan-info">Perfect balance between intense training and recovery.</p>
            <ul>
                <li>All locations</li>
                <li>Premium classes</li>
                <li>Nutritional consulting</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=gym-pro' : 'login.php' ?>" class="plan-btn">GET STARTED</a>
        </article>
        <article class="plan">
            <h2>ULTRA</h2>
            <p class="price">49 €</p>
            <p class="plan-info">Elite performance and full access. The sky is your limit.</p>
            <ul>
                <li>24/7 access</li>
                <li>Unlimited group classes</li>
                <li>Monthly physical assessment</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=gym-ultra' : 'login.php' ?>" class="plan-btn">GET STARTED</a>
        </article>
    </div>
</section>

<section class="type-section" id="section-pilates">
    <img src="../images/pilatu.jpg" alt="Pilates section">
    <h3>Pilates</h3>
    <div>
        <article class="pack">
            <h2>1 CLASS</h2>
            <p class="price">12 €</p>
            <p>Drop in anytime. No commitment.</p>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=pilates-1' : 'login.php' ?>" class="plan-btn">BUY</a>
        </article>
        <article class="pack">
            <h2>5 CLASSES</h2>
            <p class="price">50 €</p>
            <p>Best for getting started with a routine.</p>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=pilates-5' : 'login.php' ?>" class="plan-btn">BUY</a>
        </article>
        <article class="pack">
            <h2>10 CLASSES</h2>
            <p class="price">90 €</p>
            <p>Commit to your practice and save.</p>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=pilates-10' : 'login.php' ?>" class="plan-btn">BUY</a>
        </article>
    </div>
</section>

<section class="type-section" id="section-cycling">
    <img src="../images/cycling.jpg" alt="Cycling section">
    <h3>Cycling</h3>
    <div>
        <article class="pack">
            <h2>1 CLASS</h2>
            <p class="price">10 €</p>
            <p>Jump on anytime. No strings attached.</p>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=cycling-1' : 'login.php' ?>" class="plan-btn">BUY</a>
        </article>
        <article class="pack">
            <h2>5 CLASSES</h2>
            <p class="price">40 €</p>
            <p>Build the habit with a flexible pack.</p>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=cycling-5' : 'login.php' ?>" class="plan-btn">BUY</a>
        </article>
        <article class="pack">
            <h2>10 CLASSES</h2>
            <p class="price">75 €</p>
            <p>Ride more, pay less. Your best value.</p>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=cycling-10' : 'login.php' ?>" class="plan-btn">BUY</a>
        </article>
    </div>
</section>

<span class="slogan">Set your goals. Surpass your limits. Join CUBO.</span>

<section class="comparison">
    <h2>Plan Comparison</h2>
    <div class="table-wrapper">
        <table>
            <tr>
                <th>Benefit</th>
                <th>Basic</th>
                <th>Pro</th>
                <th>Ultra</th>
            </tr>
            <tr>
                <td>24/7 Access</td>
                <td><i class="fa-solid fa-xmark icon-xmark"></i></td>
                <td><i class="fa-solid fa-xmark icon-xmark"></i></td>
                <td><i class="fa-solid fa-check icon-check"></i></td>
            </tr>
            <tr>
                <td>Premium Classes</td>
                <td><i class="fa-solid fa-xmark icon-xmark"></i></td>
                <td><i class="fa-solid fa-check icon-check"></i></td>
                <td><i class="fa-solid fa-check icon-check"></i></td>
            </tr>
            <tr>
                <td>Consulting</td>
                <td><i class="fa-solid fa-xmark icon-xmark"></i></td>
                <td><i class="fa-solid fa-check icon-check"></i></td>
                <td><i class="fa-solid fa-check icon-check"></i></td>
            </tr>
        </table>
    </div>
</section>

<section class="wrapper-perguntas">
    <h2>Frequently Asked Questions</h2>

    <details class="faq-item">
        <summary>Can I cancel at any time?</summary>
        <div>
            <p>Yes, you can cancel your subscription with 30 days' notice.</p>
        </div>
    </details>

    <details class="faq-item">
        <summary>Are there enrollment fees?</summary>
        <div>
            <p>Just a one-time initial fee of €10 at the time of enrollment.</p>
        </div>
    </details>

    <details class="faq-item">
        <summary>Can I change my plan?</summary>
        <div>
            <p>Yes, you can upgrade or downgrade your plan at any time in the customer area.</p>
        </div>
    </details>

    <details class="faq-item">
        <summary>Do class packs expire?</summary>
        <div>
            <p>Class packs are valid for 6 months from the date of purchase.</p>
        </div>
    </details>
</section>

<?php } ?>
