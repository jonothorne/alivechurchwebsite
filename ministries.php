<?php
require __DIR__ . '/config.php';
$page_title = 'Ministries | ' . $site['name'];
include __DIR__ . '/includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('ministries');
}
?>
<section class="page-hero">
    <div class="container narrow">
        <p class="eyebrow light" data-cms-editable="hero_eyebrow" data-cms-page="ministries" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Ministries & Projects'); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="ministries" data-cms-type="text"><?= $cms->text('hero_headline', 'Find your people.'); ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="ministries" data-cms-type="text"><?= $cms->text('hero_subtext', 'From community outreach to gathering teams, there\'s a place for every gift. Browse a few highlights below and connect for more information.'); ?></p>
    </div>
</section>
<section class="content-section">
    <div class="container">
        <div class="card-grid">
            <?php foreach ($ministries as $item): ?>
                <article class="ministry-card">
                    <h3><?= $item['title']; ?></h3>
                    <p><?= $item['summary']; ?></p>
                    <a class="text-link" href="/connect">Join this team →</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section class="content-section alt">
    <div class="container split">
        <div>
            <h2 data-cms-editable="groups_headline" data-cms-page="ministries" data-cms-type="text"><?= $cms->text('groups_headline', 'Alive Groups'); ?></h2>
            <p data-cms-editable="groups_text" data-cms-page="ministries" data-cms-type="text"><?= $cms->text('groups_text', 'Groups meet all across Norwich and online—Alpha, prayer, running clubs, creative collectives, and more. We will help you find a circle that fits your rhythm.'); ?></p>
            <a class="btn btn-primary" href="/next-steps">Find a group</a>
            <img src="/assets/imgs/gallery/alive-church-community-craft-activity.jpg" alt="Alive Church community group activity" style="border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;">
        </div>
        <div class="card">
            <img src="/assets/imgs/gallery/alive-church-community-cafe-outdoor.jpg" alt="Alive Church serve Saturday outdoor event" style="border-radius: 0.75rem; margin-bottom: 1rem; width: 100%; height: 200px; object-fit: cover;" data-cms-editable="serve_image" data-cms-page="ministries" data-cms-type="image">
            <h3 data-cms-editable="serve_title" data-cms-page="ministries" data-cms-type="text"><?= $cms->text('serve_title', 'Serve Saturday'); ?></h3>
            <p data-cms-editable="serve_text" data-cms-page="ministries" data-cms-type="text"><?= $cms->text('serve_text', 'Once a month we mobilize hundreds of volunteers for neighborhood makeovers, litter picks, home makeovers, and care packages.'); ?></p>
            <a class="text-link" href="/connect">Sign up to serve →</a>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
