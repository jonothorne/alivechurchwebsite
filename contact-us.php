<?php
/**
 * Contact Us Page
 * General contact form and church contact information
 */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/form-handler.php';

$page_title = 'Contact Us | ' . $site['name'];
$contact_notice = null;
$contact_values = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($contact_values as $field => $_) {
        $contact_values[$field] = sanitize_field($_POST[$field] ?? '');
    }

    // Validate required fields
    if (empty($contact_values['name']) || empty($contact_values['email']) || empty($contact_values['message'])) {
        $contact_notice = ['type' => 'error', 'message' => 'Please fill in all required fields.'];
    } elseif (!filter_var($contact_values['email'], FILTER_VALIDATE_EMAIL)) {
        $contact_notice = ['type' => 'error', 'message' => 'Please enter a valid email address.'];
    } else {
        $saved = process_form_submission('contact', $contact_values);
        if ($saved) {
            $contact_notice = ['type' => 'success', 'message' => 'Thank you for your message! We\'ll get back to you as soon as possible.'];
            $contact_values = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];
        } else {
            $contact_notice = ['type' => 'error', 'message' => 'We were unable to send your message. Please try again or email us directly.'];
        }
    }
}

include __DIR__ . '/includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('contact-us');
}
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow light" data-cms-editable="hero_eyebrow" data-cms-page="contact-us" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Get In Touch'); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="contact-us" data-cms-type="text"><?= $cms->text('hero_headline', 'We\'d love to hear from you'); ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="contact-us" data-cms-type="text"><?= $cms->text('hero_subtext', 'Whether you have a question, want to learn more about the church, or just want to say hello - we\'re here for you.'); ?></p>
    </div>
</section>

<section class="content-section">
    <div class="container">
        <div class="split">
            <div>
                <h2 data-cms-editable="info_headline" data-cms-page="contact-us" data-cms-type="text"><?= $cms->text('info_headline', 'Contact Information'); ?></h2>

                <div class="contact-info-cards">
                    <div class="contact-info-card">
                        <div class="contact-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                        </div>
                        <div class="contact-details">
                            <h3>Visit Us</h3>
                            <p><?= htmlspecialchars($site['location']); ?></p>
                            <a href="<?= htmlspecialchars($site['maps_url']); ?>" target="_blank" rel="noopener" class="btn-link">Get Directions</a>
                        </div>
                    </div>

                    <div class="contact-info-card">
                        <div class="contact-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </div>
                        <div class="contact-details">
                            <h3>Email Us</h3>
                            <p><a href="mailto:<?= htmlspecialchars($site['email']); ?>"><?= htmlspecialchars($site['email']); ?></a></p>
                            <p class="contact-note">We aim to respond within 24-48 hours</p>
                        </div>
                    </div>

                    <?php if (!empty($site['phone'])): ?>
                    <div class="contact-info-card">
                        <div class="contact-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                        </div>
                        <div class="contact-details">
                            <h3>Call Us</h3>
                            <p><a href="tel:<?= preg_replace('/\s+/', '', $site['phone']); ?>"><?= htmlspecialchars($site['phone']); ?></a></p>
                            <p class="contact-note">Office hours: Mon-Fri, 9am-5pm</p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="contact-info-card">
                        <div class="contact-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <div class="contact-details">
                            <h3>Service Times</h3>
                            <p><?= htmlspecialchars($site['service_times']); ?></p>
                            <p class="contact-note"><?= htmlspecialchars($site['service_details']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="social-connect">
                    <h3>Connect With Us</h3>
                    <div class="social-links">
                        <?php if (!empty($site['social']['facebook'])): ?>
                            <a href="<?= htmlspecialchars($site['social']['facebook']); ?>" target="_blank" rel="noopener" class="social-link" aria-label="Facebook">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($site['social']['instagram'])): ?>
                            <a href="<?= htmlspecialchars($site['social']['instagram']); ?>" target="_blank" rel="noopener" class="social-link" aria-label="Instagram">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                                    <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($site['social']['youtube'])): ?>
                            <a href="<?= htmlspecialchars($site['social']['youtube']); ?>" target="_blank" rel="noopener" class="social-link" aria-label="YouTube">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23 7s-.3-2-1.2-2.8c-1.1-1.2-2.4-1.2-3-1.3C15.4 2.7 12 2.7 12 2.7s-3.4 0-6.8.2c-.6.1-1.9.1-3 1.3C1.3 5 1 7 1 7S.7 9.3.7 11.6v2.2c0 2.3.3 4.6.3 4.6s.3 2 1.2 2.8c1.1 1.2 2.6 1.1 3.3 1.3 2.4.2 10.5.3 10.5.3s3.4 0 6.8-.3c.6-.1 1.9-.1 3-1.3.9-.8 1.2-2.8 1.2-2.8s.3-2.3.3-4.6v-2.2c0-2.3-.3-4.6-.3-4.6zM9.5 15.5V8.5l6.5 3.5-6.5 3.5z"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <form class="card form-card" method="post" id="contact-form">
                <h3>Send Us a Message</h3>
                <input type="hidden" name="form_type" value="contact">
                <div class="form-message" id="form-message" style="display: none;"></div>

                <label>
                    <span>Your Name <span class="required">*</span></span>
                    <input type="text" name="name" id="name" placeholder="First & Last" required>
                    <span class="form-error" id="name-error"></span>
                </label>

                <label>
                    <span>Email <span class="required">*</span></span>
                    <input type="email" name="email" id="email" placeholder="your@email.com" required>
                    <span class="form-error" id="email-error"></span>
                </label>

                <label>
                    <span>Phone <span class="optional">(optional)</span></span>
                    <input type="tel" name="phone" id="phone" placeholder="Your phone number">
                </label>

                <label>
                    <span>Subject <span class="optional">(optional)</span></span>
                    <select name="subject" id="subject">
                        <option value="">Select a topic...</option>
                        <option value="General Enquiry">General Enquiry</option>
                        <option value="First Time Visitor">I'm Planning to Visit</option>
                        <option value="Groups & Community">Groups & Community</option>
                        <option value="Serving & Volunteering">Serving & Volunteering</option>
                        <option value="Pastoral Care">Pastoral Care</option>
                        <option value="Bible Study">Bible Study</option>
                        <option value="Events">Events</option>
                        <option value="Website Feedback">Website Feedback</option>
                        <option value="Other">Other</option>
                    </select>
                </label>

                <label>
                    <span>Message <span class="required">*</span></span>
                    <textarea rows="6" name="message" id="message"
                              placeholder="How can we help you?"
                              required></textarea>
                    <span class="form-error" id="message-error"></span>
                </label>

                <button type="submit" class="btn btn-primary" id="submit-btn">
                    <span class="btn-text">Send Message</span>
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
            document.getElementById('contact-form').addEventListener('submit', async function(e) {
                e.preventDefault();

                const form = this;
                const btn = document.getElementById('submit-btn');
                const btnText = btn.querySelector('.btn-text');
                const btnSpinner = btn.querySelector('.btn-spinner');
                const formMessage = document.getElementById('form-message');

                // Clear previous errors
                document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
                formMessage.style.display = 'none';

                // Show loading state
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

                        btn.disabled = false;
                        btnText.style.display = 'inline';
                        btnSpinner.style.display = 'none';
                    } else {
                        // Show field-specific errors
                        if (data.errors) {
                            for (const [field, error] of Object.entries(data.errors)) {
                                const fieldError = document.getElementById(field + '-error');
                                if (fieldError) fieldError.textContent = error;
                            }
                        }

                        formMessage.className = 'form-message error';
                        formMessage.textContent = data.error || 'Please fix the errors and try again.';
                        formMessage.style.display = 'block';

                        btn.disabled = false;
                        btnText.style.display = 'inline';
                        btnSpinner.style.display = 'none';
                    }
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
        <h2 data-cms-editable="other_headline" data-cms-page="contact-us" data-cms-type="text"><?= $cms->text('other_headline', 'Looking for Something Specific?'); ?></h2>
        <p data-cms-editable="other_subtext" data-cms-page="contact-us" data-cms-type="text"><?= $cms->text('other_subtext', 'We have dedicated pages for common requests. Click below to get started.'); ?></p>
        <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap;">
            <a href="/prayer" class="btn btn-outline">
                Submit a Prayer Request
            </a>
            <a href="/visit" class="btn btn-outline">
                Plan Your First Visit
            </a>
            <a href="/serve/apply" class="btn btn-outline">
                Apply to Serve
            </a>
            <a href="/groups/join" class="btn btn-primary">
                Join a Group
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
