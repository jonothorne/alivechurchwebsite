<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/form-handler.php';

$page_title = 'Next Steps | ' . $site['name'];
$prayer_notice = null;
$prayer_values = ['name' => '', 'email' => '', 'request' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($prayer_values as $field => $_) {
        $prayer_values[$field] = sanitize_field($_POST[$field] ?? '');
    }
    $saved = process_form_submission('prayer', $prayer_values);
    if ($saved) {
        $prayer_notice = ['type' => 'success', 'message' => 'Your request has been received. Our team will pray and follow up soon.'];
        $prayer_values = ['name' => '', 'email' => '', 'request' => ''];
    } else {
        $prayer_notice = ['type' => 'error', 'message' => 'We were unable to send your request. Please try again.'];
    }
}

include __DIR__ . '/includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('next-steps');
}
?>
<section class="page-hero">
    <div class="container narrow">
        <p class="eyebrow light" data-cms-editable="hero_eyebrow" data-cms-page="next-steps" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Next Steps'); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="next-steps" data-cms-type="text"><?= $cms->text('hero_headline', 'Let\'s take the journey together.'); ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="next-steps" data-cms-type="text"><?= $cms->text('hero_subtext', 'We\'re here to help you move from attending to belonging. Pick a next step and our team will reach out with details.'); ?></p>
    </div>
</section>
<section class="content-section">
    <div class="container">
        <div class="card-grid">
            <?php foreach ($next_steps as $step): ?>
                <article class="step-card">
                    <h3><?= $step['title']; ?></h3>
                    <p><?= $step['copy']; ?></p>
                    <a class="btn btn-outline" href="<?= $step['link']; ?>">Start now</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section class="content-section alt">
    <div class="container split">
        <div>
            <h2 data-cms-editable="prayer_headline" data-cms-page="next-steps" data-cms-type="text"><?= $cms->text('prayer_headline', 'Need prayer or care?'); ?></h2>
            <p data-cms-editable="prayer_text" data-cms-page="next-steps" data-cms-type="text"><?= $cms->text('prayer_text', 'Our pastoral team and prayer room intercessors stand ready to serve your family. We can pray via email, phone, or at a gathering.'); ?></p>
            <img src="/assets/imgs/gallery/alive-church-acoustic-worship-prayer.jpg" alt="Prayer and worship at Alive Church" style="border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;">
        </div>
        <form class="card form-card" method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <?php if ($prayer_notice): ?>
                <p class="notice notice-<?= $prayer_notice['type']; ?>" role="status"><?= $prayer_notice['message']; ?></p>
            <?php endif; ?>
            <label>
                <span>Your Name</span>
                <input type="text" name="name" placeholder="Jane Doe" value="<?= htmlspecialchars($prayer_values['name']); ?>" required>
            </label>
            <label>
                <span>Email</span>
                <input type="email" name="email" placeholder="you@email.com" value="<?= htmlspecialchars($prayer_values['email']); ?>" required>
            </label>
            <label>
                <span>Prayer Request</span>
                <textarea name="request" rows="4" placeholder="How can we pray?" required><?= htmlspecialchars($prayer_values['request']); ?></textarea>
            </label>
            <button class="btn btn-primary" type="submit">Submit Request</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
