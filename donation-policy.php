<?php
require __DIR__ . '/config.php';
$page_title = 'Donation Policy | ' . $site['name'];

// Initialize CMS
require_once __DIR__ . '/includes/cms/ContentManager.php';
$cms = new ContentManager('donation-policy');

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow" data-cms-editable="hero_eyebrow" data-cms-page="donation-policy" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Policy'); ?></p>
        <h1 data-cms-editable="hero_title" data-cms-page="donation-policy" data-cms-type="text"><?= $cms->text('hero_title', 'Donation Policy'); ?></h1>
        <p data-cms-editable="hero_subtitle" data-cms-page="donation-policy" data-cms-type="text"><?= $cms->text('hero_subtitle', 'Information about giving to ' . htmlspecialchars($site['name']) . ' and how your donations are used.'); ?></p>
    </div>
</section>

<section class="legal-content">
    <div class="container narrow" data-cms-editable="content" data-cms-page="donation-policy" data-cms-type="html">
        <p class="legal-updated">Last updated: <?= date('F j, Y'); ?></p>

        <h2>Thank You for Your Generosity</h2>
        <p><?= htmlspecialchars($site['name']); ?> is grateful for every gift we receive. Your generosity enables us to continue our mission of sharing the love of Jesus and serving our community.</p>

        <h2>Charitable Status</h2>
        <p><?= htmlspecialchars($site['name']); ?> is a registered charity. All donations are used in accordance with our charitable purposes:</p>
        <ul>
            <li>The advancement of the Christian faith</li>
            <li>The relief of poverty and hardship</li>
            <li>Community outreach and support</li>
            <li>Education and discipleship</li>
        </ul>

        <h2>Ways to Give</h2>
        <h3>Online Giving</h3>
        <p>You can make a one-time or recurring donation through our secure online giving platform. We accept major credit and debit cards.</p>

        <h3>Bank Transfer</h3>
        <p>For bank transfer details, please contact our office at <?= htmlspecialchars($site['email']); ?>.</p>

        <h3>Cash and Cheques</h3>
        <p>Cash and cheque donations can be made during our services or sent to our office. Cheques should be made payable to "<?= htmlspecialchars($site['name']); ?>".</p>

        <h3>Standing Order</h3>
        <p>Regular giving by standing order helps us plan effectively. Contact our office for standing order forms.</p>

        <h2>Gift Aid</h2>
        <p>If you are a UK taxpayer, you can increase your donation by 25% at no extra cost to you through Gift Aid. For every £1 you give, we can claim an extra 25p from HMRC.</p>
        <p>To enable Gift Aid:</p>
        <ul>
            <li>Complete a Gift Aid declaration (available online or from our office)</li>
            <li>You must pay UK Income Tax or Capital Gains Tax equal to or greater than the amount claimed</li>
            <li>One declaration covers all your donations to us</li>
        </ul>

        <h2>Designated Giving</h2>
        <p>You may designate your gift for a specific purpose (such as missions, building fund, or benevolence). We will honour your designation where possible.</p>
        <p>Please note:</p>
        <ul>
            <li>If a designated fund reaches its goal, excess funds may be redirected to similar purposes</li>
            <li>If a project is cancelled, designated funds will be used for the church's general purposes unless you request otherwise</li>
            <li>The church leadership reserves the right to redirect funds if a designated project becomes impractical</li>
        </ul>

        <h2>How Your Donations Are Used</h2>
        <p>Your gifts support:</p>
        <ul>
            <li><strong>Ministry and Outreach:</strong> Services, events, community programmes, and missions</li>
            <li><strong>Staff and Leadership:</strong> Supporting those who serve full-time</li>
            <li><strong>Facilities:</strong> Maintaining and improving our building and equipment</li>
            <li><strong>Benevolence:</strong> Helping those in need in our community</li>
            <li><strong>Children and Youth:</strong> Programmes for the next generation</li>
        </ul>

        <h2>Financial Accountability</h2>
        <p>We are committed to financial integrity and transparency:</p>
        <ul>
            <li>Our accounts are independently examined annually</li>
            <li>Financial reports are submitted to the Charity Commission</li>
            <li>We follow best practices for financial management</li>
            <li>Summary financial information is available to members upon request</li>
        </ul>

        <h2>Refund Policy</h2>
        <p>We understand that sometimes donations are made in error. Our refund policy is as follows:</p>

        <h3>Eligible for Refund</h3>
        <ul>
            <li>Duplicate transactions (charged twice for the same donation)</li>
            <li>Incorrect amount entered (e.g., £500 instead of £50)</li>
            <li>Unauthorised transactions (must be reported within 14 days)</li>
            <li>Technical errors that resulted in unintended charges</li>
        </ul>

        <h3>Refund Process</h3>
        <ol>
            <li>Contact us within 30 days of the donation</li>
            <li>Provide details of the transaction (date, amount, payment method)</li>
            <li>Explain the reason for the refund request</li>
            <li>We will process eligible refunds within 14 working days</li>
        </ol>

        <h3>Non-Refundable</h3>
        <ul>
            <li>Donations where the funds have already been used for their designated purpose</li>
            <li>Requests made more than 90 days after the donation</li>
            <li>"Change of mind" without extenuating circumstances</li>
        </ul>

        <h3>Gift Aid Implications</h3>
        <p>If a donation is refunded, any Gift Aid claimed on that donation will be returned to HMRC.</p>

        <h2>Recurring Donations</h2>
        <p>You can set up recurring donations through our online platform. You may:</p>
        <ul>
            <li>Cancel at any time through your account or by contacting us</li>
            <li>Modify the amount or frequency</li>
            <li>Pause giving temporarily</li>
        </ul>
        <p>Changes to recurring donations will take effect from the next scheduled payment date.</p>

        <h2>Donation Receipts</h2>
        <p>We provide:</p>
        <ul>
            <li>Immediate email receipt for online donations</li>
            <li>Annual giving statement for tax purposes (upon request)</li>
            <li>Gift Aid confirmation where applicable</li>
        </ul>

        <h2>Data Protection</h2>
        <p>Your donation information is handled securely in accordance with our <a href="/privacy-policy">Privacy Policy</a>. Payment processing is handled by secure, PCI-compliant payment providers.</p>

        <h2>Contact Us</h2>
        <p>For questions about donations, refunds, or Gift Aid:</p>
        <ul class="contact-details">
            <li><strong>Email:</strong> <?= htmlspecialchars($site['email']); ?></li>
            <li><strong>Address:</strong> <?= htmlspecialchars($site['location']); ?></li>
        </ul>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
