<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/form-handler.php';

$page_title = 'Prayer Request | ' . $site['name'];
$prayer_notice = null;
$prayer_values = ['name' => '', 'email' => '', 'request' => '', 'public' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($prayer_values as $field => $_) {
        $prayer_values[$field] = sanitize_field($_POST[$field] ?? '');
    }
    $saved = process_form_submission('prayer', $prayer_values);
    if ($saved) {
        $prayer_notice = ['type' => 'success', 'message' => 'Your prayer request has been received. Our team is praying for you right now.'];
        $prayer_values = ['name' => '', 'email' => '', 'request' => '', 'public' => ''];
    } else {
        $prayer_notice = ['type' => 'error', 'message' => 'We were unable to send your request. Please try again or email us directly.'];
    }
}

include __DIR__ . '/includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('prayer');
}
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow light" data-cms-editable="hero_eyebrow" data-cms-page="prayer" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Prayer'); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="prayer" data-cms-type="text"><?= $cms->text('hero_headline', 'We\'re praying for you.'); ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="prayer" data-cms-type="text"><?= $cms->text('hero_subtext', 'Our prayer team meets daily to lift up every request. You\'re not alone—let us stand with you in faith.'); ?></p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="split">
            <div>
                <h2 data-cms-editable="how_headline" data-cms-page="prayer" data-cms-type="text"><?= $cms->text('how_headline', 'How we pray'); ?></h2>
                <ul class="info-list">
                    <li data-cms-editable="how_item_1" data-cms-page="prayer" data-cms-type="rich"><?= $cms->html('how_item_1', '<strong>Daily Prayer:</strong> Our team prays through requests every morning at 7AM.'); ?></li>
                    <li data-cms-editable="how_item_2" data-cms-page="prayer" data-cms-type="rich"><?= $cms->html('how_item_2', '<strong>Confidential:</strong> All requests are kept private unless you choose to share publicly.'); ?></li>
                    <li data-cms-editable="how_item_3" data-cms-page="prayer" data-cms-type="rich"><?= $cms->html('how_item_3', '<strong>Follow-up:</strong> We\'ll check in with you to see how God is moving.'); ?></li>
                    <li data-cms-editable="how_item_4" data-cms-page="prayer" data-cms-type="rich"><?= $cms->html('how_item_4', '<strong>Always Available:</strong> You can submit as many requests as you need, anytime.'); ?></li>
                </ul>
                <img src="/assets/imgs/gallery/alive-church-acoustic-worship-prayer.jpg"
                     alt="Prayer at Alive Church"
                     style="border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;">
            </div>

            <form class="card form-card" method="post" id="prayer-form">
                <h3 data-cms-editable="form_title" data-cms-page="prayer" data-cms-type="text"><?= $cms->text('form_title', 'Share Your Prayer Need'); ?></h3>
                <input type="hidden" name="form_type" value="prayer">
                <div class="form-message" id="form-message" style="display: none;"></div>

                <label>
                    <span>Your Name</span>
                    <input type="text" name="name" id="name" placeholder="First & Last" required>
                    <span class="form-error" id="name-error"></span>
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="email" id="email" placeholder="your@email.com" required>
                    <span class="form-error" id="email-error"></span>
                </label>

                <label>
                    <span>Prayer Request</span>
                    <textarea rows="6" name="request" id="request"
                              placeholder="Share what's on your heart..."
                              required></textarea>
                    <span class="form-error" id="request-error"></span>
                </label>

                <label class="checkbox-label">
                    <input type="checkbox" name="public" value="yes">
                    <span>Share this request publicly so the church can pray with me</span>
                </label>

                <button type="submit" class="btn btn-primary" id="submit-btn">
                    <span class="btn-text">Submit Prayer Request</span>
                    <span class="btn-spinner" style="display: none;">
                        <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" opacity="0.25"/>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round">
                                <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                            </path>
                        </svg>
                    </span>
                </button>
            </form>

            <script>
            document.getElementById('prayer-form').addEventListener('submit', async function(e) {
                e.preventDefault();

                const form = this;
                const btn = document.getElementById('submit-btn');
                const btnText = btn.querySelector('.btn-text');
                const btnSpinner = btn.querySelector('.btn-spinner');
                const formMessage = document.getElementById('form-message');

                // Clear previous errors
                document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
                formMessage.style.display = 'none';

                // Show loading state
                btn.disabled = true;
                btnText.style.display = 'none';
                btnSpinner.style.display = 'inline-block';

                try {
                    const formData = new FormData(form);
                    const response = await fetch('/api/forms/submit', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        formMessage.className = 'form-message success';
                        formMessage.textContent = data.message;
                        formMessage.style.display = 'block';
                        form.reset();

                        btn.disabled = false;
                        btnText.style.display = 'inline';
                        btnSpinner.style.display = 'none';
                    } else {
                        if (data.errors) {
                            for (const [field, error] of Object.entries(data.errors)) {
                                const fieldError = document.getElementById(field + '-error');
                                if (fieldError) fieldError.textContent = error;
                            }
                        }

                        formMessage.className = 'form-message error';
                        formMessage.textContent = data.error || 'Please fix the errors and try again.';
                        formMessage.style.display = 'block';

                        btn.disabled = false;
                        btnText.style.display = 'inline';
                        btnSpinner.style.display = 'none';
                    }
                } catch (error) {
                    formMessage.className = 'form-message error';
                    formMessage.textContent = 'Something went wrong. Please try again.';
                    formMessage.style.display = 'block';

                    btn.disabled = false;
                    btnText.style.display = 'inline';
                    btnSpinner.style.display = 'none';
                }
            });
            </script>
        </div>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow center-text">
        <h2 data-cms-editable="connect_headline" data-cms-page="prayer" data-cms-type="text"><?= $cms->text('connect_headline', 'Other Ways to Connect'); ?></h2>
        <p data-cms-editable="connect_subtext" data-cms-page="prayer" data-cms-type="text"><?= $cms->text('connect_subtext', 'Looking for pastoral care, counseling, or someone to talk to? Our care team is here for you.'); ?></p>
        <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap;">
            <a href="mailto:<?= htmlspecialchars($site['email']); ?>" class="btn btn-outline">
                Email Our Team
            </a>
            <?php if (!empty($site['phone'])): ?>
            <a href="tel:<?= preg_replace('/\s+/', '', $site['phone']); ?>" class="btn btn-outline">
                Call Us
            </a>
            <?php endif; ?>
            <a href="/visit" class="btn btn-primary">
                Visit Us This Sunday
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
