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
    <section class="page-hero">
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
include __DIR__ . '/../includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/../includes/cms/ContentManager.php';
    $cms = new ContentManager('event-' . $slug);
}
?>

<section class="event-hero" style="background-image: linear-gradient(rgba(30, 26, 43, 0.7), rgba(30, 26, 43, 0.9)), url('<?= htmlspecialchars($eventData['image']); ?>');">
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
