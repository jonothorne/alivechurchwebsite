<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/form-handler.php';

$page_title = 'Join a Group | ' . $site['name'];

// Get group from query parameter
$group_param = $_GET['group'] ?? '';
$selected_group = null;

// Find the group from groups array
foreach ($groups as $group) {
    // Create slug from title for matching
    $title_slug = strtolower(str_replace(' ', '-', $group['title']));
    if ($title_slug === $group_param) {
        $selected_group = $group;
        break;
    }
}

$join_notice = null;
$join_values = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'group_interest' => $selected_group['title'] ?? '',
    'availability' => '',
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($join_values as $field => $_) {
        $join_values[$field] = sanitize_field($_POST[$field] ?? '');
    }
    $saved = process_form_submission('group-signup', $join_values);
    if ($saved) {
        $join_notice = ['type' => 'success', 'message' => 'Thanks for your interest! A group leader will reach out within 48 hours to connect you.'];
        $join_values = ['name' => '', 'email' => '', 'phone' => '', 'group_interest' => $selected_group['title'] ?? '', 'availability' => '', 'message' => ''];
    } else {
        $join_notice = ['type' => 'error', 'message' => 'Something went wrong. Please try again or email us directly.'];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">
    <div class="container narrow">
        <p class="eyebrow light">Groups</p>
        <h1><?= $selected_group ? 'Join: ' . htmlspecialchars($selected_group['title']) : 'Life change happens in circles, not rows.'; ?></h1>
        <p>Find a group where you can belong, grow, and make real friendships that last beyond Sunday.</p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="split">
            <?php if ($selected_group): ?>
                <div>
                    <h2>About This Group</h2>
                    <p><strong><?= htmlspecialchars($selected_group['title']); ?></strong></p>
                    <p><?= htmlspecialchars($selected_group['description']); ?></p>

                    <div style="margin: 1.5rem 0;">
                        <p><strong>📅 Schedule:</strong> <?= htmlspecialchars($selected_group['schedule']); ?></p>
                        <p><strong>📍 Location:</strong> <?= htmlspecialchars($selected_group['location']); ?></p>
                    </div>

                    <?php if (isset($selected_group['image'])): ?>
                        <img src="<?= htmlspecialchars($selected_group['image']); ?>"
                             alt="<?= htmlspecialchars($selected_group['title']); ?>"
                             style="border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;">
                    <?php endif; ?>

                    <div style="margin-top: 2rem; padding: 1.5rem; background: #f9fafb; border-radius: 0.75rem;">
                        <h3 style="margin-bottom: 1rem;">Why Join a Group?</h3>
                        <ul class="info-list">
                            <li><strong>Authentic Community:</strong> Real relationships with people who care about you.</li>
                            <li><strong>Grow Together:</strong> Study the Bible, pray together, and support each other.</li>
                            <li><strong>Everyone Welcome:</strong> New to faith or been walking with Jesus for years.</li>
                        </ul>
                    </div>
                </div>
            <?php else: ?>
                <div>
                    <h2>Why join a group?</h2>
                    <ul class="info-list">
                        <li><strong>Authentic Community:</strong> Real relationships with people who care about you and your journey.</li>
                        <li><strong>Grow Together:</strong> Study the Bible, pray together, and support each other through life.</li>
                        <li><strong>Flexible Options:</strong> Groups meet at different times, locations, and focus on various interests.</li>
                        <li><strong>Everyone Welcome:</strong> Whether you're new to faith or been walking with Jesus for years.</li>
                    </ul>

                    <h3 style="margin-top: 2rem;">Available Groups</h3>
                    <div class="card-grid" style="margin-top: 1rem;">
                        <?php foreach ($groups as $group): ?>
                            <div class="card">
                                <h4><?= htmlspecialchars($group['title']); ?></h4>
                                <p><?= htmlspecialchars($group['description']); ?></p>
                                <p class="small-text">
                                    <strong>📅 <?= htmlspecialchars($group['schedule']); ?></strong><br>
                                    <strong>📍 <?= htmlspecialchars($group['location']); ?></strong>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form class="card form-card" method="post">
                <h3>Join a Group</h3>
                <p>Fill out this form and we'll help you find the perfect fit!</p>

                <?php if ($join_notice): ?>
                    <p class="notice notice-<?= $join_notice['type']; ?>" role="status"><?= $join_notice['message']; ?></p>
                <?php endif; ?>

                <label>
                    <span>Your Name</span>
                    <input type="text" name="name" placeholder="First & Last"
                           value="<?= htmlspecialchars($join_values['name']); ?>" required>
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="email" placeholder="your@email.com"
                           value="<?= htmlspecialchars($join_values['email']); ?>" required>
                </label>

                <label>
                    <span>Phone</span>
                    <input type="tel" name="phone" placeholder="07XXX XXXXXX"
                           value="<?= htmlspecialchars($join_values['phone']); ?>">
                </label>

                <label>
                    <span>Which group interests you?</span>
                    <select name="group_interest" required>
                        <option value="">Select a group...</option>
                        <option <?= $join_values['group_interest'] === 'Gateway Group' ? 'selected' : ''; ?>>Gateway Group</option>
                        <option <?= $join_values['group_interest'] === 'Men\'s Breakfast' ? 'selected' : ''; ?>>Men's Breakfast</option>
                        <option <?= $join_values['group_interest'] === 'Women\'s Evening' ? 'selected' : ''; ?>>Women's Evening</option>
                        <option <?= $join_values['group_interest'] === 'Not sure - help me find one' ? 'selected' : ''; ?>>Not sure - help me find one</option>
                    </select>
                </label>

                <label>
                    <span>Best day/time for you?</span>
                    <select name="availability">
                        <option value="">Select...</option>
                        <option <?= $join_values['availability'] === 'Weekday Mornings' ? 'selected' : ''; ?>>Weekday Mornings</option>
                        <option <?= $join_values['availability'] === 'Weekday Evenings' ? 'selected' : ''; ?>>Weekday Evenings</option>
                        <option <?= $join_values['availability'] === 'Weekends' ? 'selected' : ''; ?>>Weekends</option>
                        <option <?= $join_values['availability'] === 'Flexible' ? 'selected' : ''; ?>>Flexible</option>
                    </select>
                </label>

                <label>
                    <span>Anything else we should know?</span>
                    <textarea rows="3" name="message"
                              placeholder="Tell us about yourself or what you're looking for in a group..."><?= htmlspecialchars($join_values['message']); ?></textarea>
                </label>

                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        </div>
    </div>
</section>

<section class="content-section alt">
    <div class="container narrow center-text">
        <h2>Not ready to join yet?</h2>
        <p>That's okay! Come visit us on a Sunday and we can chat about groups in person.</p>
        <a href="/visit" class="btn btn-primary">Plan Your Visit</a>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
