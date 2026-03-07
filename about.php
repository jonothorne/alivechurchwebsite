<?php
require __DIR__ . '/config.php';
$page_title = 'About | ' . $site['name'];
include __DIR__ . '/includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('about');
}
?>
<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <?= $cms->text('hero_eyebrow', 'About Alive', ['tag' => 'p', 'class' => 'eyebrow']); ?>
        <?= $cms->text('hero_headline', 'Our story & values.', ['tag' => 'h1']); ?>
        <?= $cms->text('hero_subtext', 'From a small prayer gathering to a growing family, we are passionate about helping people live fully alive in Jesus and taking hope to every street.', ['tag' => 'p']); ?>
    </div>
</section>
<section class="content-section">
    <div class="container split">
        <div>
            <?= $cms->text('whoweare_headline', 'Who we are', ['tag' => 'h2']); ?>
            <?= $cms->html('whoweare_content', '<p>Alive Church began as a prayer meeting above a shop in Norwich. Today, we gather in our building, homes, and online. Everything we do flows from Jesus\' Great Commission and the Acts 2 church—devoted to teaching, fellowship, generosity, and prayer.</p>
            <p>We champion unity across generations, creativity that communicates the gospel, and audacious generosity for our cities. We believe in empowering the next generation of leaders through coaching, Alive Leadership College, and hands-on ministry.</p>', ['tag' => 'div']); ?>
            <?= $cms->image('whoweare_image', '/assets/imgs/gallery/alive-church-worship-congregation.jpg', 'Alive Church worship service', ['class' => 'about-image', 'style' => 'border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;']); ?>
        </div>
        <div class="card">
            <?= $cms->text('values_headline', 'Our values', ['tag' => 'h3']); ?>
            <?= $cms->html('values_content', '<ul class="info-list">
                <li><strong>Presence:</strong> Everything begins with worship.</li>
                <li><strong>People:</strong> Everyone is seen, known, and needed.</li>
                <li><strong>Purpose:</strong> We equip everyday saints to influence their world.</li>
                <li><strong>Play:</strong> Joy is our culture; laughter is welcome.</li>
            </ul>', ['tag' => 'div']); ?>
        </div>
    </div>
</section>
<section class="content-section alt">
    <div class="container">
        <div class="section-heading">
            <?= $cms->text('leadership_eyebrow', 'Leadership', ['tag' => 'p', 'class' => 'eyebrow']); ?>
            <?= $cms->text('leadership_headline', 'Meet some of the vision team.', ['tag' => 'h2']); ?>
        </div>
        <div class="card-grid profile-grid">
            <article class="profile-card">
                <?= $cms->image('leader1_image', '/assets/imgs/gallery/alive-church-congregation-hands-raised.jpg', 'Pastor speaking at Alive Church', ['style' => 'border-radius: 0.75rem; margin-bottom: 1rem; width: 100%; height: 200px; object-fit: cover;']); ?>
                <?= $cms->text('leader1_name', 'Pastors Phil & Jo Thorne', ['tag' => 'h3']); ?>
                <?= $cms->text('leader1_role', 'Senior Pastors', ['tag' => 'p']); ?>
                <?= $cms->text('leader1_bio', 'Phil started Alive Church over 40 years ago with a vision for community transformation in Norwich. Phil pastors the church with Jo, his wife. They have both shaped the church we see today.', ['tag' => 'p']); ?>
            </article>
            <article class="profile-card">
                <?= $cms->image('leader2_image', '/assets/imgs/gallery/alive-church-worship-leaders-performance.jpg', 'Worship leaders at Alive Church', ['style' => 'border-radius: 0.75rem; margin-bottom: 1rem; width: 100%; height: 200px; object-fit: cover;']); ?>
                <?= $cms->text('leader2_name', 'Pastor Jono Thorne', ['tag' => 'h3']); ?>
                <?= $cms->text('leader2_role', 'Worship & Creative Pastor', ['tag' => 'p']); ?>
                <?= $cms->text('leader2_bio', 'Jono oversees many aspects of church life. He is always doing something new and is responsible for Worship, Creative, Youth, Facilities management and Operations in the church.', ['tag' => 'p']); ?>
            </article>
            <article class="profile-card">
                <?= $cms->image('leader3_image', '/assets/imgs/gallery/alive-church-live-worship-band-lincolnshire.jpg', 'Youth worship at Alive Church', ['style' => 'border-radius: 0.75rem; margin-bottom: 1rem; width: 100%; height: 200px; object-fit: cover;']); ?>
                <?= $cms->text('leader3_name', 'Pastors Jon and Sara Plastow', ['tag' => 'h3']); ?>
                <?= $cms->text('leader3_role', 'Pastoral, Children & Compliance', ['tag' => 'p']); ?>
                <?= $cms->text('leader3_bio', 'Sara grew up in the church, and with her husband Jon, lead many aspects of church life. Sara can often be found preaching, whilst Jon plays drums. Together they lead Kids Church and Jon is trustee of Alive UK.', ['tag' => 'p']); ?>
            </article>
            <article class="profile-card">
                <?= $cms->image('leader4_image', '/assets/imgs/gallery/alive-church-live-worship-band-lincolnshire.jpg', 'Youth worship at Alive Church', ['style' => 'border-radius: 0.75rem; margin-bottom: 1rem; width: 100%; height: 200px; object-fit: cover;']); ?>
                <?= $cms->text('leader4_name', 'Pastors Abiodun & Ruth', ['tag' => 'h3']); ?>
                <?= $cms->text('leader4_role', 'Pastoral Care', ['tag' => 'p']); ?>
                <?= $cms->text('leader4_bio', 'Abiodun and Ruth bring warmth and wisdom to our pastoral care ministry, supporting families and individuals through life\'s seasons with prayer, counsel, and practical help.', ['tag' => 'p']); ?>
            </article>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
