<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/form-handler.php';

$page_title = 'Apply to Serve | ' . $site['name'];

// Get team from query parameter
$team = $_GET['team'] ?? '';
$selected_team = null;

// Find the team from serve opportunities
foreach ($serve_opportunities as $opportunity) {
    // Create slug from title for matching
    $title_slug = strtolower(str_replace(' ', '-', $opportunity['title']));
    if ($title_slug === $team) {
        $selected_team = $opportunity;
        break;
    }
}

$serve_notice = null;
$serve_values = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'team' => $selected_team['title'] ?? '',
    'availability' => '',
    'experience' => '',
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($serve_values as $field => $_) {
        $serve_values[$field] = sanitize_field($_POST[$field] ?? '');
    }
    $saved = process_form_submission('serve-application', $serve_values);
    if ($saved) {
        $serve_notice = ['type' => 'success', 'message' => 'Thanks for your interest in serving! Our team will reach out within 48 hours to discuss next steps.'];
        $serve_values = ['name' => '', 'email' => '', 'phone' => '', 'team' => $selected_team['title'] ?? '', 'availability' => '', 'experience' => '', 'message' => ''];
    } else {
        $serve_notice = ['type' => 'error', 'message' => 'Something went wrong. Please try again or email us directly.'];
    }
}

include __DIR__ . '/../includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/../includes/cms/ContentManager.php';
    $cms = new ContentManager('serve');
}
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow light" data-cms-editable="hero_eyebrow" data-cms-page="serve" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Serve With Us'); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="serve" data-cms-type="text"><?= $cms->text('hero_headline', 'Apply to Serve'); ?><?= $selected_team ? ': ' . htmlspecialchars($selected_team['title']) : ''; ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="serve" data-cms-type="text"><?= $cms->text('hero_subtext', 'Thank you for your interest in using your gifts to serve! Fill out the form below and our team will connect with you.'); ?></p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="split">
            <?php if ($selected_team): ?>
                <div>
                    <h2>About This Team</h2>
                    <p><strong><?= htmlspecialchars($selected_team['title']); ?></strong></p>
                    <p><?= htmlspecialchars($selected_team['description']); ?></p>

                    <div style="margin: 1.5rem 0;">
                        <p><strong>Commitment:</strong> <?= htmlspecialchars($selected_team['commitment']); ?></p>
                    </div>

                    <?php if (!empty($selected_team['areas'])): ?>
                        <div>
                            <p><strong>Areas to serve:</strong></p>
                            <div class="areas">
                                <?php foreach ($selected_team['areas'] as $area): ?>
                                    <span class="area-tag"><?= htmlspecialchars($area); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($selected_team['image'])): ?>
                        <img src="<?= htmlspecialchars($selected_team['image']); ?>"
                             alt="<?= htmlspecialchars($selected_team['title']); ?>"
                             style="border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;">
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div>
                    <h2 data-cms-editable="why_headline" data-cms-page="serve" data-cms-type="text"><?= $cms->text('why_headline', 'Why Serve?'); ?></h2>
                    <ul class="info-list">
                        <li data-cms-editable="why_item_1" data-cms-page="serve" data-cms-type="rich"><?= $cms->html('why_item_1', '<strong>Make a Difference:</strong> Your gifts can change someone\'s Sunday experience.'); ?></li>
                        <li data-cms-editable="why_item_2" data-cms-page="serve" data-cms-type="rich"><?= $cms->html('why_item_2', '<strong>Grow Spiritually:</strong> Serving deepens your own faith journey.'); ?></li>
                        <li data-cms-editable="why_item_3" data-cms-page="serve" data-cms-type="rich"><?= $cms->html('why_item_3', '<strong>Build Community:</strong> Meet amazing people and form lasting friendships.'); ?></li>
                        <li data-cms-editable="why_item_4" data-cms-page="serve" data-cms-type="rich"><?= $cms->html('why_item_4', '<strong>Discover Your Calling:</strong> Find where your passions meet our mission.'); ?></li>
                    </ul>
                    <img src="/assets/imgs/gallery/alive-church-community-cafe-outdoor.jpg"
                         alt="Serving at Alive Church"
                         style="border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;">
                </div>
            <?php endif; ?>

            <form class="card form-card" method="post" id="serve-form">
                <h3 data-cms-editable="form_title" data-cms-page="serve" data-cms-type="text"><?= $cms->text('form_title', 'Application Form'); ?></h3>

                <input type="hidden" name="form_type" value="serve">
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
                    <span>Team/Ministry *</span>
                    <select name="team" id="team" required>
                        <option value="">Select a team...</option>
                        <?php foreach ($serve_opportunities as $opportunity): ?>
                            <option value="<?= htmlspecialchars($opportunity['title']); ?>"
                                <?= ($selected_team['title'] ?? '') === $opportunity['title'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($opportunity['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Availability *</span>
                    <select name="availability" id="availability" required>
                        <option value="">Select your availability...</option>
                        <option>Every week</option>
                        <option>Twice a month</option>
                        <option>Once a month</option>
                        <option>Special events only</option>
                        <option>Flexible</option>
                    </select>
                </label>

                <label>
                    <span>Relevant Experience</span>
                    <textarea rows="3" name="experience" id="experience" placeholder="Have you served in this area before? Any relevant skills or training?"></textarea>
                </label>

                <label>
                    <span>Anything Else We Should Know?</span>
                    <textarea rows="3" name="message" id="message" placeholder="Questions, preferences, or additional info..."></textarea>
                </label>

                <button type="submit" class="btn btn-primary" id="submit-btn">
                    <span class="btn-text">Submit Application</span>
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
            document.getElementById('serve-form').addEventListener('submit', async function(e) {
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
