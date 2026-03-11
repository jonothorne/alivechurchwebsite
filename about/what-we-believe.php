<?php
/**
 * What We Believe Page
 */
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/hero-textures.php';

$page_title = 'What We Believe | ' . $site['name'];
$page_description = 'Discover the core beliefs and values that guide ' . $site['name'] . ' - rooted in Scripture and centred on Jesus.';
$hero_texture_class = get_random_texture();

// Initialize CMS
require_once __DIR__ . '/../includes/cms/ContentManager.php';
$cms = new ContentManager('about-beliefs');

include __DIR__ . '/../includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <?= $cms->text('hero_eyebrow', 'Our Faith', ['tag' => 'p', 'class' => 'eyebrow']); ?>
        <?= $cms->text('hero_headline', 'What We Believe', ['tag' => 'h1']); ?>
        <?= $cms->text('hero_subtext', 'Our beliefs are rooted in Scripture and centred on the person and work of Jesus Christ.', ['tag' => 'p']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('bible_headline', 'The Bible', ['tag' => 'h2']); ?>
        <?= $cms->html('bible_content', '<p>We believe the Bible is the inspired, infallible Word of God. It is our ultimate authority for faith and practice, revealing God\'s character, His plan for salvation, and His purposes for humanity.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow">
        <?= $cms->text('god_headline', 'God', ['tag' => 'h2']); ?>
        <?= $cms->html('god_content', '<p>We believe in one God, eternally existing in three persons: Father, Son, and Holy Spirit. God is the Creator of all things, sovereign over all, and worthy of all worship and praise.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('jesus_headline', 'Jesus Christ', ['tag' => 'h2']); ?>
        <?= $cms->html('jesus_content', '<p>We believe Jesus Christ is fully God and fully man. He was born of a virgin, lived a sinless life, died on the cross for our sins, rose bodily from the dead, and ascended to heaven where He intercedes for us. He will return in glory to judge the living and the dead.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow">
        <?= $cms->text('spirit_headline', 'The Holy Spirit', ['tag' => 'h2']); ?>
        <?= $cms->html('spirit_content', '<p>We believe the Holy Spirit convicts the world of sin, regenerates believers, and empowers them for godly living and service. We believe in the present-day work of the Spirit, including spiritual gifts for the building up of the church.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('salvation_headline', 'Salvation', ['tag' => 'h2']); ?>
        <?= $cms->html('salvation_content', '<p>We believe salvation is a gift of God\'s grace, received through faith in Jesus Christ alone. It cannot be earned by good works but is freely given to all who trust in Christ\'s finished work on the cross.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow">
        <?= $cms->text('church_headline', 'The Church', ['tag' => 'h2']); ?>
        <?= $cms->html('church_content', '<p>We believe the Church is the body of Christ, made up of all believers across the world and throughout history. The local church gathers for worship, fellowship, teaching, and mission - carrying the gospel to our communities and the nations.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('values_headline', 'Our Values', ['tag' => 'h2']); ?>
        <?= $cms->html('values_content', '<ul class="info-list">
            <li><strong>Presence:</strong> Everything begins with worship. We prioritise encountering God\'s presence together.</li>
            <li><strong>People:</strong> Everyone is seen, known, and needed. We build authentic community where all belong.</li>
            <li><strong>Purpose:</strong> We equip everyday saints to influence their world for Christ.</li>
            <li><strong>Play:</strong> Joy is our culture; laughter is welcome. We celebrate life together.</li>
        </ul>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section cta-section alt">
    <div class="container narrow text-center">
        <?= $cms->text('cta_headline', 'Questions About Faith?', ['tag' => 'h2']); ?>
        <?= $cms->text('cta_text', 'We\'d love to talk with you about what it means to follow Jesus. Reach out anytime - we\'re here to help.', ['tag' => 'p']); ?>
        <div class="cta-buttons">
            <a href="/contact-us" class="btn btn-primary">Get in Touch</a>
            <a href="/about/history" class="btn btn-secondary">Our Story</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
