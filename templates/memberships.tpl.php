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
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=gym-basic' : '/actions/login.php' ?>" class="plan-btn">GET STARTED</a>
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
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=gym-pro' : '/actions/login.php' ?>" class="plan-btn">GET STARTED</a>
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
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=gym-ultra' : '/actions/login.php' ?>" class="plan-btn">GET STARTED</a>
        </article>
    </div>
</section>

<section class="type-section" id="section-pilates">
    <img src="../images/pilatu.jpg" alt="Pilates section">
    <h3>Pilates</h3>
    <div>
        <article class="plan">
            <h2>1 CLASS</h2>
            <p class="price">12 €</p>
            <p class="plan-info">Drop in anytime. No commitment required.</p>
            <ul>
                <li>1 session with certified instructor</li>
                <li>Mat included</li>
                <li>Valid immediately</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=pilates-1' : '/actions/login.php' ?>" class="plan-btn">BUY</a>
        </article>
        <article class="plan plan-popular">
            <span class="popular-badge">BEST VALUE</span>
            <h2>5 CLASSES</h2>
            <p class="price">50 €</p>
            <p class="plan-info">Best for getting started with a routine.</p>
            <ul>
                <li>5 sessions to use flexibly</li>
                <li>All locations</li>
                <li>Valid for 3 months</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=pilates-5' : '/actions/login.php' ?>" class="plan-btn">BUY</a>
        </article>
        <article class="plan">
            <h2>10 CLASSES</h2>
            <p class="price">90 €</p>
            <p class="plan-info">Commit to your practice and save.</p>
            <ul>
                <li>10 sessions at your pace</li>
                <li>Priority booking</li>
                <li>Valid for 6 months</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=pilates-10' : '/actions/login.php' ?>" class="plan-btn">BUY</a>
        </article>
    </div>
</section>

<section class="type-section" id="section-cycling">
    <img src="../images/cycling.jpg" alt="Cycling section">
    <h3>Cycling</h3>
    <div>
        <article class="plan">
            <h2>1 CLASS</h2>
            <p class="price">10 €</p>
            <p class="plan-info">Jump on anytime. No strings attached.</p>
            <ul>
                <li>1 high-energy session</li>
                <li>Bike reserved for you</li>
                <li>Valid immediately</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=cycling-1' : '/actions/login.php' ?>" class="plan-btn">BUY</a>
        </article>
        <article class="plan plan-popular">
            <span class="popular-badge">BEST VALUE</span>
            <h2>5 CLASSES</h2>
            <p class="price">40 €</p>
            <p class="plan-info">Build the habit with a flexible pack.</p>
            <ul>
                <li>5 sessions to use flexibly</li>
                <li>All locations</li>
                <li>Valid for 3 months</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=cycling-5' : '/actions/login.php' ?>" class="plan-btn">BUY</a>
        </article>
        <article class="plan">
            <h2>10 CLASSES</h2>
            <p class="price">75 €</p>
            <p class="plan-info">Ride more, pay less. Your best value.</p>
            <ul>
                <li>10 sessions at your pace</li>
                <li>Performance metrics included</li>
                <li>Valid for 6 months</li>
            </ul>
            <a href="<?= $session->isLoggedIn() ? 'subscribe.php?plan=cycling-10' : '/actions/login.php' ?>" class="plan-btn">BUY</a>
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
