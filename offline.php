<?php
require __DIR__ . '/config.php';
$page_title = 'Offline | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow center-text">
        <p class="eyebrow light">No Connection</p>
        <h1>You're Offline</h1>
        <p>It looks like you've lost your internet connection. Don't worry - pages you've visited before may still be available.</p>
    </div>
</section>

<section class="content-section">
    <div class="container narrow center-text">
        <h2>Try these cached pages</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
            <a href="/" class="card" style="text-decoration: none; padding: 2rem;">
                <h3>📖 Home</h3>
                <p>Return to the homepage</p>
            </a>
            <a href="/bible-study" class="card" style="text-decoration: none; padding: 2rem;">
                <h3>📚 Bible Study</h3>
                <p>Continue reading God's Word</p>
            </a>
            <a href="/reading-plans" class="card" style="text-decoration: none; padding: 2rem;">
                <h3>📅 Reading Plans</h3>
                <p>Keep up with your plan</p>
            </a>
            <a href="/my-studies" class="card" style="text-decoration: none; padding: 2rem;">
                <h3>🔖 My Studies</h3>
                <p>Your saved content</p>
            </a>
        </div>

        <div style="margin-top: 3rem; padding: 1.5rem; background: var(--color-bg-subtle); border-radius: var(--radius-lg);">
            <p style="margin: 0; color: var(--color-text-muted);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.5rem;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                Your reading progress will sync automatically when you're back online.
            </p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
