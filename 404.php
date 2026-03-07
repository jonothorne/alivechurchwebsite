<?php
require __DIR__ . '/config.php';
$page_title = 'Page Not Found | ' . $site['name'];
http_response_code(404);
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow center-text">
        <p class="eyebrow light">Error 404</p>
        <h1>Page not found</h1>
        <p>Looks like this page doesn't exist or has moved. Let's get you back on track.</p>
    </div>
</section>

<section class="content-section">
    <div class="container narrow center-text">
        <h2>Where would you like to go?</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
            <a href="/" class="card" style="text-decoration: none; padding: 2rem;">
                <h3>🏠 Home</h3>
                <p>Start from the beginning</p>
            </a>
            <a href="/visit" class="card" style="text-decoration: none; padding: 2rem;">
                <h3>👋 Plan a Visit</h3>
                <p>Join us this Sunday</p>
            </a>
            <a href="/watch" class="card" style="text-decoration: none; padding: 2rem;">
                <h3>📺 Watch</h3>
                <p>Latest messages & sermons</p>
            </a>
            <a href="/events" class="card" style="text-decoration: none; padding: 2rem;">
                <h3>📅 Events</h3>
                <p>See what's happening</p>
            </a>
            <a href="/connect" class="card" style="text-decoration: none; padding: 2rem;">
                <h3>🤝 Connect</h3>
                <p>Join a group or serve team</p>
            </a>
            <a href="/give" class="card" style="text-decoration: none; padding: 2rem;">
                <h3>💝 Give</h3>
                <p>Make an impact</p>
            </a>
        </div>

        <div style="margin-top: 3rem;">
            <p>Still can't find what you're looking for?</p>
            <a href="mailto:<?= htmlspecialchars($site['email']); ?>" class="btn btn-primary">
                Contact Us
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
