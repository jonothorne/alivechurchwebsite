<?php
/**
 * Team/Staff Template
 *
 * Grid layout for team member profiles.
 * Variables available: $cms, $pageData, $heroStyle
 */

require __DIR__ . '/../config.php';
$page_title = $cms->text('page_title', $pageData['title'] ?? 'Our Team') . ' | ' . $site['name'];
include __DIR__ . '/../includes/header.php';
?>

<?php if ($heroStyle !== 'none'): ?>
<section class="page-hero <?= 'hero-' . htmlspecialchars($heroStyle); ?>">
    <div class="container">
        <h1 data-cms-editable="hero_heading" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="text">
            <?= $cms->text('hero_heading', 'Our Team'); ?>
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
        <div class="team-intro" data-cms-editable="team_intro" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('team_intro', '<p class="intro-text">Meet the people who make it all happen.</p>'); ?>
        </div>

        <div class="team-grid" data-cms-editable="team_members" data-cms-page="<?= $cms->getPageSlug(); ?>" data-cms-type="html">
            <?= $cms->html('team_members', '
            <div class="team-member">
                <div class="team-member-photo">
                    <img src="/assets/imgs/placeholder-person.jpg" alt="Team member">
                </div>
                <h3>Team Member Name</h3>
                <p class="team-member-role">Role / Title</p>
                <p class="team-member-bio">A brief bio about this team member...</p>
            </div>
            <div class="team-member">
                <div class="team-member-photo">
                    <img src="/assets/imgs/placeholder-person.jpg" alt="Team member">
                </div>
                <h3>Team Member Name</h3>
                <p class="team-member-role">Role / Title</p>
                <p class="team-member-bio">A brief bio about this team member...</p>
            </div>
            <div class="team-member">
                <div class="team-member-photo">
                    <img src="/assets/imgs/placeholder-person.jpg" alt="Team member">
                </div>
                <h3>Team Member Name</h3>
                <p class="team-member-role">Role / Title</p>
                <p class="team-member-bio">A brief bio about this team member...</p>
            </div>
            '); ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
