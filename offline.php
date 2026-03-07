<?php
require __DIR__ . '/config.php';
$page_title = 'Offline | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <h1>You're Offline</h1>
        <p>It looks like you've lost your internet connection.</p>
    </div>
</section>

<section class="content-section">
    <div class="container narrow center-text">
        <div class="offline-content">
            <div class="offline-icon">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: var(--color-text-muted);">
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                    <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"></path>
                    <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"></path>
                    <path d="M10.71 5.05A16 16 0 0 1 22.58 9"></path>
                    <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"></path>
                    <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                    <line x1="12" y1="20" x2="12.01" y2="20"></line>
                </svg>
            </div>

            <h2>Don't worry!</h2>
            <p>Pages you've visited before may still be available. Try navigating to content you've previously read.</p>

            <div class="offline-suggestions">
                <h3>Try these:</h3>
                <div class="offline-links">
                    <a href="/" class="btn btn-outline">Home</a>
                    <a href="/bible-study" class="btn btn-outline">Bible Study</a>
                    <a href="/reading-plans" class="btn btn-outline">Reading Plans</a>
                </div>
            </div>

            <p class="offline-note">Your reading progress will sync automatically when you're back online.</p>
        </div>
    </div>
</section>

<style>
.offline-content {
    padding: 2rem 0;
}

.offline-icon {
    margin-bottom: 1.5rem;
}

.offline-content h2 {
    margin-bottom: 0.5rem;
}

.offline-content > p {
    color: var(--color-text-muted);
    margin-bottom: 2rem;
}

.offline-suggestions {
    background: var(--color-grey);
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 2rem;
}

.offline-suggestions h3 {
    font-size: 1rem;
    margin-bottom: 1rem;
}

.offline-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: center;
}

.offline-note {
    font-size: 0.875rem;
    color: var(--color-text-muted);
    font-style: italic;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
