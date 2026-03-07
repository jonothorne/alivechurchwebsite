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
                    <li><strong>Daily Prayer:</strong> Our team prays through requests every morning at 7AM.</li>
                    <li><strong>Confidential:</strong> All requests are kept private unless you choose to share publicly.</li>
                    <li><strong>Follow-up:</strong> We'll check in with you to see how God is moving.</li>
                    <li><strong>Always Available:</strong> You can submit as many requests as you need, anytime.</li>
                </ul>
                <img src="/assets/imgs/gallery/alive-church-acoustic-worship-prayer.jpg"
                     alt="Prayer at Alive Church"
                     style="border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;">
            </div>

            <form class="card form-card" method="post">
                <h3>Share Your Prayer Need</h3>
                <?php if ($prayer_notice): ?>
                    <p class="notice notice-<?= $prayer_notice['type']; ?>" role="status"><?= $prayer_notice['message']; ?></p>
                <?php endif; ?>

                <label>
                    <span>Your Name</span>
                    <input type="text" name="name" placeholder="First & Last"
                           value="<?= htmlspecialchars($prayer_values['name']); ?>" required>
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="email" placeholder="your@email.com"
                           value="<?= htmlspecialchars($prayer_values['email']); ?>" required>
                </label>

                <label>
                    <span>Prayer Request</span>
                    <textarea rows="6" name="request"
                              placeholder="Share what's on your heart..."
                              required><?= htmlspecialchars($prayer_values['request']); ?></textarea>
                </label>

                <label class="checkbox-label">
                    <input type="checkbox" name="public" value="yes"
                           <?= $prayer_values['public'] === 'yes' ? 'checked' : ''; ?>>
                    <span>Share this request publicly so the church can pray with me</span>
                </label>

                <button type="submit" class="btn btn-primary">Submit Prayer Request</button>
            </form>
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
            <a href="tel:<?= preg_replace('/\s+/', '', $site['phone']); ?>" class="btn btn-outline">
                Call Us
            </a>
            <a href="/visit" class="btn btn-primary">
                Visit Us This Sunday
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
