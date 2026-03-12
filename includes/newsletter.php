    <!-- Newsletter Signup Section -->
    <div class="newsletter-section">
        <div class="container">
            <div class="newsletter-card">
                <div>
                    <h3>Stay in the loop</h3>
                    <p>Get weekly updates, event invites, and encouragement delivered to your inbox.</p>
                </div>

                <div class="newsletter-message" id="newsletter-message" style="display: none;"></div>

                <form class="newsletter-form" id="newsletter-form" action="/api/newsletter-signup.php" method="post">
                    <input type="email" name="email" placeholder="Your email" required class="newsletter-input" id="newsletter-email">
                    <button type="submit" class="btn btn-primary" id="newsletter-submit">Subscribe</button>
                </form>
            </div>
        </div>
    </div>
