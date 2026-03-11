<?php
require __DIR__ . '/config.php';
$page_title = 'Terms of Service | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow">Legal</p>
        <h1>Terms of Service</h1>
        <p>The terms and conditions governing your use of our website and services.</p>
    </div>
</section>

<section class="legal-content">
    <div class="container narrow">
        <p class="legal-updated">Last updated: <?= date('F j, Y'); ?></p>

        <h2>1. Acceptance of Terms</h2>
        <p>By accessing and using the <?= htmlspecialchars($site['name']); ?> website and services, you accept and agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use our website or services.</p>

        <h2>2. Description of Services</h2>
        <p><?= htmlspecialchars($site['name']); ?> provides:</p>
        <ul>
            <li>Information about our church, services, and events</li>
            <li>Online sermon and teaching content</li>
            <li>Bible study resources and reading plans</li>
            <li>Event registration and group sign-ups</li>
            <li>Online giving and donation facilities</li>
            <li>Prayer request submission</li>
            <li>Newsletter and communication services</li>
        </ul>

        <h2>3. User Accounts</h2>
        <p>Some features of our website may require you to create an account. When creating an account, you agree to:</p>
        <ul>
            <li>Provide accurate and complete information</li>
            <li>Maintain the security of your password</li>
            <li>Accept responsibility for all activities under your account</li>
            <li>Notify us immediately of any unauthorised use</li>
        </ul>
        <p>We reserve the right to suspend or terminate accounts that violate these terms.</p>

        <h2>4. Acceptable Use</h2>
        <p>When using our website and services, you agree not to:</p>
        <ul>
            <li>Use the services for any unlawful purpose</li>
            <li>Harass, abuse, or harm other users</li>
            <li>Post or transmit inappropriate, offensive, or harmful content</li>
            <li>Attempt to gain unauthorised access to our systems</li>
            <li>Interfere with or disrupt the services</li>
            <li>Use automated systems to access the website without permission</li>
            <li>Impersonate others or misrepresent your affiliation</li>
        </ul>

        <h2>5. Content and Intellectual Property</h2>
        <p>All content on this website, including text, images, videos, sermons, and study materials, is the property of <?= htmlspecialchars($site['name']); ?> or its content providers and is protected by copyright laws.</p>
        <p>You may:</p>
        <ul>
            <li>View and listen to content for personal, non-commercial use</li>
            <li>Share links to our content</li>
            <li>Print materials for personal study</li>
        </ul>
        <p>You may not:</p>
        <ul>
            <li>Reproduce, distribute, or sell our content without permission</li>
            <li>Modify or create derivative works</li>
            <li>Remove copyright or proprietary notices</li>
        </ul>

        <h2>6. User-Generated Content</h2>
        <p>When you submit content to our website (such as comments, prayer requests, or testimonies), you:</p>
        <ul>
            <li>Grant us a non-exclusive licence to use, display, and share that content</li>
            <li>Confirm the content is your own or you have permission to share it</li>
            <li>Accept responsibility for your submissions</li>
        </ul>
        <p>We reserve the right to remove any content that violates these terms or is otherwise inappropriate.</p>

        <h2>7. Donations</h2>
        <p>All donations made through our website are:</p>
        <ul>
            <li>Voluntary and made at the donor's discretion</li>
            <li>Processed securely through third-party payment providers</li>
            <li>Used in accordance with our charitable purposes</li>
            <li>Subject to our refund policy (contact us for details)</li>
        </ul>
        <p><?= htmlspecialchars($site['name']); ?> is a registered charity. Gift Aid declarations and tax receipts are available where applicable.</p>

        <h2>8. Third-Party Links</h2>
        <p>Our website may contain links to third-party websites. We are not responsible for the content, privacy practices, or terms of service of these external sites. We encourage you to review their policies before providing any personal information.</p>

        <h2>9. Disclaimer of Warranties</h2>
        <p>Our website and services are provided "as is" without warranties of any kind, either express or implied. We do not guarantee that:</p>
        <ul>
            <li>The services will be uninterrupted or error-free</li>
            <li>Defects will be corrected</li>
            <li>The website is free of viruses or harmful components</li>
        </ul>

        <h2>10. Limitation of Liability</h2>
        <p><?= htmlspecialchars($site['name']); ?> shall not be liable for any indirect, incidental, special, or consequential damages arising from your use of our website or services.</p>

        <h2>11. Changes to Terms</h2>
        <p>We reserve the right to modify these terms at any time. Changes will be effective immediately upon posting to this page. Your continued use of our services constitutes acceptance of any changes.</p>

        <h2>12. Governing Law</h2>
        <p>These Terms of Service are governed by and construed in accordance with the laws of England and Wales. Any disputes shall be subject to the exclusive jurisdiction of the courts of England and Wales.</p>

        <h2>13. Contact Us</h2>
        <p>If you have any questions about these Terms of Service, please contact us:</p>
        <ul class="contact-details">
            <li><strong>Email:</strong> <?= htmlspecialchars($site['email']); ?></li>
            <li><strong>Address:</strong> <?= htmlspecialchars($site['location']); ?></li>
        </ul>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
