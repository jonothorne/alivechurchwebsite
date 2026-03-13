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

<section class="page-hero <?= $hero_texture_class; ?>">
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

            <form class="card form-card" method="post" id="group-form">
                <h3>Join a Group</h3>
                <p>Fill out this form and we'll help you find the perfect fit!</p>

                <input type="hidden" name="form_type" value="group">
                <div class="form-message" id="form-message" style="display: none;"></div>

                <label>
                    <span>Your Name</span>
                    <input type="text" name="name" id="name" placeholder="First & Last" required>
                    <span class="form-error" id="name-error"></span>
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="email" id="email" placeholder="your@email.com" required>
                    <span class="form-error" id="email-error"></span>
                </label>

                <label>
                    <span>Phone</span>
                    <input type="tel" name="phone" id="phone" placeholder="07XXX XXXXXX">
                </label>

                <label>
                    <span>Which group interests you?</span>
                    <select name="group_interest" id="group_interest" required>
                        <option value="">Select a group...</option>
                        <option <?= ($selected_group['title'] ?? '') === 'Gateway Group' ? 'selected' : ''; ?>>Gateway Group</option>
                        <option <?= ($selected_group['title'] ?? '') === "Men's Breakfast" ? 'selected' : ''; ?>>Men's Breakfast</option>
                        <option <?= ($selected_group['title'] ?? '') === "Women's Evening" ? 'selected' : ''; ?>>Women's Evening</option>
                        <option>Not sure - help me find one</option>
                    </select>
                </label>

                <label>
                    <span>Best day/time for you?</span>
                    <select name="availability" id="availability">
                        <option value="">Select...</option>
                        <option>Weekday Mornings</option>
                        <option>Weekday Evenings</option>
                        <option>Weekends</option>
                        <option>Flexible</option>
                    </select>
                </label>

                <label>
                    <span>Anything else we should know?</span>
                    <textarea rows="3" name="message" id="message"
                              placeholder="Tell us about yourself or what you're looking for in a group..."></textarea>
                </label>

                <button type="submit" class="btn btn-primary" id="submit-btn">
                    <span class="btn-text">Submit</span>
                    <span class="btn-spinner" style="display: none;">
                        <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" opacity="0.25"/>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round">
                                <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                            </path>
                        </svg>
                    </span>
                </button>
            </form>

            <script>
            document.getElementById('group-form').addEventListener('submit', async function(e) {
                e.preventDefault();

                const form = this;
                const btn = document.getElementById('submit-btn');
                const btnText = btn.querySelector('.btn-text');
                const btnSpinner = btn.querySelector('.btn-spinner');
                const formMessage = document.getElementById('form-message');

                document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
                formMessage.style.display = 'none';

                btn.disabled = true;
                btnText.style.display = 'none';
                btnSpinner.style.display = 'inline-block';

                try {
                    const formData = new FormData(form);
                    const response = await fetch('/api/forms/submit', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        formMessage.className = 'form-message success';
                        formMessage.textContent = data.message;
                        formMessage.style.display = 'block';
                        form.reset();
                    } else {
                        if (data.errors) {
                            for (const [field, error] of Object.entries(data.errors)) {
                                const fieldError = document.getElementById(field + '-error');
                                if (fieldError) fieldError.textContent = error;
                            }
                        }
                        formMessage.className = 'form-message error';
                        formMessage.textContent = data.error || 'Please fix the errors and try again.';
                        formMessage.style.display = 'block';
                    }

                    btn.disabled = false;
                    btnText.style.display = 'inline';
                    btnSpinner.style.display = 'none';
                } catch (error) {
                    formMessage.className = 'form-message error';
                    formMessage.textContent = 'Something went wrong. Please try again.';
                    formMessage.style.display = 'block';

                    btn.disabled = false;
                    btnText.style.display = 'inline';
                    btnSpinner.style.display = 'none';
                }
            });
            </script>
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
