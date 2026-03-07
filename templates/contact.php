<?php
/**
 * Contact Page Template
 *
 * Contact info, map, and optional form.
 * Variables available: $cms, $pageData, $heroStyle
 */

require __DIR__ . '/../config.php';
$page_title = $cms->text('page_title', $pageData['title'] ?? 'Contact Us') . ' | ' . $site['name'];
include __DIR__ . '/../includes/header.php';
?>

<?php if ($heroStyle !== 'none'): ?>
<section class="page-hero <?= 'hero-' . htmlspecialchars($heroStyle); ?>">
    <div class="container">
        <h1 data-cms-editable="hero_heading" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
            <?= $cms->text('hero_heading', 'Contact Us'); ?>
        </h1>
        <?php $heroSubtext = $cms->getBlockContent('hero_subtext', ''); ?>
        <?php if ($heroSubtext || $cms->isEditMode()): ?>
        <p data-cms-editable="hero_subtext" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
            <?= htmlspecialchars($heroSubtext); ?>
        </p>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="page-content">
    <div class="container">
        <div class="contact-layout">
            <div class="contact-info">
                <div data-cms-editable="contact_details" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
                    <?= $cms->html('contact_details', '
                    <h2>Get in Touch</h2>
                    <div class="contact-item">
                        <h3>Address</h3>
                        <p>123 Church Street<br>City, State 12345</p>
                    </div>
                    <div class="contact-item">
                        <h3>Phone</h3>
                        <p><a href="tel:+1234567890">(123) 456-7890</a></p>
                    </div>
                    <div class="contact-item">
                        <h3>Email</h3>
                        <p><a href="mailto:hello@example.com">hello@example.com</a></p>
                    </div>
                    <div class="contact-item">
                        <h3>Service Times</h3>
                        <p>Sunday: 9:00 AM & 11:00 AM<br>Wednesday: 7:00 PM</p>
                    </div>
                    '); ?>
                </div>
            </div>
            <div class="contact-form-area">
                <div data-cms-editable="contact_form_intro" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
                    <?= $cms->html('contact_form_intro', '<h2>Send us a Message</h2><p>We\'d love to hear from you!</p>'); ?>
                </div>
                <!-- Contact form can be added here -->
                <form class="contact-form" method="post" action="/api/contact.php">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="contact-map" data-cms-editable="map_embed" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
    <?= $cms->html('map_embed', '<div class="map-placeholder" style="height: 400px; background: var(--color-grey); display: flex; align-items: center; justify-content: center;"><p>Add Google Maps embed code here</p></div>'); ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
