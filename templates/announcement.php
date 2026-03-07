<?php
/**
 * Announcement Template
 *
 * Bold announcement or event promotion page.
 * Variables available: $cms, $pageData, $heroStyle
 */

require __DIR__ . '/../config.php';
$page_title = $cms->text('page_title', $pageData['title'] ?? 'Announcement') . ' | ' . $site['name'];
include __DIR__ . '/../includes/header.php';
?>

<section class="announcement-hero">
    <div class="container text-center">
        <div class="announcement-badge" data-cms-editable="announcement_badge" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
            <?= $cms->text('announcement_badge', 'Upcoming Event'); ?>
        </div>
        <h1 data-cms-editable="hero_heading" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
            <?= $cms->text('hero_heading', $pageData['title'] ?? 'Big Announcement'); ?>
        </h1>
        <div class="announcement-details" data-cms-editable="announcement_details" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('announcement_details', '
            <p class="announcement-date">Saturday, January 1st at 6:00 PM</p>
            <p class="announcement-location">Main Auditorium</p>
            '); ?>
        </div>
        <div class="announcement-cta">
            <?php $ctaBtn = $cms->getBlockContent('cta_button_text', ''); ?>
            <?php if ($ctaBtn || $cms->isEditMode()): ?>
            <a class="btn btn-primary btn-lg"
               href="<?= htmlspecialchars($cms->getBlockContent('cta_button_url', '#')); ?>"
               data-cms-editable="cta_button_text" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
                <?= htmlspecialchars($ctaBtn ?: 'Register Now'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="page-content">
    <div class="container container-narrow">
        <div class="announcement-content" data-cms-editable="main_content" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('main_content', '
            <h2>About This Event</h2>
            <p>Add details about your announcement or event here...</p>

            <h2>What to Expect</h2>
            <p>Describe what attendees can look forward to...</p>
            '); ?>
        </div>
    </div>
</section>

<section class="announcement-cta-section">
    <div class="container text-center">
        <div data-cms-editable="cta_section" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('cta_section', '<h2>Don\'t Miss Out</h2><p>Space is limited. Reserve your spot today!</p>'); ?>
        </div>
        <?php if ($ctaBtn || $cms->isEditMode()): ?>
        <a class="btn btn-primary btn-lg" href="<?= htmlspecialchars($cms->getBlockContent('cta_button_url', '#')); ?>">
            <?= htmlspecialchars($ctaBtn ?: 'Register Now'); ?>
        </a>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
