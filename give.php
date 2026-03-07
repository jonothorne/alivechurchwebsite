<?php
require __DIR__ . '/config.php';
$page_title = 'Give | ' . $site['name'];
include __DIR__ . '/includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('give');
}
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow" data-cms-editable="hero_eyebrow" data-cms-page="give" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Generosity'); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="give" data-cms-type="text"><?= $cms->text('hero_headline', 'Kingdom impact through your giving.'); ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="give" data-cms-type="text"><?= $cms->text('hero_subtext', 'Thank you for investing in transformed lives, community outreach, and missions around the world.'); ?></p>
    </div>
</section>

<!-- Online Giving Form Section -->
<section class="giving-form-section">
    <div class="container narrow">
        <div class="giving-card">
            <h2>Give Online</h2>
            <p class="form-intro">Secure, convenient giving in less than a minute. Your generosity makes a difference.</p>

            <form id="stripe-giving-form" class="stripe-form" action="/api/process-donation.php" method="post">

                <!-- Amount Selection -->
                <div class="form-group">
                    <label class="form-label">Select Amount</label>
                    <div class="amount-buttons">
                        <button type="button" class="amount-btn" data-amount="20">£20</button>
                        <button type="button" class="amount-btn" data-amount="50">£50</button>
                        <button type="button" class="amount-btn" data-amount="100">£100</button>
                        <button type="button" class="amount-btn active" data-amount="custom">Custom</button>
                    </div>
                </div>

                <!-- Custom Amount Input -->
                <div class="form-group" id="custom-amount-group">
                    <label for="amount" class="form-label">Amount (£)</label>
                    <div class="input-with-icon">
                        <span class="input-icon">£</span>
                        <input type="number" id="amount" name="amount" min="1" step="0.01"
                               placeholder="Enter amount" required class="form-input amount-input">
                    </div>
                </div>

                <!-- Frequency Selection -->
                <div class="form-group">
                    <label for="frequency" class="form-label">Frequency</label>
                    <select id="frequency" name="frequency" class="form-select" required>
                        <option value="one-time">One-time gift</option>
                        <option value="weekly">Weekly (every Sunday)</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>

                <!-- Email for Receipt -->
                <div class="form-group">
                    <label for="email" class="form-label">Email for Receipt</label>
                    <input type="email" id="email" name="email" placeholder="your@email.com"
                           required class="form-input">
                </div>

                <!-- Gift Aid (UK Taxpayers) -->
                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="gift-aid" name="gift_aid" value="yes">
                        <span class="checkbox-text">
                            <strong>Add Gift Aid</strong> (UK taxpayers only)<br>
                            <small>Boost your donation by 25% at no extra cost to you. By ticking this box, I confirm I am a UK taxpayer and understand that if I pay less Income Tax and/or Capital Gains Tax than the amount of Gift Aid claimed on all my donations in that tax year, it is my responsibility to pay any difference.</small>
                        </span>
                    </label>
                </div>

                <!-- Stripe Card Element Placeholder -->
                <div class="form-group">
                    <label class="form-label">Card Details</label>
                    <div id="card-element" class="stripe-card-element">
                        <!-- Stripe Elements will insert the card input here -->
                    </div>
                    <div id="card-errors" class="form-error" role="alert"></div>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="submit-button" class="btn btn-primary btn-block">
                    <span id="button-text">Complete Donation</span>
                    <span id="spinner" class="spinner hidden"></span>
                </button>

                <!-- Stripe Badge -->
                <div class="stripe-badge">
                    <svg width="60" height="25" viewBox="0 0 60 25" xmlns="http://www.w3.org/2000/svg">
                        <path fill="#635BFF" d="M59.64 14.28h-8.06c.19 1.93 1.6 2.55 3.2 2.55 1.64 0 2.96-.37 4.05-.95v3.32a8.33 8.33 0 0 1-4.56 1.1c-4.01 0-6.83-2.5-6.83-7.48 0-4.19 2.39-7.52 6.3-7.52 3.92 0 5.96 3.28 5.96 7.5 0 .4-.04 1.26-.06 1.48zm-5.92-5.62c-1.03 0-2.17.73-2.17 2.58h4.25c0-1.85-1.07-2.58-2.08-2.58zM40.95 20.3c-1.44 0-2.32-.6-2.9-1.04l-.02 4.63-4.12.87V5.57h3.76l.08 1.02a4.7 4.7 0 0 1 3.23-1.29c2.9 0 5.62 2.6 5.62 7.4 0 5.23-2.7 7.6-5.65 7.6zM40 8.95c-.95 0-1.54.34-1.97.81l.02 6.12c.4.44.98.78 1.95.78 1.52 0 2.54-1.65 2.54-3.87 0-2.15-1.04-3.84-2.54-3.84zM28.24 5.57h4.13v14.44h-4.13V5.57zm0-4.7L32.37 0v3.36l-4.13.88V.88zm-4.32 9.35v9.79H19.8V5.57h3.7l.12 1.22c1-1.77 3.07-1.41 3.62-1.22v3.79c-.52-.17-2.29-.43-3.32.86zm-8.55 4.72c0 2.43 2.6 1.68 3.12 1.46v3.36c-.55.3-1.54.54-2.89.54a4.15 4.15 0 0 1-4.27-4.24l.01-13.17 4.02-.86v3.54h3.14V9.1h-3.13v5.85zm-4.91.7c0 2.97-2.31 4.66-5.73 4.66a11.2 11.2 0 0 1-4.46-.93v-3.93c1.38.75 3.1 1.31 4.46 1.31.92 0 1.53-.24 1.53-1C6.26 13.77 0 14.51 0 9.95 0 7.04 2.28 5.3 5.62 5.3c1.36 0 2.72.2 4.09.75v3.88a9.23 9.23 0 0 0-4.1-1.06c-.86 0-1.44.25-1.44.93 0 1.85 6.29.97 6.29 5.88z"/>
                    </svg>
                    <span>Secured by Stripe</span>
                </div>

                <p class="security-note">🔒 Your information is secure and encrypted. We never store your card details.</p>
            </form>
        </div>
    </div>
</section>

<!-- Alternative Giving Methods -->
<section class="content-section">
    <div class="container">
        <div class="section-heading center">
            <h2 data-cms-editable="methods_headline" data-cms-page="give" data-cms-type="text"><?= $cms->text('methods_headline', 'More Ways to Give'); ?></h2>
            <p data-cms-editable="methods_subtext" data-cms-page="give" data-cms-type="text"><?= $cms->text('methods_subtext', 'Choose the method that works best for you.'); ?></p>
        </div>

        <div class="giving-methods-grid">
            <!-- Text to Give -->
            <article class="giving-method-card">
                <div class="method-icon">📱</div>
                <h3>Text to Give</h3>
                <p>The fastest way to give on the go.</p>
                <div class="method-details">
                    <p><strong>Text "ALIVE 20" to 70100</strong></p>
                    <p class="small-text">Replace 20 with your desired amount (£1-£20). Standard text rates apply.</p>
                </div>
            </article>

            <!-- Bank Transfer -->
            <article class="giving-method-card">
                <div class="method-icon">🏦</div>
                <h3>Bank Transfer</h3>
                <p>Set up a direct transfer or standing order.</p>
                <div class="method-details">
                    <p><strong>Account Name:</strong> Alive Church</p>
                    <p><strong>Sort Code:</strong> 20-00-00</p>
                    <p><strong>Account Number:</strong> 12345678</p>
                    <button class="btn btn-outline btn-sm copy-btn" data-copy="20-00-00 12345678">
                        Copy Details
                    </button>
                </div>
            </article>

            <!-- In Person -->
            <article class="giving-method-card">
                <div class="method-icon">⛪</div>
                <h3>In Person</h3>
                <p>Give during any Sunday gathering.</p>
                <div class="method-details">
                    <p>Giving stations are available in the lobby before and after each service. Cash and card accepted.</p>
                    <a href="/visit" class="text-link">Plan Your Visit →</a>
                </div>
            </article>

            <!-- Standing Order -->
            <article class="giving-method-card">
                <div class="method-icon">📄</div>
                <h3>Standing Order</h3>
                <p>Automate your regular giving through your bank.</p>
                <div class="method-details">
                    <p>Download our standing order form and submit it to your bank.</p>
                    <a href="/assets/downloads/standing-order-form.pdf" class="btn btn-outline btn-sm" download>
                        Download Form
                    </a>
                </div>
            </article>
        </div>
    </div>
</section>

<!-- Financial Transparency Section -->
<section class="content-section alt">
    <div class="container">
        <div class="section-heading center">
            <h2 data-cms-editable="transparency_headline" data-cms-page="give" data-cms-type="text"><?= $cms->text('transparency_headline', 'Where Your Giving Goes'); ?></h2>
            <p data-cms-editable="transparency_subtext" data-cms-page="give" data-cms-type="text"><?= $cms->text('transparency_subtext', 'We are committed to transparency and stewardship. Here\'s how we invest every pound.'); ?></p>
        </div>

        <div class="financial-breakdown">
            <div class="breakdown-item">
                <div class="breakdown-header">
                    <span class="breakdown-label">Local Outreach & Community</span>
                    <span class="breakdown-percentage">40%</span>
                </div>
                <div class="breakdown-bar">
                    <div class="breakdown-fill" style="width: 40%; background: var(--color-magenta);"></div>
                </div>
                <p class="breakdown-description">Foodbank, youth programs, community events, pastoral care</p>
            </div>

            <div class="breakdown-item">
                <div class="breakdown-header">
                    <span class="breakdown-label">Church Planting & Kingdom Expansion</span>
                    <span class="breakdown-percentage">30%</span>
                </div>
                <div class="breakdown-bar">
                    <div class="breakdown-fill" style="width: 30%; background: var(--color-purple);"></div>
                </div>
                <p class="breakdown-description">Planting new churches across the UK</p>
            </div>

            <div class="breakdown-item">
                <div class="breakdown-header">
                    <span class="breakdown-label">Operations & Facilities</span>
                    <span class="breakdown-percentage">20%</span>
                </div>
                <div class="breakdown-bar">
                    <div class="breakdown-fill" style="width: 20%; background: var(--color-blue);"></div>
                </div>
                <p class="breakdown-description">Building costs, staff, technology, ministry resources</p>
            </div>

            <div class="breakdown-item">
                <div class="breakdown-header">
                    <span class="breakdown-label">Global Missions</span>
                    <span class="breakdown-percentage">10%</span>
                </div>
                <div class="breakdown-bar">
                    <div class="breakdown-fill" style="width: 10%; background: var(--color-dark-blue);"></div>
                </div>
                <p class="breakdown-description">We tithe as a church—giving the first 10% to global partners</p>
            </div>
        </div>

        <div class="transparency-footer">
            <p><strong>Alive Church</strong> is a registered charity in England & Wales.</p>
            <p>Charity Number: 1234567</p>
            <a href="/assets/downloads/annual-report-2024.pdf" class="btn btn-outline" download>
                Download 2024 Annual Report
            </a>
        </div>
    </div>
</section>

<!-- Impact Stories -->
<section class="content-section">
    <div class="container">
        <div class="section-heading center">
            <h2 data-cms-editable="stories_headline" data-cms-page="give" data-cms-type="text"><?= $cms->text('stories_headline', 'Stories of Impact'); ?></h2>
            <p data-cms-editable="stories_subtext" data-cms-page="give" data-cms-type="text"><?= $cms->text('stories_subtext', 'Your generosity is changing lives in Norwich and around the world.'); ?></p>
        </div>

        <div class="impact-stories">
            <article class="impact-card">
                <img src="/assets/imgs/gallery/alive-church-community-cafe-outdoor.jpg"
                     alt="Alive Church foodbank and community outreach"
                     class="impact-image">
                <div class="impact-content">
                    <h3>Feeding Families</h3>
                    <p>"Because of Alive's Foodbank, my family had meals during the toughest season of our lives. We're back on our feet now, and I volunteer there every week to help others the way we were helped."</p>
                    <p class="impact-author">— Sarah, Norwich</p>
                </div>
            </article>

            <article class="impact-card">
                <img src="/assets/imgs/gallery/alive-church-family-worship-lincolnshire.jpg"
                     alt="Alive Church community impact"
                     class="impact-image">
                <div class="impact-content">
                    <h3>Youth Transformed</h3>
                    <p>"Alive Youth camp ignited my son's faith in a way I couldn't. Thank you for sponsoring him—it changed the trajectory of his life. He's now serving on the youth team!"</p>
                    <p class="impact-author">— Mark, Parent</p>
                </div>
            </article>

            <article class="impact-card">
                <img src="/assets/imgs/gallery/alive-church-worship-congregation.jpg"
                     alt="Church planting impact"
                     class="impact-image">
                <div class="impact-content">
                    <h3>New Churches Born</h3>
                    <p>"Alive Church sent our planting team to Nottingham. In 18 months, we've seen 200 people come to faith and gather every week. Your generosity planted this church."</p>
                    <p class="impact-author">— Ps. James, Nottingham Plant</p>
                </div>
            </article>
        </div>
    </div>
</section>

<!-- Stripe JavaScript Integration -->
<script src="https://js.stripe.com/v3/"></script>
<script>
    // NOTE: User needs to add their Stripe publishable key here
    // Get your key from: https://dashboard.stripe.com/apikeys
    const STRIPE_PUBLISHABLE_KEY = 'pk_test_51SzcpfJk18C9h8xIu6xrUQyR9ZS57dVBJaewYUHbwRamCEfqyVstjT20M8AyXXN5Oj0lrfZGWOpgxIeuhWBU6tGl00MxJvNvzZ'; // REPLACE THIS

    // Initialize Stripe (will show error message if key not configured)
    let stripe, elements, cardElement;

    if (STRIPE_PUBLISHABLE_KEY !== 'pk_test_51SzcpfJk18C9h8xIu6xrUQyR9ZS57dVBJaewYUHbwRamCEfqyVstjT20M8AyXXN5Oj0lrfZGWOpgxIeuhWBU6tGl00MxJvNvzZ') {
        stripe = Stripe(STRIPE_PUBLISHABLE_KEY);
        elements = stripe.elements();
        cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#2D1B4E',
                    fontFamily: 'Montserrat, sans-serif',
                    '::placeholder': {
                        color: '#9CA3AF',
                    },
                },
            },
        });
        cardElement.mount('#card-element');

        // Handle real-time validation errors
        cardElement.on('change', function(event) {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });
    } else {
        // Show setup message if Stripe key not configured
        document.getElementById('card-element').innerHTML = '<p style="padding: 1rem; background: #FEF3C7; border: 1px solid #F59E0B; border-radius: 0.5rem; color: #92400E;">⚠️ <strong>Stripe not configured.</strong> Add your Stripe publishable key in the JavaScript section to enable payments.</p>';
        document.getElementById('submit-button').disabled = true;
    }

    // Amount button selection
    const amountButtons = document.querySelectorAll('.amount-btn');
    const amountInput = document.getElementById('amount');
    const customAmountGroup = document.getElementById('custom-amount-group');

    amountButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            amountButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const amount = this.dataset.amount;
            if (amount === 'custom') {
                customAmountGroup.style.display = 'block';
                amountInput.value = '';
                amountInput.focus();
            } else {
                customAmountGroup.style.display = 'none';
                amountInput.value = amount;
            }
        });
    });

    // Form submission
    const form = document.getElementById('stripe-giving-form');
    form.addEventListener('submit', async function(event) {
        event.preventDefault();

        if (!stripe || !cardElement) {
            alert('Stripe is not configured. Please contact the church administrator.');
            return;
        }

        const submitButton = document.getElementById('submit-button');
        const spinner = document.getElementById('spinner');
        const buttonText = document.getElementById('button-text');

        submitButton.disabled = true;
        spinner.classList.remove('hidden');
        buttonText.classList.add('hidden');

        try {
            // Get form data
            const formData = new FormData(form);

            // Send to server to create payment intent/subscription
            const response = await fetch('/api/process-donation.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || 'Payment failed');
            }

            // Confirm payment with Stripe
            const {error: confirmError} = await stripe.confirmCardPayment(
                result.clientSecret,
                {
                    payment_method: {
                        card: cardElement,
                        billing_details: {
                            email: document.getElementById('email').value
                        }
                    }
                }
            );

            if (confirmError) {
                throw new Error(confirmError.message);
            }

            // Payment successful!
            alert('Thank you for your generous donation! You will receive a receipt via email.');
            form.reset();
            cardElement.clear();

            // Redirect to thank you page (optional)
            // window.location.href = '/thank-you.php';

        } catch (error) {
            // Show error
            document.getElementById('card-errors').textContent = error.message;
        } finally {
            submitButton.disabled = false;
            spinner.classList.add('hidden');
            buttonText.classList.remove('hidden');
        }
    });

    // Copy to clipboard functionality
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const textToCopy = this.dataset.copy;
            navigator.clipboard.writeText(textToCopy).then(() => {
                const originalText = this.textContent;
                this.textContent = 'Copied!';
                setTimeout(() => {
                    this.textContent = originalText;
                }, 2000);
            });
        });
    });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
