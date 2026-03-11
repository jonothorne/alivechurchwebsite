<?php
require __DIR__ . '/config.php';
$page_title = 'Privacy Policy | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow">Legal</p>
        <h1>Privacy Policy</h1>
        <p>How we collect, use, and protect your personal information.</p>
    </div>
</section>

<section class="legal-content">
    <div class="container narrow">
        <p class="legal-updated">Last updated: <?= date('F j, Y'); ?></p>

        <h2>1. Introduction</h2>
        <p><?= htmlspecialchars($site['name']); ?> ("we", "our", or "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website or use our services.</p>

        <h2>2. Information We Collect</h2>
        <h3>Personal Information</h3>
        <p>We may collect personal information that you voluntarily provide to us when you:</p>
        <ul>
            <li>Register for an account</li>
            <li>Sign up for our newsletter</li>
            <li>Submit a contact form or prayer request</li>
            <li>Register for events</li>
            <li>Make a donation</li>
            <li>Join a group or ministry</li>
        </ul>
        <p>This information may include:</p>
        <ul>
            <li>Name and email address</li>
            <li>Phone number</li>
            <li>Postal address</li>
            <li>Date of birth</li>
            <li>Payment information (processed securely through third-party providers)</li>
        </ul>

        <h3>Automatically Collected Information</h3>
        <p>When you visit our website, we may automatically collect certain information, including:</p>
        <ul>
            <li>IP address and browser type</li>
            <li>Device information</li>
            <li>Pages visited and time spent on our site</li>
            <li>Referring website addresses</li>
        </ul>

        <h2>3. How We Use Your Information</h2>
        <p>We use the information we collect to:</p>
        <ul>
            <li>Provide and maintain our services</li>
            <li>Send you newsletters, updates, and event information</li>
            <li>Process donations and event registrations</li>
            <li>Respond to your enquiries and prayer requests</li>
            <li>Improve our website and services</li>
            <li>Comply with legal obligations</li>
        </ul>

        <h2>4. Information Sharing</h2>
        <p>We do not sell, trade, or otherwise transfer your personal information to third parties, except:</p>
        <ul>
            <li>To trusted service providers who assist us in operating our website and services (e.g., payment processors, email service providers)</li>
            <li>When required by law or to protect our rights</li>
            <li>With your explicit consent</li>
        </ul>

        <h2>5. Data Security</h2>
        <p>We implement appropriate technical and organisational measures to protect your personal information against unauthorised access, alteration, disclosure, or destruction. However, no method of transmission over the internet is 100% secure.</p>

        <h2>6. Your Rights</h2>
        <p>Under data protection laws, you have the right to:</p>
        <ul>
            <li>Access your personal data</li>
            <li>Correct inaccurate data</li>
            <li>Request deletion of your data</li>
            <li>Object to processing of your data</li>
            <li>Request data portability</li>
            <li>Withdraw consent at any time</li>
        </ul>
        <p>To exercise these rights, please contact us using the details below.</p>

        <h2>7. Cookies</h2>
        <p>Our website uses cookies to enhance your browsing experience. For detailed information about how we use cookies, please see our <a href="/cookie-policy">Cookie Policy</a>.</p>

        <h2>8. Children's Privacy</h2>
        <p>Our services are not directed to individuals under 16. We do not knowingly collect personal information from children. If you become aware that a child has provided us with personal information, please contact us.</p>

        <h2>9. Changes to This Policy</h2>
        <p>We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new policy on this page and updating the "Last updated" date.</p>

        <h2>10. Contact Us</h2>
        <p>If you have any questions about this Privacy Policy, please contact us:</p>
        <ul class="contact-details">
            <li><strong>Email:</strong> <?= htmlspecialchars($site['email']); ?></li>
            <li><strong>Address:</strong> <?= htmlspecialchars($site['location']); ?></li>
        </ul>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
