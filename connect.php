<?php
require __DIR__ . '/config.php';
$page_title = 'Connect | ' . $site['name'];
include __DIR__ . '/includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('connect');
}
?>

<section class="page-hero">
    <div class="container narrow">
        <p class="eyebrow" data-cms-editable="hero_eyebrow" data-cms-page="connect" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Connect'); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="connect" data-cms-type="text"><?= $cms->text('hero_headline', 'Find your people. Use your gifts.'); ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="connect" data-cms-type="text"><?= $cms->text('hero_subtext', 'From groups to serve teams, there\'s a place for everyone to belong, grow, and make a difference.'); ?></p>
    </div>
</section>

<section class="connect-section">
    <div class="container">
        <!-- Tab Navigation -->
        <div class="tab-nav">
            <button class="tab-btn active" data-tab="groups">Groups</button>
            <button class="tab-btn" data-tab="ministries">Ministries</button>
            <button class="tab-btn" data-tab="serve">Serve</button>
            <button class="tab-btn" data-tab="next-steps">Next Steps</button>
        </div>

        <!-- Tab Content: Groups -->
        <div class="tab-content active" id="groups">
            <div class="section-intro">
                <h2 data-cms-editable="groups_headline" data-cms-page="connect" data-cms-type="text"><?= $cms->text('groups_headline', 'Join a Group'); ?></h2>
                <p data-cms-editable="groups_subtext" data-cms-page="connect" data-cms-type="text"><?= $cms->text('groups_subtext', 'Life change happens in circles, not rows. Find a group that fits your season, interests, and schedule.'); ?></p>
            </div>

            <div class="card-grid">
                <?php foreach ($groups as $group): ?>
                    <?php
                    $group_slug = strtolower(str_replace(' ', '-', $group['title']));
                    ?>
                    <article class="connect-card">
                        <img src="<?= htmlspecialchars($group['image']); ?>"
                             alt="<?= htmlspecialchars($group['title']); ?>">
                        <div>
                            <h3><?= htmlspecialchars($group['title']); ?></h3>
                            <p><?= htmlspecialchars($group['description']); ?></p>
                            <div class="card-meta">
                                <span>📅 <?= htmlspecialchars($group['schedule']); ?></span>
                                <span>📍 <?= htmlspecialchars($group['location']); ?></span>
                            </div>
                            <a class="btn btn-outline" href="/groups/join?group=<?= urlencode($group_slug); ?>">
                                Join Group
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tab Content: Ministries -->
        <div class="tab-content" id="ministries">
            <div class="section-intro">
                <h2 data-cms-editable="ministries_headline" data-cms-page="connect" data-cms-type="text"><?= $cms->text('ministries_headline', 'Our Ministries'); ?></h2>
                <p data-cms-editable="ministries_subtext" data-cms-page="connect" data-cms-type="text"><?= $cms->text('ministries_subtext', 'Discover how we\'re serving our community and making a difference across Norwich.'); ?></p>
            </div>

            <div class="card-grid">
                <?php foreach ($ministries as $ministry): ?>
                    <article class="ministry-card">
                        <h3><?= htmlspecialchars($ministry['title']); ?></h3>
                        <p><?= htmlspecialchars($ministry['summary']); ?></p>
                        <a class="text-link" href="/about">Learn more →</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tab Content: Serve -->
        <div class="tab-content" id="serve">
            <div class="section-intro">
                <h2 data-cms-editable="serve_headline" data-cms-page="connect" data-cms-type="text"><?= $cms->text('serve_headline', 'Serve With Us'); ?></h2>
                <p data-cms-editable="serve_subtext" data-cms-page="connect" data-cms-type="text"><?= $cms->text('serve_subtext', 'Use your gifts to make Sunday the best day of the week for others. Every role matters, and there\'s a place for you.'); ?></p>
            </div>

            <div class="card-grid">
                <?php foreach ($serve_opportunities as $opportunity): ?>
                    <article class="serve-card">
                        <?php if (isset($opportunity['image'])): ?>
                            <img src="<?= htmlspecialchars($opportunity['image']); ?>"
                                 alt="<?= htmlspecialchars($opportunity['title']); ?>"
                                 style="width: 100%; height: 180px; object-fit: cover; border-radius: 0.75rem; margin-bottom: 1rem;">
                        <?php endif; ?>
                        <h3><?= htmlspecialchars($opportunity['title']); ?></h3>
                        <span class="commitment"><?= htmlspecialchars($opportunity['commitment']); ?></span>
                        <p><?= htmlspecialchars($opportunity['description']); ?></p>
                        <div class="areas">
                            <?php foreach ($opportunity['areas'] as $area): ?>
                                <span class="area-tag"><?= htmlspecialchars($area); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php
                        $team_slug = strtolower(str_replace(' ', '-', $opportunity['title']));
                        ?>
                        <a class="btn btn-primary" href="/serve/apply?team=<?= urlencode($team_slug); ?>">Apply to Serve</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tab Content: Next Steps -->
        <div class="tab-content" id="next-steps">
            <div class="section-intro">
                <h2 data-cms-editable="nextsteps_headline" data-cms-page="connect" data-cms-type="text"><?= $cms->text('nextsteps_headline', 'Your Next Steps'); ?></h2>
                <p data-cms-editable="nextsteps_subtext" data-cms-page="connect" data-cms-type="text"><?= $cms->text('nextsteps_subtext', 'Let\'s walk together on your faith journey. Pick a next step and our team will reach out with details.'); ?></p>
            </div>

            <div class="card-grid">
                <?php foreach ($next_steps as $step): ?>
                    <article class="step-card">
                        <h3><?= htmlspecialchars($step['title']); ?></h3>
                        <p><?= htmlspecialchars($step['copy']); ?></p>
                        <a class="btn btn-outline" href="<?= htmlspecialchars($step['link']); ?>">
                            Start Now
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/newsletter.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
