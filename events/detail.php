<?php
/**
 * Event Detail Page
 * Shows full details for a single event, combining Planning Center data with local CMS content
 */
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-config.php';

// Get event slug from URL
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: /events');
    exit;
}

// Find event in Planning Center data
$event = null;
foreach ($all_events as $e) {
    if ($e['slug'] === $slug) {
        $event = $e;
        break;
    }
}

// Get local event details from database
$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT * FROM event_details WHERE slug = ?");
$stmt->execute([$slug]);
$localDetails = $stmt->fetch(PDO::FETCH_ASSOC);

// If no event found in either source, 404
if (!$event && !$localDetails) {
    header('HTTP/1.0 404 Not Found');
    $page_title = 'Event Not Found | ' . $site['name'];
    include __DIR__ . '/../includes/header.php';
    ?>
    <section class="page-hero <?= $hero_texture_class; ?>">
        <div class="container narrow">
            <h1>Event Not Found</h1>
            <p>Sorry, we couldn't find that event. It may have ended or been removed.</p>
            <a href="/events" class="btn btn-primary">View All Events</a>
        </div>
    </section>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Merge data - local details override Planning Center data
$eventData = [
    'title' => $localDetails['title'] ?? $event['title'] ?? 'Event',
    'subtitle' => $localDetails['subtitle'] ?? '',
    'description' => $localDetails['description'] ?? $event['description'] ?? '',
    'full_description' => $localDetails['full_description'] ?? '',
    'image' => $localDetails['image'] ?? $event['image'] ?? '/assets/imgs/gallery/alive-church-worship-congregation.jpg',
    'date' => $event['date'] ?? '',
    'time' => $event['time'] ?? '',
    'location' => $localDetails['custom_location'] ?? $event['location'] ?? 'Alive House, Norwich',
    'is_recurring' => $event['is_recurring'] ?? false,
    'frequency' => $event['frequency'] ?? '',
    'who_is_it_for' => $localDetails['who_is_it_for'] ?? '',
    'what_to_expect' => $localDetails['what_to_expect'] ?? '',
    'what_to_bring' => $localDetails['what_to_bring'] ?? '',
    'cost' => $localDetails['cost'] ?? $event['cost'] ?? 'Free',
    'registration_url' => $localDetails['registration_url'] ?? $event['registration_url'] ?? '',
    'registration_required' => !empty($localDetails['registration_url']) || ($event['registration_required'] ?? false),
    'contact_email' => $localDetails['contact_email'] ?? $site['email'],
    'contact_phone' => $localDetails['contact_phone'] ?? $site['phone'],
    'category' => $event['category'] ?? 'weekly',
    'next_dates' => [],
];

// Get next few dates for recurring events
if ($eventData['is_recurring'] && !empty($event['occurrences'])) {
    $now = time();
    $count = 0;
    foreach ($event['occurrences'] as $occurrence) {
        $occTime = strtotime($occurrence['start_datetime']);
        if ($occTime >= $now && $count < 5) {
            $eventData['next_dates'][] = [
                'date' => date('l j F', $occTime),
                'time' => date('g:iA', $occTime),
            ];
            $count++;
        }
    }
}

$page_title = $eventData['title'] . ' | Events | ' . $site['name'];
$page_description = $eventData['description'] ? substr(strip_tags($eventData['description']), 0, 155) : 'Join us for ' . $eventData['title'] . ' at ' . $site['name'];

// Share URLs
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$shareTitle = urlencode($eventData['title'] . ' - ' . $site['name']);
$shareUrl = urlencode($currentUrl);

// Social Media Meta Tags
$og_type = 'event';
$og_url = $currentUrl;
$og_title = $eventData['title'] . ' | ' . $site['name'];
$og_description = $page_description;
$og_image = $eventData['image'];

// Event Schema Markup (JSON-LD)
$eventSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => $eventData['title'],
    'description' => $eventData['description'],
    'url' => $currentUrl,
    'image' => $eventData['image'],
    'location' => [
        '@type' => 'Place',
        'name' => $eventData['location'],
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'Nelson Street',
            'addressLocality' => 'Norwich',
            'addressRegion' => 'Norfolk',
            'postalCode' => 'NR2 4DR',
            'addressCountry' => 'GB'
        ]
    ],
    'organizer' => [
        '@type' => 'Organization',
        'name' => $site['name'],
        'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
    ],
    'offers' => [
        '@type' => 'Offer',
        'price' => '0',
        'priceCurrency' => 'GBP',
        'availability' => 'https://schema.org/InStock',
        'url' => $eventData['registration_url'] ?: $currentUrl
    ],
    'eventStatus' => 'https://schema.org/EventScheduled',
    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode'
];

// Add date/time if available
if (!empty($event['start_datetime'])) {
    $eventSchema['startDate'] = date('c', strtotime($event['start_datetime']));
    if (!empty($event['end_datetime'])) {
        $eventSchema['endDate'] = date('c', strtotime($event['end_datetime']));
    }
} elseif (!empty($eventData['next_dates'][0])) {
    // Use first upcoming date for recurring events
    $eventSchema['startDate'] = date('c');
}

include __DIR__ . '/../includes/header.php';

// Output Event Schema
echo '<script type="application/ld+json">' . json_encode($eventSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/../includes/cms/ContentManager.php';
    $cms = new ContentManager('event-' . $slug);
}
?>

<?php if (!empty($eventData['image'])): ?>
<style <?= csp_nonce(); ?>>.event-hero { background-image: linear-gradient(rgba(30, 26, 43, 0.7), rgba(30, 26, 43, 0.9)), url('<?= htmlspecialchars($eventData['image']); ?>'); background-size: cover; background-position: center; }</style>
<?php endif; ?>

<section class="event-hero">
    <div class="container">
        <div class="event-hero-content">
            <div class="event-meta">
                <span class="event-category-badge"><?= htmlspecialchars(ucfirst($eventData['category'])); ?></span>
                <?php if ($eventData['is_recurring']): ?>
                    <span class="event-recurring-badge"><?= htmlspecialchars($eventData['frequency']); ?></span>
                <?php endif; ?>
            </div>
            <h1><?= htmlspecialchars($eventData['title']); ?></h1>
            <?php if ($eventData['subtitle']): ?>
                <p class="event-subtitle"><?= htmlspecialchars($eventData['subtitle']); ?></p>
            <?php endif; ?>
            <p class="event-short-desc"><?= htmlspecialchars($eventData['description']); ?></p>
        </div>
    </div>
</section>

<section class="event-detail-section">
    <div class="container">
        <div class="event-layout">
            <!-- Main Content -->
            <div class="event-main">
                <?php if ($eventData['full_description']): ?>
                    <div class="event-section">
                        <h2>About This Event</h2>
                        <div class="event-description">
                            <?= $eventData['full_description']; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($eventData['what_to_expect']): ?>
                    <div class="event-section">
                        <h2>What to Expect</h2>
                        <div class="event-list">
                            <?= $eventData['what_to_expect']; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($eventData['who_is_it_for']): ?>
                    <div class="event-section">
                        <h2>Who Is It For?</h2>
                        <div class="event-list">
                            <?= $eventData['who_is_it_for']; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($eventData['what_to_bring']): ?>
                    <div class="event-section">
                        <h2>What to Bring</h2>
                        <div class="event-list">
                            <?= $eventData['what_to_bring']; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <aside class="event-sidebar">
                <div class="event-info-card">
                    <h3>Event Details</h3>

                    <div class="event-info-item">
                        <span class="event-info-icon">📅</span>
                        <div>
                            <strong>When</strong>
                            <?php if ($eventData['is_recurring']): ?>
                                <p><?= htmlspecialchars($eventData['frequency']); ?></p>
                                <p><?= htmlspecialchars($eventData['time']); ?></p>
                            <?php else: ?>
                                <p><?= htmlspecialchars($eventData['date']); ?></p>
                                <p><?= htmlspecialchars($eventData['time']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="event-info-item">
                        <span class="event-info-icon">📍</span>
                        <div>
                            <strong>Where</strong>
                            <p><?= htmlspecialchars($eventData['location']); ?></p>
                            <a href="<?= htmlspecialchars($site['maps_url']); ?>" target="_blank" class="text-link">Get Directions →</a>
                        </div>
                    </div>

                    <div class="event-info-item">
                        <span class="event-info-icon">💷</span>
                        <div>
                            <strong>Cost</strong>
                            <p><?= htmlspecialchars($eventData['cost']); ?></p>
                        </div>
                    </div>

                    <?php if ($eventData['registration_required'] && $eventData['registration_url']): ?>
                        <a href="<?= htmlspecialchars($eventData['registration_url']); ?>" class="btn btn-primary btn-block" target="_blank">
                            Register Now
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (!empty($eventData['next_dates'])): ?>
                    <div class="event-info-card">
                        <h3>Upcoming Dates</h3>
                        <ul class="upcoming-dates-list">
                            <?php foreach ($eventData['next_dates'] as $nextDate): ?>
                                <li>
                                    <span class="date"><?= htmlspecialchars($nextDate['date']); ?></span>
                                    <span class="time"><?= htmlspecialchars($nextDate['time']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="event-info-card">
                    <h3>Questions?</h3>
                    <p>We'd love to hear from you!</p>
                    <div class="event-contact">
                        <a href="mailto:<?= htmlspecialchars($eventData['contact_email']); ?>" class="contact-link">
                            <span>✉️</span> <?= htmlspecialchars($eventData['contact_email']); ?>
                        </a>
                        <a href="tel:<?= preg_replace('/\s+/', '', $eventData['contact_phone']); ?>" class="contact-link">
                            <span>📞</span> <?= htmlspecialchars($eventData['contact_phone']); ?>
                        </a>
                    </div>
                </div>

                <!-- Share & Invite -->
                <div class="event-info-card event-share-card">
                    <h3>Invite a Friend</h3>
                    <p>Know someone who'd enjoy this? Share it!</p>
                    <div class="event-share-buttons">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl; ?>" target="_blank" rel="noopener" class="share-btn share-facebook" title="Share on Facebook">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                            </svg>
                        </a>
                        <a href="https://twitter.com/intent/tweet?text=<?= urlencode('Join me at ' . $eventData['title'] . '!'); ?>&url=<?= $shareUrl; ?>" target="_blank" rel="noopener" class="share-btn share-twitter" title="Share on X">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                        </a>
                        <a href="https://api.whatsapp.com/send?text=<?= urlencode('Join me at ' . $eventData['title'] . '! '); ?><?= $shareUrl; ?>" target="_blank" rel="noopener" class="share-btn share-whatsapp" title="Share on WhatsApp">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                        </a>
                        <a href="mailto:?subject=<?= urlencode('Join me at ' . $eventData['title']); ?>&body=<?= urlencode('Hey! I thought you might be interested in this event: ' . $eventData['title'] . "\n\n" . $currentUrl); ?>" class="share-btn share-email" title="Send Email">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </a>
                        <button class="share-btn share-copy" data-action="copy-event-link" title="Copy Link">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                            </svg>
                        </button>
                    </div>
                    <div class="copy-confirmation" id="copy-confirmation" style="display: none;">Link copied!</div>
                </div>
            </aside>
        </div>
    </div>
</section>

<!-- Back to Events -->
<section class="content-section">
    <div class="container" style="text-align: center;">
        <a href="/events" class="btn btn-outline">← Back to All Events</a>
    </div>
</section>

<script <?= csp_nonce(); ?>>
document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action="copy-event-link"]');
    if (btn) {
        navigator.clipboard.writeText(window.location.href).then(() => {
            const confirmation = document.getElementById('copy-confirmation');
            confirmation.style.display = 'block';
            setTimeout(() => confirmation.style.display = 'none', 2000);
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
