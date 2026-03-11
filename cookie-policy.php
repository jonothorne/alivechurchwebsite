<?php
require __DIR__ . '/config.php';
$page_title = 'Cookie Policy | ' . $site['name'];

// Initialize CMS
require_once __DIR__ . '/includes/cms/ContentManager.php';
$cms = new ContentManager('cookie-policy');

include __DIR__ . '/includes/header.php';

$default_content = '
<p class="legal-updated">Last updated: ' . date('F j, Y') . '</p>

<h2>1. What Are Cookies?</h2>
<p>Cookies are small text files that are stored on your device (computer, tablet, or mobile) when you visit a website. They are widely used to make websites work more efficiently and provide information to website owners.</p>

<h2>2. How We Use Cookies</h2>
<p>' . htmlspecialchars($site['name']) . ' uses cookies to:</p>
<ul>
    <li>Keep you signed in to your account</li>
    <li>Remember your preferences (such as theme settings)</li>
    <li>Understand how you use our website</li>
    <li>Improve our website and services</li>
    <li>Ensure website security</li>
</ul>

<h2>3. Types of Cookies We Use</h2>

<h3>Strictly Necessary Cookies</h3>
<p>These cookies are essential for the website to function properly. They enable core functionality such as security, account access, and remembering your cookie consent preferences.</p>
<table class="cookie-table">
    <thead>
        <tr>
            <th>Cookie Name</th>
            <th>Purpose</th>
            <th>Duration</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>PHPSESSID</td>
            <td>Maintains your session while browsing the site</td>
            <td>Session</td>
        </tr>
        <tr>
            <td>cookie_consent</td>
            <td>Stores your cookie consent preferences</td>
            <td>1 year</td>
        </tr>
    </tbody>
</table>

<h3>Functional Cookies</h3>
<p>These cookies enable enhanced functionality and personalisation, such as remembering your preferences.</p>
<table class="cookie-table">
    <thead>
        <tr>
            <th>Cookie Name</th>
            <th>Purpose</th>
            <th>Duration</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>theme</td>
            <td>Remembers your light/dark mode preference</td>
            <td>1 year</td>
        </tr>
        <tr>
            <td>font_size</td>
            <td>Remembers your preferred font size for reading</td>
            <td>1 year</td>
        </tr>
    </tbody>
</table>

<h3>Analytics Cookies</h3>
<p>These cookies help us understand how visitors interact with our website by collecting and reporting information anonymously.</p>
<table class="cookie-table">
    <thead>
        <tr>
            <th>Cookie Name</th>
            <th>Purpose</th>
            <th>Duration</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>_ga</td>
            <td>Google Analytics - distinguishes unique users</td>
            <td>2 years</td>
        </tr>
        <tr>
            <td>_gid</td>
            <td>Google Analytics - distinguishes unique users</td>
            <td>24 hours</td>
        </tr>
    </tbody>
</table>

<h2>4. Third-Party Cookies</h2>
<p>Some cookies are placed by third-party services that appear on our pages. We use the following third-party services:</p>
<ul>
    <li><strong>YouTube:</strong> For embedded video content (sermons, teaching)</li>
    <li><strong>Google Analytics:</strong> For website usage statistics</li>
    <li><strong>Payment Processors:</strong> For secure donation processing</li>
</ul>
<p>These third parties may set their own cookies. Please refer to their respective privacy policies for more information.</p>

<h2>5. Managing Cookies</h2>
<p>You can control and manage cookies in several ways:</p>

<h3>Cookie Consent</h3>
<p>When you first visit our website, you\'ll see a cookie consent banner. You can choose to accept or decline non-essential cookies.</p>

<h3>Browser Settings</h3>
<p>Most web browsers allow you to control cookies through their settings. You can:</p>
<ul>
    <li>View cookies stored on your device</li>
    <li>Delete cookies</li>
    <li>Block cookies from specific or all websites</li>
    <li>Set preferences for certain types of cookies</li>
</ul>
<p>Please note that blocking certain cookies may affect the functionality of our website.</p>

<h3>Browser-Specific Instructions</h3>
<ul>
    <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Google Chrome</a></li>
    <li><a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer" target="_blank" rel="noopener">Mozilla Firefox</a></li>
    <li><a href="https://support.apple.com/en-gb/guide/safari/sfri11471/mac" target="_blank" rel="noopener">Safari</a></li>
    <li><a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" rel="noopener">Microsoft Edge</a></li>
</ul>

<h2>6. Changes to This Policy</h2>
<p>We may update this Cookie Policy from time to time. Any changes will be posted on this page with an updated revision date.</p>

<h2>7. Contact Us</h2>
<p>If you have any questions about our use of cookies, please contact us:</p>
<ul class="contact-details">
    <li><strong>Email:</strong> ' . htmlspecialchars($site['email']) . '</li>
    <li><strong>Address:</strong> ' . htmlspecialchars($site['location']) . '</li>
</ul>
';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow" data-cms-editable="hero_eyebrow" data-cms-page="cookie-policy" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Legal'); ?></p>
        <h1 data-cms-editable="hero_title" data-cms-page="cookie-policy" data-cms-type="text"><?= $cms->text('hero_title', 'Cookie Policy'); ?></h1>
        <p data-cms-editable="hero_subtitle" data-cms-page="cookie-policy" data-cms-type="text"><?= $cms->text('hero_subtitle', 'How we use cookies and similar technologies on our website.'); ?></p>
    </div>
</section>

<section class="legal-content">
    <div class="container narrow" data-cms-editable="content" data-cms-page="cookie-policy" data-cms-type="html">
        <?= $cms->html('content', $default_content); ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
