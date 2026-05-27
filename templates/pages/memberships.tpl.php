<?php function drawMemberships(object $session, ?int $classesRemaining = null)
{
    $loggedIn = $session->isLoggedIn();
    ?>

    <div class="banner">
        <h1>MEMBERSHIPS</h1>
        <p>Choose the right plan to achieve your goals</p>
    </div>

    <nav class="types-membership-container">
        <div class="types-memberships">
            <a href="#section-gym">GYM</a>
            <a href="#section-pilates">PILATES</a>
            <a href="#section-cycling">CYCLING</a>
            <a href="#section-pt">PERSONAL TRAINING</a>
        </div>
    </nav>

    <?php if ($classesRemaining !== null): ?>
    <div class="credits-balance-banner">
        <i class="fa fa-ticket"></i>
        <?php if ($classesRemaining > 0): ?>
            You have <strong><?= $classesRemaining ?> class
                credit<?= $classesRemaining !== 1 ? 's' : '' ?></strong> remaining — use them in any group class.
        <?php else: ?>
            You have <strong>no class credits</strong>. Purchase a pack below to book group classes.
        <?php endif; ?>
    </div>
<?php endif; ?>

    <section class="type-section" id="section-gym">
        <img src="../../images/bigGuy.jpg" alt="Gym section">
        <h2>Gym</h2>
        <div>
            <article class="plan">
                <h3>BASIC</h3>
                <p class="price">29 €</p>
                <p class="plan-info">The solid foundation for your transformation. Real results.</p>
                <ul>
                    <li>Weight room</li>
                    <li>Free schedule access</li>
                    <li>Training app included</li>
                </ul>
                <a href="<?= $loggedIn ? '/pages/confirm-purchase.php?plan=gym-1' : '/actions/login.php' ?>"
                   class="plan-btn">
                    <?= $loggedIn ? 'Get Started' : 'Login to Subscribe' ?>
                </a>
            </article>
            <article class="plan plan-popular">
                <span class="popular-badge">MOST POPULAR</span>
                <h3>PRO</h3>
                <p class="price">39 €</p>
                <p class="plan-info">Perfect balance between intense training and recovery.</p>
                <ul>
                    <li>All locations</li>
                    <li>Premium classes</li>
                    <li>Nutritional consulting</li>
                </ul>
                <a href="<?= $loggedIn ? '/pages/confirm-purchase.php?plan=gym-2' : '/actions/login.php' ?>"
                   class="plan-btn">
                    <?= $loggedIn ? 'Get Started' : 'Login to Subscribe' ?>
                </a>
            </article>
            <article class="plan">
                <h3>ULTRA</h3>
                <p class="price">49 €</p>
                <p class="plan-info">Elite performance and full access. The sky is your limit.</p>
                <ul>
                    <li>24/7 access</li>
                    <li>Unlimited group classes</li>
                    <li>Monthly physical assessment</li>
                </ul>
                <a href="<?= $loggedIn ? '/pages/confirm-purchase.php?plan=gym-3' : '/actions/login.php' ?>"
                   class="plan-btn">
                    <?= $loggedIn ? 'Get Started' : 'Login to Subscribe' ?>
                </a>
            </article>
        </div>
    </section>

    <section class="type-section" id="section-pilates">
        <img src="../../images/pilatu.jpg" alt="Pilates section">
        <h2>Pilates</h2>
        <p class="credits-universal-note"><i class="fa fa-circle-info"></i> Credits from any class pack work across
            <strong>all group classes</strong> — Pilates, Cycling, and Personal Training.</p>
        <div>
            <article class="plan">
                <h3>1 CLASS</h3>
                <p class="price">10 €</p>
                <p class="plan-info">Drop in anytime. No commitment required.</p>
                <ul>
                    <li>1 session with certified instructor</li>
                    <li>Mat included</li>
                    <li>Valid immediately</li>
                </ul>
                <a href="<?= $loggedIn ? '/pages/confirm-purchase.php?plan=classes-1' : '/actions/login.php' ?>"
                   class="plan-btn">
                    <?= $loggedIn ? 'Buy 1 Class' : 'Login to Buy' ?>
                </a>
            </article>
            <article class="plan plan-popular">
                <span class="popular-badge">BEST VALUE</span>
                <h3>5 CLASSES</h3>
                <p class="price">40 €</p>
                <p class="plan-info">Best for getting started with a routine.</p>
                <ul>
                    <li>5 sessions to use flexibly</li>
                    <li>All locations</li>
                    <li>Valid for 3 months</li>
                </ul>
                <a href="<?= $loggedIn ? '/pages/confirm-purchase.php?plan=classes-5' : '/actions/login.php' ?>"
                   class="plan-btn">
                    <?= $loggedIn ? 'Buy 5 Classes' : 'Login to Buy' ?>
                </a>
            </article>
            <article class="plan">
                <h3>10 CLASSES</h3>
                <p class="price">75 €</p>
                <p class="plan-info">Commit to your practice and save.</p>
                <ul>
                    <li>10 sessions at your pace</li>
                    <li>Priority booking</li>
                    <li>Valid for 6 months</li>
                </ul>
                <a href="<?= $loggedIn ? '/pages/confirm-purchase.php?plan=classes-10' : '/actions/login.php' ?>"
                   class="plan-btn">
                    <?= $loggedIn ? 'Buy 10 Classes' : 'Login to Buy' ?>
                </a>
            </article>
        </div>
    </section>

    <section class="type-section" id="section-cycling">
        <img src="../../images/cycling.jpg" alt="Cycling section">
        <h2>Cycling</h2>
        <p class="credits-universal-note"><i class="fa fa-circle-info"></i> Credits from any class pack work across
            <strong>all group classes</strong> — Pilates, Cycling, and Personal Training.</p>
        <div>
            <article class="plan">
                <h3>1 CLASS</h3>
                <p class="price">10 €</p>
                <p class="plan-info">Jump on anytime. No strings attached.</p>
                <ul>
                    <li>1 high-energy session</li>
                    <li>Bike reserved for you</li>
                    <li>Valid immediately</li>
                </ul>
                <a href="<?= $loggedIn ? '/pages/confirm-purchase.php?plan=classes-1' : '/actions/login.php' ?>"
                   class="plan-btn">
                    <?= $loggedIn ? 'Buy 1 Class' : 'Login to Buy' ?>
                </a>
            </article>
            <article class="plan plan-popular">
                <span class="popular-badge">BEST VALUE</span>
                <h3>5 CLASSES</h3>
                <p class="price">40 €</p>
                <p class="plan-info">Build the habit with a flexible pack.</p>
                <ul>
                    <li>5 sessions to use flexibly</li>
                    <li>All locations</li>
                    <li>Valid for 3 months</li>
                </ul>
                <a href="<?= $loggedIn ? '/pages/confirm-purchase.php?plan=classes-5' : '/actions/login.php' ?>"
                   class="plan-btn">
                    <?= $loggedIn ? 'Buy 5 Classes' : 'Login to Buy' ?>
                </a>
            </article>
            <article class="plan">
                <h3>10 CLASSES</h3>
                <p class="price">75 €</p>
                <p class="plan-info">Ride more, pay less. Your best value.</p>
                <ul>
                    <li>10 sessions at your pace</li>
                    <li>Performance metrics included</li>
                    <li>Valid for 6 months</li>
                </ul>
                <a href="<?= $loggedIn ? '/pages/confirm-purchase.php?plan=classes-10' : '/actions/login.php' ?>"
                   class="plan-btn">
                    <?= $loggedIn ? 'Buy 10 Classes' : 'Login to Buy' ?>
                </a>
            </article>
        </div>
    </section>


    <section class="type-section" id="section-pt">
        <img src="../../images/personal-training.png" alt="Personal Training section">
        <h2>Personal Training</h2>
        <p class="credits-universal-note"><i class="fa fa-circle-info"></i> Book Personal Training sessions using your
            class credits. <a href="/pages/schedule.php">View schedule →</a></p>
        <div>
            <article class="plan">
                <h3>1 CLASS</h3>
                <p class="price">10 €</p>
                <p class="plan-info">Private session with dedicated coach attention.</p>
                <ul>
                    <li>1-on-1 focus</li>
                    <li>Goal-based programming</li>
                    <li>All locations</li>
                </ul>
                <a href="<?= $loggedIn ? '/pages/confirm-purchase.php?plan=classes-1' : '/actions/login.php' ?>"
                   class="plan-btn">
                    <?= $loggedIn ? 'Buy 1 Class' : 'Login to Buy' ?>
                </a>
            </article>
            <article class="plan plan-popular">
                <span class="popular-badge">MOST POPULAR</span>
                <h3>5 CLASSES</h3>
                <p class="price">40 €</p>
                <p class="plan-info">Build consistency with a personalised programme.</p>
                <ul>
                    <li>Custom plan</li>
                    <li>Weekly adjustments</li>
                    <li>Nutrition guidance</li>
                </ul>
                <a href="<?= $loggedIn ? '/pages/confirm-purchase.php?plan=classes-5' : '/actions/login.php' ?>"
                   class="plan-btn">
                    <?= $loggedIn ? 'Buy 5 Classes' : 'Login to Buy' ?>
                </a>
            </article>
            <article class="plan">
                <h3>10 CLASSES</h3>
                <p class="price">75 €</p>
                <p class="plan-info">Full commitment — track progress and see real results.</p>
                <ul>
                    <li>Monthly assessments</li>
                    <li>Performance metrics</li>
                    <li>Priority booking</li>
                </ul>
                <a href="<?= $loggedIn ? '/pages/confirm-purchase.php?plan=classes-10' : '/actions/login.php' ?>"
                   class="plan-btn">
                    <?= $loggedIn ? 'Buy 10 Classes' : 'Login to Buy' ?>
                </a>
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
