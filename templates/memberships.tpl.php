<?php function drawMemberships(object $session) { ?>

<div class="banner">
    <h2 class="membership-title">MEMBERSHIPS</h2>
    <p class="banner-subtitle">Choose the right plan to achieve your goals</p>
</div>

<div class="types-membership-container">
    <h3 class="types-memberships">
        <a href="#section-gym" class="gym-title">GYM</a>
        <a href="#section-pilates" class="pilatus-title">PILATES</a>
        <a href="#section-cycling" class="cycling-title">CYCLING</a>
    </h3>
</div>

<img class="examples-img" id="section-gym" src="../images/bigGuy.jpg" alt="Gym section">
<h3 class="Gym-title">Gym</h3>
<div class="main-wrapper">
    <div class="memberships">
        <div class="plan">
            <h2>BASIC</h2>
            <p class="price">29 €</p>
            <p class="plan-info">The solid foundation for your transformation. Real results.</p>
            <ul>
                <li>Weight room</li>
                <li>Free schedule access</li>
                <li>Training app included</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=gym-basic' : 'login.php' ?>" class="plan-btn">GET STARTED</a>
        </div>
        <div class="plan plan-popular">
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
        </div>
        <div class="plan">
            <h2>ULTRA</h2>
            <p class="price">49 €</p>
            <p class="plan-info">Elite performance and full access. The sky is your limit.</p>
            <ul>
                <li>24/7 access</li>
                <li>Unlimited group classes</li>
                <li>Monthly physical assessment</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=gym-ultra' : 'login.php' ?>" class="plan-btn">GET STARTED</a>
        </div>
    </div>
</div>

<img class="examples-img" id="section-pilates" src="../images/pilatu.jpg" alt="Pilates section">
<h3 class="Pilatus-title">Pilates</h3>
<div class="main-wrapper">
    <div class="memberships">
        <div class="plan">
            <h2>BASIC</h2>
            <p class="price">25 €</p>
            <p class="plan-info">Introduction to Pilates with certified instructors.</p>
            <ul>
                <li>2 classes per week</li>
                <li>Mat included</li>
                <li>Certified instructors</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=pilates-basic' : 'login.php' ?>" class="plan-btn">GET STARTED</a>
        </div>
        <div class="plan plan-popular">
            <span class="popular-badge">MOST POPULAR</span>
            <h2>PRO</h2>
            <p class="price">35 €</p>
            <p class="plan-info">Evolve with reformer classes and access to all locations.</p>
            <ul>
                <li>4 classes per week</li>
                <li>All locations</li>
                <li>Reformer classes</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=pilates-pro' : 'login.php' ?>" class="plan-btn">GET STARTED</a>
        </div>
        <div class="plan">
            <h2>ULTRA</h2>
            <p class="price">45 €</p>
            <p class="plan-info">Unlimited classes and personalized coaching.</p>
            <ul>
                <li>Unlimited classes</li>
                <li>Monthly private sessions</li>
                <li>Postural assessment</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=pilates-ultra' : 'login.php' ?>" class="plan-btn">GET STARTED</a>
        </div>
    </div>
</div>

<img class="examples-img" id="section-cycling" src="../images/cycling.jpg" alt="Cycling section">
<h3 class="Cycling-title">Cycling</h3>
<div class="main-wrapper">
    <div class="memberships">
        <div class="plan">
            <h2>BASIC</h2>
            <p class="price">20 €</p>
            <p class="plan-info">Start pedaling with energy and consistency.</p>
            <ul>
                <li>3 classes per week</li>
                <li>Reserved bike</li>
                <li>Training app included</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=cycling-basic' : 'login.php' ?>" class="plan-btn">GET STARTED</a>
        </div>
        <div class="plan plan-popular">
            <span class="popular-badge">MOST POPULAR</span>
            <h2>PRO</h2>
            <p class="price">30 €</p>
            <p class="plan-info">Performance and metrics to take your training to the next level.</p>
            <ul>
                <li>Unlimited classes</li>
                <li>All locations</li>
                <li>Performance metrics</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=cycling-pro' : 'login.php' ?>" class="plan-btn">GET STARTED</a>
        </div>
        <div class="plan">
            <h2>ULTRA</h2>
            <p class="price">40 €</p>
            <p class="plan-info">Elite training with full analysis and tracking.</p>
            <ul>
                <li>Unlimited classes</li>
                <li>Personalized training</li>
                <li>Monthly pedaling analysis</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=cycling-ultra' : 'login.php' ?>" class="plan-btn">GET STARTED</a>
        </div>
    </div>
</div>

<span class="slogan">Set your goals. Surpass your limits. Join CUBO.</span>

<section class="comparison">
    <h2>Plan Comparison</h2>
    <div class="table-wrapper">
        <table class="table-dark">
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
    <h2 class="perguntas-title">Frequently Asked Questions</h2>

    <details class="faq-item">
        <summary class="faq-question">Can I cancel at any time?</summary>
        <div class="faq-answer">
            <p>Yes, you can cancel your subscription with 30 days' notice.</p>
        </div>
    </details>

    <details class="faq-item">
        <summary class="faq-question">Are there enrollment fees?</summary>
        <div class="faq-answer">
            <p>Just a one-time initial fee of €10 at the time of enrollment.</p>
        </div>
    </details>

    <details class="faq-item">
        <summary class="faq-question">Can I change my plan?</summary>
        <div class="faq-answer">
            <p>Yes, you can upgrade or downgrade your plan at any time in the customer area.</p>
        </div>
    </details>

    <details class="faq-item">
        <summary class="faq-question">Are classes included in all plans?</summary>
        <div class="faq-answer">
            <p>Basic group classes are included in the Basic plan. Premium and specialized classes are available in Pro and Ultra plans.</p>
        </div>
    </details>
</section>

<?php } ?>
