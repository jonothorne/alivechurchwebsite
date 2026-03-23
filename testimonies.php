<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';

// Check if testimonies are enabled
$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'testimonies_enabled'");
$stmt->execute();
$testimoniesEnabled = $stmt->fetchColumn();

// If not enabled and not admin, redirect to home
if (!$testimoniesEnabled && !isset($_SESSION['admin_user_id'])) {
    header('Location: /');
    exit;
}

$page_title = 'Stories | ' . $site['name'];
$page_description = 'Real stories of life change from Alive Church Norwich. Discover how God is transforming lives in our Norwich church family.';

// Get filter
$filter = $_GET['type'] ?? 'all';
$validFilters = ['all', 'salvation', 'transformation', 'healing', 'serve'];
if (!in_array($filter, $validFilters)) {
    $filter = 'all';
}

// Fetch testimonies
$sql = "SELECT * FROM testimonies WHERE is_published = 1";
if ($filter !== 'all') {
    $sql .= " AND testimony_type = :type";
}
$sql .= " ORDER BY is_featured DESC, display_order ASC, created_at DESC";

$stmt = $pdo->prepare($sql);
if ($filter !== 'all') {
    $stmt->execute(['type' => $filter]);
} else {
    $stmt->execute();
}
$testimonies = $stmt->fetchAll();

// Get featured testimony for hero
$featuredStmt = $pdo->query("SELECT * FROM testimonies WHERE is_published = 1 AND is_featured = 1 ORDER BY display_order ASC LIMIT 1");
$featured = $featuredStmt->fetch();

include __DIR__ . '/includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('testimonies');
}
?>

<?php if (!$testimoniesEnabled): ?>
<div class="admin-preview-banner">
    <div class="container">
        <strong>Admin Preview Mode</strong> - This page is hidden from visitors. Enable it in <a href="/admin/settings">Settings</a>.
    </div>
</div>
<?php endif; ?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow" data-cms-editable="hero_eyebrow" data-cms-page="testimonies" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Real Stories'); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="testimonies" data-cms-type="text"><?= $cms->text('hero_headline', 'Lives Changed'); ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="testimonies" data-cms-type="text"><?= $cms->text('hero_subtext', 'These are real stories from real people in our church family. God is still in the business of changing lives.'); ?></p>
    </div>
</section>

<?php if ($featured): ?>
<!-- Featured Story -->
<section class="featured-testimony">
    <div class="container">
        <div class="featured-testimony-card">
            <?php if ($featured['video_url']): ?>
            <div class="featured-testimony-video">
                <?php
                // Extract YouTube ID
                preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $featured['video_url'], $matches);
                $youtubeId = $matches[1] ?? '';
                if ($youtubeId):
                ?>
                <div class="video-wrapper">
                    <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($youtubeId); ?>"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen></iframe>
                </div>
                <?php endif; ?>
            </div>
            <?php elseif ($featured['person_image']): ?>
            <div class="featured-testimony-image">
                <img src="<?= htmlspecialchars($featured['person_image']); ?>" alt="<?= htmlspecialchars($featured['person_name']); ?>">
            </div>
            <?php endif; ?>
            <div class="featured-testimony-content">
                <span class="testimony-type-badge"><?= ucfirst($featured['testimony_type']); ?> Story</span>
                <h2><?= htmlspecialchars($featured['title']); ?></h2>
                <?php if ($featured['short_quote']): ?>
                <blockquote>"<?= htmlspecialchars($featured['short_quote']); ?>"</blockquote>
                <?php endif; ?>
                <div class="testimony-author">
                    <strong><?= htmlspecialchars($featured['person_name']); ?></strong>
                    <?php if ($featured['person_role']): ?>
                    <span><?= htmlspecialchars($featured['person_role']); ?></span>
                    <?php endif; ?>
                </div>
                <a href="#story-<?= $featured['id']; ?>" class="btn btn-primary">Read Full Story</a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Filter Tabs -->
<section class="testimonies-filter">
    <div class="container">
        <div class="filter-tabs">
            <a href="/testimonies" class="filter-tab <?= $filter === 'all' ? 'active' : ''; ?>">All Stories</a>
            <a href="/testimonies?type=salvation" class="filter-tab <?= $filter === 'salvation' ? 'active' : ''; ?>">Salvation</a>
            <a href="/testimonies?type=transformation" class="filter-tab <?= $filter === 'transformation' ? 'active' : ''; ?>">Life Change</a>
            <a href="/testimonies?type=healing" class="filter-tab <?= $filter === 'healing' ? 'active' : ''; ?>">Healing</a>
            <a href="/testimonies?type=serve" class="filter-tab <?= $filter === 'serve' ? 'active' : ''; ?>">Why I Serve</a>
        </div>
    </div>
</section>

<!-- Testimonies Grid -->
<section class="testimonies-section">
    <div class="container">
        <?php if (empty($testimonies)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📖</div>
            <h3>Stories Coming Soon</h3>
            <p>We're collecting stories from our church family. Check back soon to hear how God is working!</p>
            <?php if (isset($_SESSION['admin_user_id'])): ?>
            <a href="/admin/testimonies" class="btn btn-primary">Add Testimonies</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="testimonies-grid">
            <?php foreach ($testimonies as $testimony): ?>
            <article class="testimony-card" id="story-<?= $testimony['id']; ?>">
                <?php if ($testimony['person_image']): ?>
                <div class="testimony-card-image">
                    <img src="<?= htmlspecialchars($testimony['person_image']); ?>" alt="<?= htmlspecialchars($testimony['person_name']); ?>">
                    <?php if ($testimony['video_url']): ?>
                    <span class="video-indicator">▶</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="testimony-card-content">
                    <span class="testimony-type-badge small"><?= ucfirst($testimony['testimony_type']); ?></span>
                    <h3><?= htmlspecialchars($testimony['title']); ?></h3>
                    <?php if ($testimony['short_quote']): ?>
                    <p class="testimony-quote">"<?= htmlspecialchars($testimony['short_quote']); ?>"</p>
                    <?php else: ?>
                    <p class="testimony-excerpt"><?= htmlspecialchars(substr(strip_tags($testimony['full_story']), 0, 150)); ?>...</p>
                    <?php endif; ?>
                    <div class="testimony-author">
                        <strong><?= htmlspecialchars($testimony['person_name']); ?></strong>
                        <?php if ($testimony['person_role']): ?>
                        <span><?= htmlspecialchars($testimony['person_role']); ?></span>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-outline btn-sm read-story-btn"
                            data-story-id="<?= $testimony['id']; ?>"
                            data-title="<?= htmlspecialchars($testimony['title']); ?>"
                            data-name="<?= htmlspecialchars($testimony['person_name']); ?>"
                            data-role="<?= htmlspecialchars($testimony['person_role'] ?? ''); ?>"
                            data-type="<?= $testimony['testimony_type']; ?>"
                            data-story="<?= htmlspecialchars($testimony['full_story']); ?>"
                            data-video="<?= htmlspecialchars($testimony['video_url'] ?? ''); ?>"
                            data-image="<?= htmlspecialchars($testimony['person_image'] ?? ''); ?>">
                        Read Story
                    </button>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Share Your Story CTA -->
<section class="share-story-cta">
    <div class="container narrow">
        <div class="cta-card">
            <h2 data-cms-editable="cta_headline" data-cms-page="testimonies" data-cms-type="text"><?= $cms->text('cta_headline', 'Has God changed your life?'); ?></h2>
            <p data-cms-editable="cta_text" data-cms-page="testimonies" data-cms-type="text"><?= $cms->text('cta_text', 'We\'d love to hear your story. Your testimony could encourage someone who\'s right where you used to be.'); ?></p>
            <a href="/contact-us?subject=Share%20My%20Story" class="btn btn-primary">Share Your Story</a>
        </div>
    </div>
</section>

<!-- Story Modal -->
<div class="modal testimony-modal" id="story-modal">
    <div class="modal-backdrop"></div>
    <div class="modal-content">
        <button class="modal-close" aria-label="Close">&times;</button>
        <div class="modal-body">
            <div class="testimony-modal-video" id="modal-video"></div>
            <span class="testimony-type-badge" id="modal-type"></span>
            <h2 id="modal-title"></h2>
            <div class="testimony-author" id="modal-author"></div>
            <div class="testimony-full-story" id="modal-story"></div>
            <div class="testimony-share">
                <span>Share this story:</span>
                <button class="share-btn" data-platform="facebook" aria-label="Share on Facebook">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </button>
                <button class="share-btn" data-platform="twitter" aria-label="Share on X">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </button>
                <button class="share-btn" data-platform="copy" aria-label="Copy link">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                </button>
            </div>
        </div>
    </div>
</div>

<script <?= csp_nonce(); ?>>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('story-modal');
    const modalBackdrop = modal.querySelector('.modal-backdrop');
    const modalClose = modal.querySelector('.modal-close');

    // Open modal when clicking "Read Story"
    document.querySelectorAll('.read-story-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = this.dataset;

            document.getElementById('modal-title').textContent = data.title;
            document.getElementById('modal-type').textContent = data.type.charAt(0).toUpperCase() + data.type.slice(1) + ' Story';
            document.getElementById('modal-story').innerHTML = data.story.replace(/\n/g, '<br>');

            let authorHtml = '<strong>' + data.name + '</strong>';
            if (data.role) {
                authorHtml += '<span>' + data.role + '</span>';
            }
            document.getElementById('modal-author').innerHTML = authorHtml;

            // Handle video
            const videoContainer = document.getElementById('modal-video');
            if (data.video) {
                const youtubeMatch = data.video.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/);
                if (youtubeMatch) {
                    videoContainer.innerHTML = '<div class="video-wrapper"><iframe src="https://www.youtube.com/embed/' + youtubeMatch[1] + '" frameborder="0" allowfullscreen></iframe></div>';
                    videoContainer.style.display = 'block';
                }
            } else if (data.image) {
                videoContainer.innerHTML = '<img src="' + data.image + '" alt="' + data.name + '">';
                videoContainer.style.display = 'block';
            } else {
                videoContainer.style.display = 'none';
            }

            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });

    // Close modal
    function closeModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        // Stop video if playing
        document.getElementById('modal-video').innerHTML = '';
    }

    modalClose.addEventListener('click', closeModal);
    modalBackdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    });

    // Share buttons
    document.querySelectorAll('.share-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const platform = this.dataset.platform;
            const title = document.getElementById('modal-title').textContent;
            const url = window.location.href;

            switch(platform) {
                case 'facebook':
                    window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url), '_blank', 'width=600,height=400');
                    break;
                case 'twitter':
                    window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent(title + ' - ' + url), '_blank', 'width=600,height=400');
                    break;
                case 'copy':
                    navigator.clipboard.writeText(url).then(() => {
                        this.innerHTML = '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
                        setTimeout(() => {
                            this.innerHTML = '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
                        }, 2000);
                    });
                    break;
            }
        });
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
