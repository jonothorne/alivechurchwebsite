<?php
/**
 * Our Vision Page
 */
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/hero-textures.php';

$page_title = 'Our Vision | ' . $site['name'];
$page_description = 'Discover the vision and mission of ' . $site['name'] . ' - helping people live fully alive in Jesus and taking hope to every street.';
$hero_texture_class = get_random_texture();

// Initialize CMS
require_once __DIR__ . '/../includes/cms/ContentManager.php';
$cms = new ContentManager('about-vision');

include __DIR__ . '/../includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <?= $cms->text('hero_eyebrow', 'Looking Forward', ['tag' => 'p', 'class' => 'eyebrow']); ?>
        <?= $cms->text('hero_headline', 'Our Vision', ['tag' => 'h1']); ?>
        <?= $cms->text('hero_subtext', 'For over four decades, we\'ve been driven by a God-given vision to see lives transformed and communities restored.', ['tag' => 'p']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('founding_headline', 'Where It All Began', ['tag' => 'h2']); ?>
        <?= $cms->html('founding_content', '<p>When nine people gathered in Norwich in 1985, they carried a simple but powerful vision:</p>
        <p class="lead"><strong>Love God. Love one another. Love the world.</strong></p>
        <p>Inspired by Isaiah 58:12, they believed God was calling them to become "repairers of the breach" – bringing restoration, healing, and hope to broken lives and communities. That founding vision continues to shape everything we do today.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow">
        <?= $cms->text('mission_headline', 'Our Mission', ['tag' => 'h2']); ?>
        <?= $cms->html('mission_content', '<p class="lead"><strong>To help people live fully alive in Jesus.</strong></p>
        <p>This is more than a slogan – it\'s the heartbeat of who we are. We chose the name "Alive Church" because we believe that true life is found in Jesus. Not just existing, but thriving. Not just surviving, but flourishing in every area of life – spiritually, relationally, emotionally, and practically.</p>
        <p>We want to see people encounter Jesus, grow deep roots in faith, discover their purpose, and step into everything God has for them.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('vision_headline', 'Our Vision', ['tag' => 'h2']); ?>
        <?= $cms->html('vision_content', '<p class="lead"><strong>Hope to every street.</strong></p>
        <p>We dream of a church that doesn\'t just gather on Sundays but scatters throughout the week – bringing the hope of Jesus to every neighbourhood, workplace, school, and community in our city.</p>
        <p>This isn\'t new for us. From homeless outreach on the streets of Norwich in the 1990s, to food parcels delivered during COVID, to community cafés and foodbanks – we\'ve always believed the church should be good news to our city. We want to see transformation not just in individual lives, but in the very fabric of Norwich and beyond.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow">
        <?= $cms->text('values_headline', 'Our Values', ['tag' => 'h2']); ?>
        <?= $cms->html('values_content', '<ul class="info-list">
            <li><strong>Presence:</strong> Everything begins with worship. We prioritise encountering God together and making space for the Holy Spirit to move – just as He did in the remarkable season of 2010-2011 when services extended to multiple days a week.</li>
            <li><strong>People:</strong> Everyone is seen, known, and needed. From nine people to hundreds, we\'ve always been a family where everyone belongs. We build authentic community across generations.</li>
            <li><strong>Purpose:</strong> We equip everyday people to influence their world. Whether it\'s a prayer meeting, a mission trip to Germany, or organising a town fun day – we believe every member is called to make a difference.</li>
            <li><strong>Play:</strong> Joy is our culture; laughter is welcome. We take God seriously, but we don\'t take ourselves too seriously. Celebration and creativity have always been part of who we are.</li>
        </ul>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('heart_headline', 'What We\'re Known For', ['tag' => 'h2']); ?>
        <?= $cms->html('heart_content', '<p><strong>Heart for the Marginalised</strong><br>
        From CityCare\'s homeless outreach to our ongoing foodbank and community café, we\'ve always sought to love those on the margins – walking alongside people through court visits, detox programmes, and the hardest seasons of life.</p>
        <p><strong>Unity Across Churches</strong><br>
        We don\'t believe we\'re the only church in Norwich – we\'re part of a wider family. From hosting "Together for Jesus" gatherings with 1,700+ believers to "Prayer in the Park" with 1,200+ Christians, we champion unity and partnership across the body of Christ.</p>
        <p><strong>Raising Up Leaders</strong><br>
        We believe in empowering the next generation. We\'ve planted churches across Norfolk, sent mission teams internationally, and continue to invest in developing leaders who will carry the gospel forward.</p>
        <p><strong>Creative Excellence</strong><br>
        Whether it\'s a 100-piece choir outside City Hall, a daily livestream during lockdown, or a Sunday worship gathering – we believe creativity honours God and communicates the gospel in fresh ways.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow">
        <?= $cms->text('future_headline', 'The Future', ['tag' => 'h2']); ?>
        <?= $cms->html('future_content', '<p>We\'ve seen God do incredible things over four decades – but we believe our best days are still ahead.</p>
        <p>We\'re praying and planning for continued growth, not for growth\'s sake, but because there are still people in Norwich and beyond who need to know Jesus. We\'re developing leaders, strengthening our ministries, and dreaming big dreams about how God might use our church to bring hope to every street.</p>
        <p>The same vision that started with nine people still burns in our hearts today: <strong>Love God. Love one another. Love the world.</strong></p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('partner_headline', 'Join the Mission', ['tag' => 'h2']); ?>
        <?= $cms->html('partner_content', '<p>This vision is too big for any one person – it takes all of us. There are many ways you can be part of what God is doing:</p>
        <ul class="info-list">
            <li><strong>Pray:</strong> Join us in praying for our church, our city, and our world.</li>
            <li><strong>Belong:</strong> Find your place in a group where you can grow and be known.</li>
            <li><strong>Serve:</strong> Use your gifts and talents to make a difference – on a team or in your everyday life.</li>
            <li><strong>Give:</strong> Support the work financially through generous giving.</li>
            <li><strong>Invite:</strong> Bring someone with you to experience church and encounter Jesus.</li>
        </ul>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section cta-section alt">
    <div class="container narrow text-center">
        <?= $cms->text('cta_headline', 'Be Part of Something Bigger', ['tag' => 'h2']); ?>
        <?= $cms->text('cta_text', 'We\'d love to have you join us on this adventure. Whether you\'re exploring faith or ready to dive in, there\'s a place for you here.', ['tag' => 'p']); ?>
        <div class="cta-buttons">
            <a href="/visit" class="btn btn-primary">Plan Your Visit</a>
            <a href="/connect" class="btn btn-secondary">Get Connected</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
