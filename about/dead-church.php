<?php
/**
 * Dead Church? Page - A playful page about what we're NOT
 */
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/hero-textures.php';

$page_title = 'Dead Church? | ' . $site['name'];
$page_description = 'Looking for a dead church? Sorry, you\'ve come to the wrong place. Alive Church is anything but boring.';
$hero_texture_class = get_random_texture();

// Initialize CMS
require_once __DIR__ . '/../includes/cms/ContentManager.php';
$cms = new ContentManager('about-dead-church');

include __DIR__ . '/../includes/header.php';
?>

<section class="page-hero page-hero-image" style="background-image: url('/assets/imgs/gallery/dead-church.png');">
    <div class="container narrow">
        <?= $cms->text('hero_eyebrow', 'Wrong Place', ['tag' => 'p', 'class' => 'eyebrow']); ?>
        <?= $cms->text('hero_headline', 'Dead Church?', ['tag' => 'h1']); ?>
        <?= $cms->text('hero_subtext', 'Sorry, you\'ve come to the wrong place.', ['tag' => 'p']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->html('intro_content', '<p class="lead">If you\'re looking for a dead church – dry, boring, and stuck in the past – we\'re not it. Not even close.</p>
        <p>We\'re called <strong>Alive Church</strong> for a reason. We believe church should be full of life, energy, and joy – because that\'s what happens when people encounter the living God.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow">
        <?= $cms->text('not_headline', 'What We\'re Not', ['tag' => 'h2']); ?>
        <?= $cms->html('not_content', '<ul class="info-list">
            <li><strong>We\'re not boring.</strong> Our services are engaging, creative, and designed to connect with real life. You won\'t be checking your watch.</li>
            <li><strong>We\'re not stuffy.</strong> Come as you are. Jeans are fine. Coffee is encouraged. Kids are welcome (and often loud).</li>
            <li><strong>We\'re not stuck in the past.</strong> We honour our history, but we\'re always looking forward. Fresh music, relevant teaching, and a willingness to try new things.</li>
            <li><strong>We\'re not exclusive.</strong> Whether you\'ve been in church your whole life or you\'re just curious – there\'s a place for you here.</li>
            <li><strong>We\'re not quiet.</strong> We laugh, we clap, we sing loudly (some of us in tune). Joy is part of our DNA.</li>
        </ul>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('are_headline', 'What We Are', ['tag' => 'h2']); ?>
        <?= $cms->html('are_content', '<ul class="info-list">
            <li><strong>Alive.</strong> We believe in a God who is active, present, and still doing incredible things today.</li>
            <li><strong>Welcoming.</strong> First-timers regularly tell us they felt at home from the moment they walked in.</li>
            <li><strong>Real.</strong> We don\'t pretend to have it all together. We\'re a community of imperfect people finding hope in Jesus.</li>
            <li><strong>Passionate.</strong> About worship, about community, about making a difference in our city.</li>
            <li><strong>Fun.</strong> Yes, church can be fun. We take God seriously, but we don\'t take ourselves too seriously.</li>
        </ul>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow">
        <?= $cms->text('expect_headline', 'What To Expect', ['tag' => 'h2']); ?>
        <?= $cms->html('expect_content', '<p>When you visit Alive Church, expect to be greeted with a genuine smile (and probably offered a coffee). Expect music that stirs something in your soul. Expect teaching that\'s biblical but also practical and applicable to everyday life.</p>
        <p>Expect to see people of all ages – from babies to grandparents – worshipping together. Expect laughter. Expect to leave feeling like you encountered something real.</p>
        <p>And if you\'re still not sure? That\'s okay. We\'d rather you come and experience it for yourself than take our word for it.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section">
    <div class="container narrow">
        <?= $cms->text('still_headline', 'Still Looking for Dead Church?', ['tag' => 'h2']); ?>
        <?= $cms->html('still_content', '<p>We can\'t help you there. But if you\'re looking for a community where faith comes alive, where you can be yourself, and where you might just encounter the God who changes everything – you\'ve found the right place.</p>
        <p><strong>We\'re Alive Church.</strong> And we\'d love to meet you.</p>', ['tag' => 'div', 'class' => 'prose']); ?>
    </div>
</section>

<section class="content-section cta-section alt">
    <div class="container narrow text-center">
        <?= $cms->text('cta_headline', 'Ready to Experience Alive?', ['tag' => 'h2']); ?>
        <?= $cms->text('cta_text', 'Join us this Sunday and see for yourself. We promise it won\'t be boring.', ['tag' => 'p']); ?>
        <div class="cta-buttons">
            <a href="/visit" class="btn btn-primary">Plan Your Visit</a>
            <a href="/watch" class="btn btn-secondary">Watch Online First</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
