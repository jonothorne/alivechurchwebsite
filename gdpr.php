<?php
require __DIR__ . '/config.php';
$page_title = 'Your Data Rights (GDPR) | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow">Legal</p>
        <h1>Your Data Rights</h1>
        <p>Understanding your rights under the General Data Protection Regulation (GDPR).</p>
    </div>
</section>

<section class="legal-content">
    <div class="container narrow">
        <p class="legal-updated">Last updated: <?= date('F j, Y'); ?></p>

        <h2>About GDPR</h2>
        <p>The General Data Protection Regulation (GDPR) is a regulation in EU law on data protection and privacy. Even after Brexit, the UK has retained equivalent protections through the UK GDPR and Data Protection Act 2018.</p>
        <p><?= htmlspecialchars($site['name']); ?> is committed to protecting your personal data and respecting your privacy rights.</p>

        <h2>Your Rights</h2>
        <p>Under data protection law, you have the following rights:</p>

        <div class="rights-grid">
            <div class="right-card">
                <h3>Right to be Informed</h3>
                <p>You have the right to know how your personal data is being used. We provide this information through our <a href="/privacy-policy">Privacy Policy</a>.</p>
            </div>

            <div class="right-card">
                <h3>Right of Access</h3>
                <p>You can request a copy of all personal data we hold about you. This is commonly known as a "Subject Access Request" (SAR).</p>
            </div>

            <div class="right-card">
                <h3>Right to Rectification</h3>
                <p>If any personal data we hold about you is inaccurate or incomplete, you have the right to have it corrected.</p>
            </div>

            <div class="right-card">
                <h3>Right to Erasure</h3>
                <p>Also known as the "right to be forgotten", you can request that we delete your personal data in certain circumstances.</p>
            </div>

            <div class="right-card">
                <h3>Right to Restrict Processing</h3>
                <p>You can ask us to limit how we use your personal data while a concern is being investigated.</p>
            </div>

            <div class="right-card">
                <h3>Right to Data Portability</h3>
                <p>You can request your data in a commonly used, machine-readable format to transfer to another organisation.</p>
            </div>

            <div class="right-card">
                <h3>Right to Object</h3>
                <p>You can object to the processing of your personal data in certain circumstances, including for direct marketing purposes.</p>
            </div>

            <div class="right-card">
                <h3>Rights Related to Automated Decision Making</h3>
                <p>You have rights regarding decisions made about you without human involvement. We do not currently use automated decision-making.</p>
            </div>
        </div>

        <h2>Our Lawful Bases for Processing</h2>
        <p>We process personal data under the following lawful bases:</p>
        <ul>
            <li><strong>Consent:</strong> Where you have given clear consent for us to process your personal data for a specific purpose (e.g., newsletter sign-up).</li>
            <li><strong>Contract:</strong> Where processing is necessary for a contract with you (e.g., event registration).</li>
            <li><strong>Legitimate Interests:</strong> Where processing is necessary for our legitimate interests, provided these are not overridden by your rights (e.g., church administration, pastoral care).</li>
            <li><strong>Legal Obligation:</strong> Where we need to comply with the law (e.g., safeguarding requirements, financial records).</li>
        </ul>

        <h2>Data We Collect</h2>
        <p>We may collect and process the following categories of personal data:</p>
        <ul>
            <li>Identity data (name, title, date of birth)</li>
            <li>Contact data (email address, phone number, postal address)</li>
            <li>Account data (username, password, preferences)</li>
            <li>Transaction data (donation history, event bookings)</li>
            <li>Technical data (IP address, browser type, device information)</li>
            <li>Usage data (how you use our website and services)</li>
            <li>Special category data (with explicit consent, such as prayer requests or pastoral care needs)</li>
        </ul>

        <h2>Data Retention</h2>
        <p>We retain personal data only for as long as necessary for the purposes for which it was collected:</p>
        <ul>
            <li><strong>Account information:</strong> Retained while your account is active, plus 2 years after closure</li>
            <li><strong>Donation records:</strong> Retained for 7 years (legal requirement)</li>
            <li><strong>Event registrations:</strong> Retained for 2 years after the event</li>
            <li><strong>Newsletter subscriptions:</strong> Until you unsubscribe</li>
            <li><strong>Website analytics:</strong> Aggregated data retained for 26 months</li>
        </ul>

        <h2>Data Security</h2>
        <p>We have implemented appropriate security measures to prevent your personal data from being accidentally lost, used, accessed, altered, or disclosed in an unauthorised way. These include:</p>
        <ul>
            <li>Encryption of data in transit (HTTPS)</li>
            <li>Secure password storage (hashing)</li>
            <li>Access controls and authentication</li>
            <li>Regular security updates</li>
            <li>Staff training on data protection</li>
        </ul>

        <h2>How to Exercise Your Rights</h2>
        <p>To exercise any of your data protection rights, please contact us using the details below. We will respond to your request within one month.</p>
        <p>To help us process your request efficiently, please:</p>
        <ul>
            <li>Clearly state which right you wish to exercise</li>
            <li>Provide enough information for us to verify your identity</li>
            <li>Specify if your request relates to specific data or all data we hold about you</li>
        </ul>

        <h2>Making a Complaint</h2>
        <p>If you are unhappy with how we have handled your personal data, you have the right to lodge a complaint with the Information Commissioner's Office (ICO):</p>
        <ul class="contact-details">
            <li><strong>Website:</strong> <a href="https://ico.org.uk" target="_blank" rel="noopener">ico.org.uk</a></li>
            <li><strong>Phone:</strong> 0303 123 1113</li>
        </ul>
        <p>However, we would appreciate the opportunity to address your concerns before you approach the ICO, so please contact us in the first instance.</p>

        <h2>Contact Our Data Protection Lead</h2>
        <p>For any questions about data protection or to exercise your rights:</p>
        <ul class="contact-details">
            <li><strong>Email:</strong> <?= htmlspecialchars($site['email']); ?></li>
            <li><strong>Address:</strong> <?= htmlspecialchars($site['location']); ?></li>
        </ul>
        <p>Please mark any correspondence "Data Protection Request".</p>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
