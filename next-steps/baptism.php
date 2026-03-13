<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/form-handler.php';

$page_title = 'Baptism Signup | ' . $site['name'];

$baptism_notice = null;
$baptism_values = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'baptism_status' => '',
    'testimony' => '',
    'preferred_date' => '',
    'guests' => '',
    'questions' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($baptism_values as $field => $_) {
        $baptism_values[$field] = sanitize_field($_POST[$field] ?? '');
    }
    $saved = process_form_submission('baptism-signup', $baptism_values);
    if ($saved) {
        $baptism_notice = ['type' => 'success', 'message' => 'Thank you for taking this step! Our team will contact you within 48 hours to discuss baptism preparation and schedule your baptism.'];
        $baptism_values = ['name' => '', 'email' => '', 'phone' => '', 'baptism_status' => '', 'testimony' => '', 'preferred_date' => '', 'guests' => '', 'questions' => ''];
    } else {
        $baptism_notice = ['type' => 'error', 'message' => 'Something went wrong. Please try again or email us directly.'];
    }
}

include __DIR__ . '/../includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/../includes/cms/ContentManager.php';
    $cms = new ContentManager('baptism');
}
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow light" data-cms-editable="hero_eyebrow" data-cms-page="baptism" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Next Steps'); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="baptism" data-cms-type="text"><?= $cms->text('hero_headline', 'Baptism: Declare Your Faith'); ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="baptism" data-cms-type="text"><?= $cms->text('hero_subtext', 'Baptism is a public declaration of your faith in Jesus. It\'s an outward sign of an inward decision to follow Him.'); ?></p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="split">
            <div>
                <h2 data-cms-editable="why_headline" data-cms-page="baptism" data-cms-type="text"><?= $cms->text('why_headline', 'Why Get Baptized?'); ?></h2>
                <ul class="info-list">
                    <li data-cms-editable="why_item_1" data-cms-page="baptism" data-cms-type="rich"><?= $cms->html('why_item_1', '<strong>Follow Jesus\' Example:</strong> Jesus was baptized in the Jordan River (Matthew 3:13-17).'); ?></li>
                    <li data-cms-editable="why_item_2" data-cms-page="baptism" data-cms-type="rich"><?= $cms->html('why_item_2', '<strong>Obey His Command:</strong> Jesus instructed His followers to be baptized (Matthew 28:19).'); ?></li>
                    <li data-cms-editable="why_item_3" data-cms-page="baptism" data-cms-type="rich"><?= $cms->html('why_item_3', '<strong>Identify with Christ:</strong> Baptism symbolizes dying to your old life and rising to new life in Christ.'); ?></li>
                    <li data-cms-editable="why_item_4" data-cms-page="baptism" data-cms-type="rich"><?= $cms->html('why_item_4', '<strong>Celebrate Publicly:</strong> Share your faith story with your church family and invite loved ones to witness.'); ?></li>
                </ul>

                <div class="card" style="margin-top: 2rem;">
                    <h3 data-cms-editable="expect_headline" data-cms-page="baptism" data-cms-type="text"><?= $cms->text('expect_headline', 'What to Expect'); ?></h3>
                    <p data-cms-editable="expect_step_1" data-cms-page="baptism" data-cms-type="rich"><?= $cms->html('expect_step_1', '<strong>1. Preparation:</strong> Meet with a pastor to discuss your faith journey and what baptism means.'); ?></p>
                    <p data-cms-editable="expect_step_2" data-cms-page="baptism" data-cms-type="rich"><?= $cms->html('expect_step_2', '<strong>2. Celebration Sunday:</strong> Share your testimony and be baptized during a service.'); ?></p>
                    <p data-cms-editable="expect_step_3" data-cms-page="baptism" data-cms-type="rich"><?= $cms->html('expect_step_3', '<strong>3. Invitation:</strong> Invite friends and family to witness this special moment.'); ?></p>
                    <p data-cms-editable="expect_step_4" data-cms-page="baptism" data-cms-type="rich"><?= $cms->html('expect_step_4', '<strong>4. Party Time:</strong> Celebrate with our church family after the service!'); ?></p>
                </div>

                <img src="/assets/imgs/gallery/alive-church-worship-congregation.jpg"
                     alt="Baptism celebration at Alive Church"
                     style="border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;">
            </div>

            <form class="card form-card" method="post" id="baptism-form">
                <h3 data-cms-editable="form_title" data-cms-page="baptism" data-cms-type="text"><?= $cms->text('form_title', 'Baptism Signup'); ?></h3>

                <input type="hidden" name="form_type" value="baptism">
                <div class="form-message" id="form-message" style="display: none;"></div>

                <label>
                    <span>Name *</span>
                    <input type="text" name="name" id="name" placeholder="First & Last" required>
                    <span class="form-error" id="name-error"></span>
                </label>

                <label>
                    <span>Email *</span>
                    <input type="email" name="email" id="email" placeholder="you@email.com" required>
                    <span class="form-error" id="email-error"></span>
                </label>

                <label>
                    <span>Phone</span>
                    <input type="tel" name="phone" id="phone" placeholder="07123 456789">
                </label>

                <label>
                    <span>Have you been baptized before? *</span>
                    <select name="baptism_status" id="baptism_status" required>
                        <option value="">Please select...</option>
                        <option>No, this is my first time</option>
                        <option>Yes, as a baby</option>
                        <option>Yes, as a believer</option>
                        <option>Not sure</option>
                    </select>
                </label>

                <label>
                    <span>Brief Faith Story (1-2 sentences) *</span>
                    <textarea rows="3" name="testimony" id="testimony" placeholder="Tell us about when and how you came to faith in Jesus..." required></textarea>
                    <div class="form-help">This helps us prepare you for sharing your testimony on baptism day</div>
                </label>

                <label>
                    <span>Preferred Date/Service</span>
                    <input type="text" name="preferred_date" id="preferred_date" placeholder="e.g., 'Next available' or 'Easter Sunday'">
                </label>

                <label>
                    <span>Expected Number of Guests</span>
                    <input type="number" name="guests" id="guests" placeholder="How many people will you invite?" min="0">
                    <div class="form-help">This helps us prepare seating and celebration details</div>
                </label>

                <label>
                    <span>Questions or Special Requests</span>
                    <textarea rows="3" name="questions" id="questions" placeholder="Any questions about baptism or special circumstances we should know about?"></textarea>
                </label>

                <button type="submit" class="btn btn-primary" id="submit-btn">
                    <span class="btn-text">Submit Baptism Request</span>
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
            document.getElementById('baptism-form').addEventListener('submit', async function(e) {
                e.preventDefault();

                const form = this;
                const btn = document.getElementById('submit-btn');
                const btnText = btn.querySelector('.btn-text');
                const btnSpinner = btn.querySelector('.btn-spinner');
                const formMessage = document.getElementById('form-message');

                document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
                formMessage.style.display = 'none';

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
                    }

                    btn.disabled = false;
                    btnText.style.display = 'inline';
                    btnSpinner.style.display = 'none';
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
