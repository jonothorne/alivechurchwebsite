<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/form-handler.php';

$page_title = 'Event Registration | ' . $site['name'];

// Get event slug from query parameter
$event_slug = $_GET['event'] ?? '';
$selected_event = null;

// Find the event from config
foreach ($all_events as $event) {
    if (isset($event['slug']) && $event['slug'] === $event_slug) {
        $selected_event = $event;
        break;
    }
}

// If no event found, redirect to events page
if (!$selected_event) {
    header('Location: /events');
    exit;
}

$register_notice = null;
$register_values = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'adults' => '1',
    'children' => '0',
    'message' => '',
    'event_title' => $selected_event['title']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($register_values as $field => $_) {
        $register_values[$field] = sanitize_field($_POST[$field] ?? $register_values[$field]);
    }
    $saved = process_form_submission('event-registration', $register_values);
    if ($saved) {
        $register_notice = ['type' => 'success', 'message' => 'Registration confirmed! Check your email for event details and reminders.'];
        $register_values = ['name' => '', 'email' => '', 'phone' => '', 'adults' => '1', 'children' => '0', 'message' => '', 'event_title' => $selected_event['title']];
    } else {
        $register_notice = ['type' => 'error', 'message' => 'Something went wrong. Please try again or contact us directly.'];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow light">Event Registration</p>
        <h1><?= htmlspecialchars($selected_event['title']); ?></h1>
        <p><?= htmlspecialchars($selected_event['description']); ?></p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="split">
            <div>
                <h2>Event Details</h2>
                <div class="card">
                    <div class="event-details" style="padding: 0;">
                        <p><strong>📅 Date:</strong> <?= htmlspecialchars($selected_event['date']); ?></p>
                        <p><strong>🕐 Time:</strong> <?= htmlspecialchars($selected_event['time']); ?></p>
                        <p><strong>📍 Location:</strong> <?= htmlspecialchars($selected_event['location']); ?></p>
                        <?php if ($selected_event['cost']): ?>
                            <p><strong>💷 Cost:</strong> <?= htmlspecialchars($selected_event['cost']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <h3 style="margin-top: 2rem;">What to expect</h3>
                <ul class="info-list">
                    <li><strong>Confirmation Email:</strong> You'll receive details and reminders via email.</li>
                    <li><strong>Check-in:</strong> Arrive 15 minutes early for easy check-in.</li>
                    <li><strong>Questions?</strong> Contact us at <a href="mailto:<?= htmlspecialchars($site['email']); ?>"><?= htmlspecialchars($site['email']); ?></a></li>
                </ul>

                <?php if ($selected_event['image']): ?>
                    <img src="<?= htmlspecialchars($selected_event['image']); ?>"
                         alt="<?= htmlspecialchars($selected_event['title']); ?>"
                         style="border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;">
                <?php endif; ?>
            </div>

            <form class="card form-card" method="post">
                <h3>Register for This Event</h3>

                <?php if ($register_notice): ?>
                    <p class="notice notice-<?= $register_notice['type']; ?>" role="status"><?= $register_notice['message']; ?></p>
                <?php endif; ?>

                <input type="hidden" name="event_title" value="<?= htmlspecialchars($selected_event['title']); ?>">

                <label>
                    <span>Your Name</span>
                    <input type="text" name="name" placeholder="First & Last"
                           value="<?= htmlspecialchars($register_values['name']); ?>" required>
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="email" placeholder="your@email.com"
                           value="<?= htmlspecialchars($register_values['email']); ?>" required>
                </label>

                <label>
                    <span>Phone</span>
                    <input type="tel" name="phone" placeholder="07XXX XXXXXX"
                           value="<?= htmlspecialchars($register_values['phone']); ?>">
                </label>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <label>
                        <span>Adults</span>
                        <input type="number" name="adults" min="1" max="20"
                               value="<?= htmlspecialchars($register_values['adults']); ?>">
                    </label>

                    <label>
                        <span>Children</span>
                        <input type="number" name="children" min="0" max="20"
                               value="<?= htmlspecialchars($register_values['children']); ?>">
                    </label>
                </div>

                <label>
                    <span>Special Requests or Dietary Needs</span>
                    <textarea rows="3" name="message"
                              placeholder="Let us know if you have any questions or needs..."><?= htmlspecialchars($register_values['message']); ?></textarea>
                </label>

                <button type="submit" class="btn btn-primary">Complete Registration</button>
            </form>
        </div>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow center-text">
        <h2>Looking for other events?</h2>
        <a href="/events" class="btn btn-outline">View All Events</a>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
