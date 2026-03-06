<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/form-handler.php';

$page_title = 'Apply to Serve | ' . $site['name'];

// Get team from query parameter
$team = $_GET['team'] ?? '';
$selected_team = null;

// Find the team from serve opportunities
foreach ($serve_opportunities as $opportunity) {
    // Create slug from title for matching
    $title_slug = strtolower(str_replace(' ', '-', $opportunity['title']));
    if ($title_slug === $team) {
        $selected_team = $opportunity;
        break;
    }
}

$serve_notice = null;
$serve_values = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'team' => $selected_team['title'] ?? '',
    'availability' => '',
    'experience' => '',
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($serve_values as $field => $_) {
        $serve_values[$field] = sanitize_field($_POST[$field] ?? '');
    }
    $saved = process_form_submission('serve-application', $serve_values);
    if ($saved) {
        $serve_notice = ['type' => 'success', 'message' => 'Thanks for your interest in serving! Our team will reach out within 48 hours to discuss next steps.'];
        $serve_values = ['name' => '', 'email' => '', 'phone' => '', 'team' => $selected_team['title'] ?? '', 'availability' => '', 'experience' => '', 'message' => ''];
    } else {
        $serve_notice = ['type' => 'error', 'message' => 'Something went wrong. Please try again or email us directly.'];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">
    <div class="container narrow">
        <p class="eyebrow light">Serve With Us</p>
        <h1>Apply to Serve<?= $selected_team ? ': ' . htmlspecialchars($selected_team['title']) : ''; ?></h1>
        <p>Thank you for your interest in using your gifts to serve! Fill out the form below and our team will connect with you.</p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="split">
            <?php if ($selected_team): ?>
                <div>
                    <h2>About This Team</h2>
                    <p><strong><?= htmlspecialchars($selected_team['title']); ?></strong></p>
                    <p><?= htmlspecialchars($selected_team['description']); ?></p>

                    <div style="margin: 1.5rem 0;">
                        <p><strong>Commitment:</strong> <?= htmlspecialchars($selected_team['commitment']); ?></p>
                    </div>

                    <?php if (!empty($selected_team['areas'])): ?>
                        <div>
                            <p><strong>Areas to serve:</strong></p>
                            <div class="areas">
                                <?php foreach ($selected_team['areas'] as $area): ?>
                                    <span class="area-tag"><?= htmlspecialchars($area); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($selected_team['image'])): ?>
                        <img src="<?= htmlspecialchars($selected_team['image']); ?>"
                             alt="<?= htmlspecialchars($selected_team['title']); ?>"
                             style="border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;">
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div>
                    <h2>Why Serve?</h2>
                    <ul class="info-list">
                        <li><strong>Make a Difference:</strong> Your gifts can change someone's Sunday experience.</li>
                        <li><strong>Grow Spiritually:</strong> Serving deepens your own faith journey.</li>
                        <li><strong>Build Community:</strong> Meet amazing people and form lasting friendships.</li>
                        <li><strong>Discover Your Calling:</strong> Find where your passions meet our mission.</li>
                    </ul>
                    <img src="/assets/imgs/gallery/alive-church-community-cafe-outdoor.jpg"
                         alt="Serving at Alive Church"
                         style="border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;">
                </div>
            <?php endif; ?>

            <form class="card form-card" method="post">
                <h3>Application Form</h3>

                <?php if ($serve_notice): ?>
                    <p class="notice notice-<?= $serve_notice['type']; ?>" role="status"><?= $serve_notice['message']; ?></p>
                <?php endif; ?>

                <label>
                    <span>Name *</span>
                    <input type="text" name="name" placeholder="First & Last" value="<?= htmlspecialchars($serve_values['name']); ?>" required>
                </label>

                <label>
                    <span>Email *</span>
                    <input type="email" name="email" placeholder="you@email.com" value="<?= htmlspecialchars($serve_values['email']); ?>" required>
                </label>

                <label>
                    <span>Phone</span>
                    <input type="tel" name="phone" placeholder="07123 456789" value="<?= htmlspecialchars($serve_values['phone']); ?>">
                </label>

                <label>
                    <span>Team/Ministry *</span>
                    <select name="team" required>
                        <option value="">Select a team...</option>
                        <?php foreach ($serve_opportunities as $opportunity): ?>
                            <option value="<?= htmlspecialchars($opportunity['title']); ?>"
                                <?= $serve_values['team'] === $opportunity['title'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($opportunity['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Availability *</span>
                    <select name="availability" required>
                        <option value="">Select your availability...</option>
                        <option <?= $serve_values['availability'] === 'Every week' ? 'selected' : ''; ?>>Every week</option>
                        <option <?= $serve_values['availability'] === 'Twice a month' ? 'selected' : ''; ?>>Twice a month</option>
                        <option <?= $serve_values['availability'] === 'Once a month' ? 'selected' : ''; ?>>Once a month</option>
                        <option <?= $serve_values['availability'] === 'Special events only' ? 'selected' : ''; ?>>Special events only</option>
                        <option <?= $serve_values['availability'] === 'Flexible' ? 'selected' : ''; ?>>Flexible</option>
                    </select>
                </label>

                <label>
                    <span>Relevant Experience</span>
                    <textarea rows="3" name="experience" placeholder="Have you served in this area before? Any relevant skills or training?"><?= htmlspecialchars($serve_values['experience']); ?></textarea>
                </label>

                <label>
                    <span>Anything Else We Should Know?</span>
                    <textarea rows="3" name="message" placeholder="Questions, preferences, or additional info..."><?= htmlspecialchars($serve_values['message']); ?></textarea>
                </label>

                <button type="submit" class="btn btn-primary">Submit Application</button>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
