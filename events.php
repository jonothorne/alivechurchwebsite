<?php
require __DIR__ . '/config.php';
$page_title = 'Events | ' . $site['name'];
include __DIR__ . '/includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('events');
}
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow" data-cms-editable="hero_eyebrow" data-cms-page="events" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Events & Gatherings'); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="events" data-cms-type="text"><?= $cms->text('hero_headline', 'What\'s happening at Alive'); ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="events" data-cms-type="text"><?= $cms->text('hero_subtext', 'From weekly gatherings to special events, there\'s always something happening. Mark your calendar and join us.'); ?></p>
    </div>
</section>

<section class="events-calendar">
    <div class="container">
        <!-- Filter Controls -->
        <div class="event-controls">
            <!-- Search Box -->
            <div class="event-search">
                <input type="text"
                       id="event-search"
                       placeholder="Search events..."
                       aria-label="Search events">
            </div>

            <!-- Month Picker -->
            <div class="month-picker">
                <select id="month-filter" aria-label="Filter by month">
                    <option value="all">All Months</option>
                    <?php
                    // Generate next 12 months
                    for ($i = 0; $i < 12; $i++) {
                        $month = date('Y-m', strtotime("+$i months"));
                        $monthName = date('F Y', strtotime("+$i months"));
                        echo "<option value=\"$month\">$monthName</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <!-- Category Filter Buttons -->
        <div class="event-filters">
            <button type="button" class="filter-btn active" data-filter="all">All Events</button>
            <button type="button" class="filter-btn" data-filter="weekly">Weekly</button>
            <button type="button" class="filter-btn" data-filter="special">Special Events</button>
            <button type="button" class="filter-btn" data-filter="groups">Groups</button>
            <button type="button" class="filter-btn" data-filter="youth">Youth</button>
            <button type="button" class="filter-btn" data-filter="outreach">Outreach</button>
        </div>

        <!-- Events Grid -->
        <div class="events-grid">
            <?php foreach ($all_events as $index => $event): ?>
                <article class="event-card-detailed"
                         data-category="<?= htmlspecialchars($event['category'] ?? ''); ?>"
                         data-month="<?= !empty($event['start_datetime']) ? date('Y-m', strtotime($event['start_datetime'])) : date('Y-m'); ?>"
                         data-title="<?= htmlspecialchars(strtolower($event['title'] ?? '')); ?>"
                         data-description="<?= htmlspecialchars(strtolower($event['description'] ?? '')); ?>"
                         data-index="<?= $index; ?>"
                         style="<?= $index >= 12 ? 'display: none;' : ''; ?>">

                    <?php if ($event['image']): ?>
                        <img src="<?= htmlspecialchars($event['image']); ?>"
                             alt="<?= htmlspecialchars($event['title']); ?>"
                             class="event-image">
                    <?php endif; ?>

                    <div class="event-content">
                        <div class="event-header">
                            <span class="event-category"><?= htmlspecialchars(ucfirst($event['category'])); ?></span>
                            <?php if (!empty($event['is_recurring'])): ?>
                                <span class="event-recurring-badge" title="<?= htmlspecialchars($event['frequency']); ?>">
                                    🔄 <?= htmlspecialchars($event['frequency']); ?>
                                </span>
                            <?php endif; ?>
                            <span class="event-date-badge"><?= htmlspecialchars($event['date']); ?></span>
                        </div>

                        <h3><?= htmlspecialchars($event['title']); ?></h3>
                        <p><?= htmlspecialchars($event['description']); ?></p>

                        <div class="event-details">
                            <p><strong>🕐 Time:</strong> <?= htmlspecialchars($event['time']); ?></p>
                            <p><strong>📍 Location:</strong> <?= htmlspecialchars($event['location']); ?></p>
                            <?php if ($event['cost']): ?>
                                <p><strong>💷 Cost:</strong> <?= htmlspecialchars($event['cost']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="event-actions">
                            <a class="btn btn-outline" href="/events/<?= htmlspecialchars($event['slug']); ?>">
                                Learn More
                            </a>
                            <?php if ($event['registration_required'] && !empty($event['registration_url'])): ?>
                                <a class="btn btn-primary" href="<?= htmlspecialchars($event['registration_url']); ?>" target="_blank">
                                    Register
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Load More Button -->
        <?php if (count($all_events) > 12): ?>
            <div class="load-more-container">
                <button id="load-more-events" class="btn btn-outline">
                    Load More Events
                </button>
                <p class="events-count">
                    Showing <span id="visible-count">12</span> of <span id="total-count"><?= count($all_events); ?></span> events
                </p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/newsletter.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
