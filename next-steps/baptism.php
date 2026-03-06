<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/form-handler.php';

$page_title = 'Baptism Signup | ' . $site['name'];

$baptism_notice = null;
$baptism_values = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'baptism_status' => '',
    'testimony' => '',
    'preferred_date' => '',
    'guests' => '',
    'questions' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($baptism_values as $field => $_) {
        $baptism_values[$field] = sanitize_field($_POST[$field] ?? '');
    }
    $saved = process_form_submission('baptism-signup', $baptism_values);
    if ($saved) {
        $baptism_notice = ['type' => 'success', 'message' => 'Thank you for taking this step! Our team will contact you within 48 hours to discuss baptism preparation and schedule your baptism.'];
        $baptism_values = ['name' => '', 'email' => '', 'phone' => '', 'baptism_status' => '', 'testimony' => '', 'preferred_date' => '', 'guests' => '', 'questions' => ''];
    } else {
        $baptism_notice = ['type' => 'error', 'message' => 'Something went wrong. Please try again or email us directly.'];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">
    <div class="container narrow">
        <p class="eyebrow light">Next Steps</p>
        <h1>Baptism: Declare Your Faith</h1>
        <p>Baptism is a public declaration of your faith in Jesus. It's an outward sign of an inward decision to follow Him.</p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="split">
            <div>
                <h2>Why Get Baptized?</h2>
                <ul class="info-list">
                    <li><strong>Follow Jesus' Example:</strong> Jesus was baptized in the Jordan River (Matthew 3:13-17).</li>
                    <li><strong>Obey His Command:</strong> Jesus instructed His followers to be baptized (Matthew 28:19).</li>
                    <li><strong>Identify with Christ:</strong> Baptism symbolizes dying to your old life and rising to new life in Christ.</li>
                    <li><strong>Celebrate Publicly:</strong> Share your faith story with your church family and invite loved ones to witness.</li>
                </ul>

                <div class="card" style="margin-top: 2rem;">
                    <h3>What to Expect</h3>
                    <p><strong>1. Preparation:</strong> Meet with a pastor to discuss your faith journey and what baptism means.</p>
                    <p><strong>2. Celebration Sunday:</strong> Share your testimony and be baptized during a service.</p>
                    <p><strong>3. Invitation:</strong> Invite friends and family to witness this special moment.</p>
                    <p><strong>4. Party Time:</strong> Celebrate with our church family after the service!</p>
                </div>

                <img src="/assets/imgs/gallery/alive-church-worship-congregation.jpg"
                     alt="Baptism celebration at Alive Church"
                     style="border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;">
            </div>

            <form class="card form-card" method="post">
                <h3>Baptism Signup</h3>

                <?php if ($baptism_notice): ?>
                    <p class="notice notice-<?= $baptism_notice['type']; ?>" role="status"><?= $baptism_notice['message']; ?></p>
                <?php endif; ?>

                <label>
                    <span>Name *</span>
                    <input type="text" name="name" placeholder="First & Last" value="<?= htmlspecialchars($baptism_values['name']); ?>" required>
                </label>

                <label>
                    <span>Email *</span>
                    <input type="email" name="email" placeholder="you@email.com" value="<?= htmlspecialchars($baptism_values['email']); ?>" required>
                </label>

                <label>
                    <span>Phone</span>
                    <input type="tel" name="phone" placeholder="07123 456789" value="<?= htmlspecialchars($baptism_values['phone']); ?>">
                </label>

                <label>
                    <span>Have you been baptized before? *</span>
                    <select name="baptism_status" required>
                        <option value="">Please select...</option>
                        <option <?= $baptism_values['baptism_status'] === 'No, this is my first time' ? 'selected' : ''; ?>>No, this is my first time</option>
                        <option <?= $baptism_values['baptism_status'] === 'Yes, as a baby' ? 'selected' : ''; ?>>Yes, as a baby</option>
                        <option <?= $baptism_values['baptism_status'] === 'Yes, as a believer' ? 'selected' : ''; ?>>Yes, as a believer</option>
                        <option <?= $baptism_values['baptism_status'] === 'Not sure' ? 'selected' : ''; ?>>Not sure</option>
                    </select>
                </label>

                <label>
                    <span>Brief Faith Story (1-2 sentences) *</span>
                    <textarea rows="3" name="testimony" placeholder="Tell us about when and how you came to faith in Jesus..." required><?= htmlspecialchars($baptism_values['testimony']); ?></textarea>
                    <div class="form-help">This helps us prepare you for sharing your testimony on baptism day</div>
                </label>

                <label>
                    <span>Preferred Date/Service</span>
                    <input type="text" name="preferred_date" placeholder="e.g., 'Next available' or 'Easter Sunday'" value="<?= htmlspecialchars($baptism_values['preferred_date']); ?>">
                </label>

                <label>
                    <span>Expected Number of Guests</span>
                    <input type="number" name="guests" placeholder="How many people will you invite?" min="0" value="<?= htmlspecialchars($baptism_values['guests']); ?>">
                    <div class="form-help">This helps us prepare seating and celebration details</div>
                </label>

                <label>
                    <span>Questions or Special Requests</span>
                    <textarea rows="3" name="questions" placeholder="Any questions about baptism or special circumstances we should know about?"><?= htmlspecialchars($baptism_values['questions']); ?></textarea>
                </label>

                <button type="submit" class="btn btn-primary">Submit Baptism Request</button>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
