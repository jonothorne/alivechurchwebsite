    <!-- Newsletter Signup Section -->
    <div class="newsletter-section">
        <div class="container">
            <div class="newsletter-card">
                <div>
                    <h3>Stay in the loop</h3>
                    <p>Get weekly updates, event invites, and encouragement delivered to your inbox.</p>
                </div>

                <?php if (isset($_SESSION['newsletter_message'])): ?>
                    <div class="notice notice-success" role="status">
                        <?= htmlspecialchars($_SESSION['newsletter_message']); ?>
                    </div>
                    <?php unset($_SESSION['newsletter_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['newsletter_error'])): ?>
                    <div class="notice notice-error" role="alert">
                        <?= htmlspecialchars($_SESSION['newsletter_error']); ?>
                    </div>
                    <?php unset($_SESSION['newsletter_error']); ?>
                <?php endif; ?>

                <form class="newsletter-form" action="/api/newsletter-signup.php" method="post">
                    <input type="email" name="email" placeholder="Your email" required class="newsletter-input">
                    <button type="submit" class="btn btn-primary">Subscribe</button>
                </form>
            </div>
        </div>
    </div>
