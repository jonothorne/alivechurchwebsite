<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/form-handler.php';

$page_title = 'Plan a Visit | ' . $site['name'];
$page_description = 'Plan your visit to Alive Church Norwich. Find service times, location, parking info, and what to expect when you visit our welcoming church in Norwich.';
$visit_notice = null;
$visit_values = [
    'name' => '',
    'email' => '',
    'gathering' => 'Sunday 11:00AM',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($visit_values as $field => $_) {
        $visit_values[$field] = sanitize_field($_POST[$field] ?? $visit_values[$field]);
    }
    $saved = process_form_submission('visit', $visit_values);
    if ($saved) {
        $visit_notice = ['type' => 'success', 'message' => 'Thanks for planning a visit! Our team will be in touch soon.'];
        $visit_values = ['name' => '', 'email' => '', 'gathering' => 'Sunday 11:00AM', 'notes' => ''];
    } else {
        $visit_notice = ['type' => 'error', 'message' => 'Something went wrong. Please try again or email us directly.'];
    }
}

include __DIR__ . '/includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('visit');
}
?>
<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow" data-cms-editable="hero_eyebrow" data-cms-page="visit" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'I\'m New'); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="visit" data-cms-type="text"><?= $cms->text('hero_headline', 'You belong before you believe.'); ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="visit" data-cms-type="text"><?= $cms->text('hero_subtext', 'Whether you\'re exploring faith for the first time or looking for a new church home, you\'re welcome here. No perfect people allowed.'); ?></p>
        <div class="hero-ctas">
            <a href="#plan-visit" class="btn btn-primary">Plan Your Visit</a>
            <a href="#watch-video" class="btn btn-outline">Watch What to Expect</a>
        </div>
    </div>
</section>

<!-- Welcome Video Section -->
<section class="welcome-video-section" id="watch-video">
    <div class="container">
        <div class="welcome-video-split">
            <div class="welcome-video-content">
                <p class="eyebrow" data-cms-editable="video_eyebrow" data-cms-page="visit" data-cms-type="text"><?= $cms->text('video_eyebrow', 'See For Yourself'); ?></p>
                <h2 data-cms-editable="video_headline" data-cms-page="visit" data-cms-type="text"><?= $cms->text('video_headline', 'What\'s it like at Alive Church?'); ?></h2>
                <div data-cms-editable="video_text" data-cms-page="visit" data-cms-type="html"><?= $cms->html('video_text', '<p>Take a quick peek inside a Sunday service. Real people, real worship, real community.</p><p>We know walking into a new church can feel intimidating. Our hope is this video helps you feel at home before you even arrive.</p>'); ?></div>
            </div>
            <div class="welcome-video-embed">
                <?php
                $welcomeVideoUrl = $cms->text('welcome_video_url', '');
                if ($welcomeVideoUrl && preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $welcomeVideoUrl, $matches)):
                    $youtubeId = $matches[1];
                ?>
                <div class="video-wrapper">
                    <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($youtubeId); ?>?rel=0"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen></iframe>
                </div>
                <?php else: ?>
                <div class="video-placeholder">
                    <img src="/assets/imgs/gallery/alive-church-worship-congregation.jpg" alt="Alive Church Sunday Service">
                    <div class="video-placeholder-overlay">
                        <span class="video-placeholder-text">Video coming soon</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<section class="content-section" id="plan-visit">
    <div class="container split">
        <div>
            <h2 data-cms-editable="expect_headline" data-cms-page="visit" data-cms-type="text"><?= $cms->text('expect_headline', 'What to expect'); ?></h2>
            <div data-cms-editable="expect_content" data-cms-page="visit" data-cms-type="html"><?= $cms->html('expect_content', '<ul class="info-list">
                <li><strong>Services:</strong> ' . $site['service_times'] . ' with live worship & teaching.</li>
                <li><strong>Kids:</strong> Secure check-in, age-specific teaching, sensory-friendly spaces.</li>
                <li><strong>Parking:</strong> Look for the pink "You Belong Here" flags and our team will guide you.</li>
                <li><strong>Dress:</strong> Trainers or ties—come as you are.</li>
            </ul>
            <p>Need extra assistance? Email <a href="mailto:' . $site['email'] . '">' . $site['email'] . '</a> and we\'ll make arrangements.</p>'); ?></div>
            <img src="/assets/imgs/gallery/alive-church-christmas-service-celebration.jpg" alt="Alive Church welcoming service" class="content-image" data-cms-editable="expect_image" data-cms-page="visit" data-cms-type="image">
        </div>
        <form class="card form-card" id="visit-form" method="post">
            <input type="hidden" name="form_type" value="visit">
            <div class="form-message hidden" id="form-message"></div>

            <label>
                <span>Name</span>
                <input type="text" name="name" id="name" placeholder="First & Last" required>
                <span class="form-error" id="name-error"></span>
            </label>
            <label>
                <span>Email</span>
                <input type="email" name="email" id="email" placeholder="you@email.com" required>
                <span class="form-error" id="email-error"></span>
            </label>
            <label>
                <span>Preferred Gathering</span>
                <select name="gathering" id="gathering">
                    <option>Sunday 11:00AM</option>
                    <option>Alive Online</option>
                </select>
            </label>
            <label>
                <span>Anything we can prepare?</span>
                <textarea rows="4" name="notes" id="notes" placeholder="Accessibility, kids, prayer..."></textarea>
            </label>
            <button type="submit" class="btn btn-primary" id="submit-btn">
                <span class="btn-text">Submit</span>
                <span class="btn-spinner hidden">
                    <svg width="20" height="20" viewBox="0 0 24 24" class="btn-spinner-svg">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" opacity="0.25"/>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round">
                            <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                        </path>
                    </svg>
                </span>
            </button>
        </form>

        <script <?= csp_nonce(); ?>>
        document.getElementById('visit-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const form = this;
            const btn = document.getElementById('submit-btn');
            const btnText = btn.querySelector('.btn-text');
            const btnSpinner = btn.querySelector('.btn-spinner');
            const formMessage = document.getElementById('form-message');

            document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
            formMessage.classList.add('hidden');

            btn.disabled = true;
            btnText.classList.add('hidden');
            btnSpinner.classList.remove('hidden');

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
                    formMessage.classList.remove('hidden');
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
                    formMessage.classList.remove('hidden');
                }

                btn.disabled = false;
                btnText.classList.remove('hidden');
                btnSpinner.classList.add('hidden');
            } catch (error) {
                formMessage.className = 'form-message error';
                formMessage.textContent = 'Something went wrong. Please try again.';
                formMessage.classList.remove('hidden');

                btn.disabled = false;
                btnText.classList.remove('hidden');
                btnSpinner.classList.add('hidden');
            }
        });
        </script>
    </div>
</section>
<section class="location-section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow light" data-cms-editable="location_eyebrow" data-cms-page="visit" data-cms-type="text"><?= $cms->text('location_eyebrow', 'Find Us'); ?></p>
            <h2 data-cms-editable="location_headline" data-cms-page="visit" data-cms-type="text"><?= $cms->text('location_headline', 'We\'re easy to find.'); ?></h2>
        </div>

        <div class="split">
            <div>
                <h3>Our Location</h3>
                <p class="location-address">
                    <?= htmlspecialchars($site['location']); ?>
                </p>
                <div class="location-info">
                    <p><strong>Service Times:</strong><br>
                    <?= htmlspecialchars($site['service_times']); ?><br>
                    <small><?= htmlspecialchars($site['service_details']); ?></small></p>

                    <?php if (!empty($site['phone'])): ?>
                    <p><strong>Phone:</strong><br>
                    <a href="tel:<?= preg_replace('/\s+/', '', $site['phone']); ?>">
                        <?= htmlspecialchars($site['phone']); ?>
                    </a></p>
                    <?php endif; ?>
                </div>
                <a class="btn btn-primary" href="<?= htmlspecialchars($site['maps_url']); ?>"
                   target="_blank" rel="noopener">Get Directions</a>
            </div>

            <div class="map-embed">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2422.8!2d1.2933!3d52.6309!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2sNelson%20Street%20Norwich%20NR2%204DR!5e0!3m2!1sen!2suk!4v1234567890"
                    width="100%"
                    height="400"
                    style="border:0; border-radius: 1rem;"
                    allowfullscreen=""
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>
    </div>
</section>

<!-- What to Expect Timeline -->
<section class="what-to-expect">
    <div class="container">
        <div class="section-heading">
            <h2 data-cms-editable="timeline_headline" data-cms-page="visit" data-cms-type="text"><?= $cms->text('timeline_headline', 'What to expect on Sunday'); ?></h2>
        </div>

        <div class="timeline">
            <div class="timeline-item">
                <div class="timeline-icon">🚗</div>
                <div class="timeline-content">
                    <h3 data-cms-editable="timeline1_title" data-cms-page="visit" data-cms-type="text"><?= $cms->text('timeline1_title', 'Arriving'); ?></h3>
                    <p data-cms-editable="timeline1_text" data-cms-page="visit" data-cms-type="text"><?= $cms->text('timeline1_text', 'Look for the big church on the intersection between Nelson Street and Arms Street - you can\'t miss it.'); ?></p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-icon">☕</div>
                <div class="timeline-content">
                    <h3 data-cms-editable="timeline2_title" data-cms-page="visit" data-cms-type="text"><?= $cms->text('timeline2_title', 'Grab Coffee'); ?></h3>
                    <p data-cms-editable="timeline2_text" data-cms-page="visit" data-cms-type="text"><?= $cms->text('timeline2_text', 'Free coffee, tea, and pastries before the service. Our host team will greet you and answer questions.'); ?></p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-icon">👶</div>
                <div class="timeline-content">
                    <h3 data-cms-editable="timeline3_title" data-cms-page="visit" data-cms-type="text"><?= $cms->text('timeline3_title', 'Kids Church'); ?></h3>
                    <p data-cms-editable="timeline3_text" data-cms-page="visit" data-cms-type="text"><?= $cms->text('timeline3_text', 'Kids join us for worship and then go out to their special kids programme just after worship. No need to register.'); ?></p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-icon">🎵</div>
                <div class="timeline-content">
                    <h3 data-cms-editable="timeline4_title" data-cms-page="visit" data-cms-type="text"><?= $cms->text('timeline4_title', 'The Service'); ?></h3>
                    <p data-cms-editable="timeline4_text" data-cms-page="visit" data-cms-type="text"><?= $cms->text('timeline4_text', '60-75 minutes of worship, teaching, and prayer. Lyrics on screens, seats in back if you prefer. No pressure to participate.'); ?></p>
                </div>
            </div>

            <div class="timeline-item">
                <div class="timeline-icon">🤝</div>
                <div class="timeline-content">
                    <h3 data-cms-editable="timeline5_title" data-cms-page="visit" data-cms-type="text"><?= $cms->text('timeline5_title', 'Connect After'); ?></h3>
                    <p data-cms-editable="timeline5_text" data-cms-page="visit" data-cms-type="text"><?= $cms->text('timeline5_text', 'Stick around for more coffee and conversation in our cafe. Our team can help you find groups, serve opportunities, or next steps.'); ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- What Makes Us Different -->
<section class="why-alive-section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow" data-cms-editable="why_eyebrow" data-cms-page="visit" data-cms-type="text"><?= $cms->text('why_eyebrow', 'Why Alive Church?'); ?></p>
            <h2 data-cms-editable="why_headline" data-cms-page="visit" data-cms-type="text"><?= $cms->text('why_headline', 'What makes us different'); ?></h2>
        </div>

        <div class="why-cards">
            <div class="why-card">
                <div class="why-card-icon">❤️</div>
                <h3 data-cms-editable="why1_title" data-cms-page="visit" data-cms-type="text"><?= $cms->text('why1_title', 'Real Community'); ?></h3>
                <p data-cms-editable="why1_text" data-cms-page="visit" data-cms-type="text"><?= $cms->text('why1_text', 'We\'re a family, not a performance. Expect authentic relationships, genuine conversations, and people who will actually remember your name.'); ?></p>
            </div>

            <div class="why-card">
                <div class="why-card-icon">📖</div>
                <h3 data-cms-editable="why2_title" data-cms-page="visit" data-cms-type="text"><?= $cms->text('why2_title', 'Bible-Centered Teaching'); ?></h3>
                <p data-cms-editable="why2_text" data-cms-page="visit" data-cms-type="text"><?= $cms->text('why2_text', 'Every message is rooted in Scripture and designed to help you apply God\'s Word to your everyday life. Practical, relevant, and life-changing.'); ?></p>
            </div>

            <div class="why-card">
                <div class="why-card-icon">🙌</div>
                <h3 data-cms-editable="why3_title" data-cms-page="visit" data-cms-type="text"><?= $cms->text('why3_title', 'Spirit-Led Worship'); ?></h3>
                <p data-cms-editable="why3_text" data-cms-page="visit" data-cms-type="text"><?= $cms->text('why3_text', 'Our worship is passionate but accessible. Whether you\'re a hands-raised worshipper or prefer to observe quietly, there\'s room for you.'); ?></p>
            </div>

            <div class="why-card">
                <div class="why-card-icon">🌍</div>
                <h3 data-cms-editable="why4_title" data-cms-page="visit" data-cms-type="text"><?= $cms->text('why4_title', 'Outward Focused'); ?></h3>
                <p data-cms-editable="why4_text" data-cms-page="visit" data-cms-type="text"><?= $cms->text('why4_text', 'We exist to love Norwich and beyond. From local community projects to global missions, we believe the church should be a blessing to the world.'); ?></p>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section">
    <div class="container narrow">
        <div class="section-heading">
            <h2 data-cms-editable="faq_headline" data-cms-page="visit" data-cms-type="text"><?= $cms->text('faq_headline', 'Common questions'); ?></h2>
        </div>

        <div class="faq-list" data-cms-editable="faq_content" data-cms-page="visit" data-cms-type="html">
            <?= $cms->html('faq_content', '<details class="faq-item">
                <summary>What should I wear?</summary>
                <p>Come as you are! You\'ll see everything from jeans and trainers to dresses and suits. We care more about you than your outfit.</p>
            </details>

            <details class="faq-item">
                <summary>Is there parking?</summary>
                <p>Yes! There is limited parking directly outside the church, and a large free carpark further down Arms Street with plenty of space available.</p>
            </details>

            <details class="faq-item">
                <summary>What about my kids?</summary>
                <p>They can join you, or head up to kids church - either is fine with us!</p>
            </details>

            <details class="faq-item">
                <summary>How long is the service?</summary>
                <p>Plan for 60-75 minutes including worship, teaching, and prayer. You\'re welcome to arrive a few minutes late or leave early if needed.</p>
            </details>

            <details class="faq-item">
                <summary>Will I be asked to give money?</summary>
                <p>We take up an offering for our church family, this is optional for everyone and first time visitors are not under any pressure to give.</p>
            </details>

            <details class="faq-item">
                <summary>Can I bring my service dog?</summary>
                <p>Absolutely! Service animals are always welcome. Please let us know when you register so we can prepare and assist as needed.</p>
            </details>'); ?>
        </div>
    </div>
</section>

<!-- Next Steps After Your Visit -->
<section class="next-steps-preview">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow" data-cms-editable="nextsteps_eyebrow" data-cms-page="visit" data-cms-type="text"><?= $cms->text('nextsteps_eyebrow', 'After Your Visit'); ?></p>
            <h2 data-cms-editable="nextsteps_headline" data-cms-page="visit" data-cms-type="text"><?= $cms->text('nextsteps_headline', 'Ready for more?'); ?></h2>
            <p data-cms-editable="nextsteps_subtext" data-cms-page="visit" data-cms-type="text"><?= $cms->text('nextsteps_subtext', 'Once you\'ve visited, here are some great ways to take your next step with us.'); ?></p>
        </div>

        <div class="next-steps-cards">
            <a href="/groups" class="next-step-card">
                <div class="next-step-icon">👥</div>
                <h3>Join a Group</h3>
                <p>Life is better together. Find a group that fits your stage of life.</p>
                <span class="next-step-link">Explore Groups →</span>
            </a>

            <a href="/serve" class="next-step-card">
                <div class="next-step-icon">🤲</div>
                <h3>Serve With Us</h3>
                <p>Use your gifts to make a difference. There's a place for everyone.</p>
                <span class="next-step-link">Find Your Fit →</span>
            </a>

            <a href="/next-steps/baptism" class="next-step-card">
                <div class="next-step-icon">💧</div>
                <h3>Get Baptized</h3>
                <p>Ready to declare your faith? Baptism is your next step.</p>
                <span class="next-step-link">Learn More →</span>
            </a>

            <a href="/next-steps" class="next-step-card">
                <div class="next-step-icon">🚀</div>
                <h3>Growth Track</h3>
                <p>Discover your purpose and find your place in the church.</p>
                <span class="next-step-link">Start Your Journey →</span>
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

