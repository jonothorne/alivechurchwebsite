<?php
require __DIR__ . '/config.php';
$page_title = 'Community Guidelines | ' . $site['name'];

// Initialize CMS
require_once __DIR__ . '/includes/cms/ContentManager.php';
$cms = new ContentManager('community-guidelines');

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow" data-cms-editable="hero_eyebrow" data-cms-page="community-guidelines" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Guidelines'); ?></p>
        <h1 data-cms-editable="hero_title" data-cms-page="community-guidelines" data-cms-type="text"><?= $cms->text('hero_title', 'Community Guidelines'); ?></h1>
        <p data-cms-editable="hero_subtitle" data-cms-page="community-guidelines" data-cms-type="text"><?= $cms->text('hero_subtitle', 'Creating a welcoming and respectful online community.'); ?></p>
    </div>
</section>

<section class="legal-content">
    <div class="container narrow" data-cms-editable="content" data-cms-page="community-guidelines" data-cms-type="html">
        <p class="legal-updated">Last updated: <?= date('F j, Y'); ?></p>

        <h2>Welcome to Our Community</h2>
        <p><?= htmlspecialchars($site['name']); ?> welcomes people from all backgrounds and walks of life. Our online spaces—including comments on sermons, blog posts, and social media—should reflect the same warmth, grace, and respect we show in person.</p>
        <p>These guidelines help us maintain a positive environment where everyone can engage, learn, and grow together.</p>

        <h2>Our Core Values</h2>
        <div class="rights-grid">
            <div class="right-card">
                <h3>Respect</h3>
                <p>Treat others as you would like to be treated, even when you disagree.</p>
            </div>
            <div class="right-card">
                <h3>Grace</h3>
                <p>Extend the same grace to others that God extends to us.</p>
            </div>
            <div class="right-card">
                <h3>Truth</h3>
                <p>Speak truth with love, seeking understanding rather than winning arguments.</p>
            </div>
            <div class="right-card">
                <h3>Encouragement</h3>
                <p>Build each other up and celebrate what God is doing in our lives.</p>
            </div>
        </div>

        <h2>Expected Behaviour</h2>
        <p>We encourage you to:</p>
        <ul>
            <li><strong>Be welcoming:</strong> Make newcomers feel included and valued</li>
            <li><strong>Be constructive:</strong> Share thoughts that add value to the conversation</li>
            <li><strong>Be curious:</strong> Ask questions to understand, not to challenge</li>
            <li><strong>Be patient:</strong> Remember that tone can be hard to convey in text</li>
            <li><strong>Be gracious:</strong> Give others the benefit of the doubt</li>
            <li><strong>Be relevant:</strong> Keep comments on topic</li>
            <li><strong>Be authentic:</strong> Share your genuine thoughts and experiences</li>
        </ul>

        <h2>Prohibited Content</h2>
        <p>The following content is not permitted and will be removed:</p>

        <h3>Harmful Content</h3>
        <ul>
            <li>Hate speech or discrimination based on race, ethnicity, gender, disability, sexual orientation, or any other characteristic</li>
            <li>Bullying, harassment, or personal attacks</li>
            <li>Threats of violence or harm</li>
            <li>Content that promotes self-harm or suicide</li>
        </ul>

        <h3>Inappropriate Content</h3>
        <ul>
            <li>Profanity, obscenity, or vulgar language</li>
            <li>Sexual or explicit content</li>
            <li>Graphic violence or disturbing imagery</li>
            <li>Content inappropriate for all ages</li>
        </ul>

        <h3>Deceptive Content</h3>
        <ul>
            <li>Misinformation or false claims</li>
            <li>Impersonating others</li>
            <li>Spam or repetitive content</li>
            <li>Promotional content or advertising without permission</li>
        </ul>

        <h3>Off-Topic Content</h3>
        <ul>
            <li>Political campaigning or partisan content</li>
            <li>Conspiracy theories</li>
            <li>Content unrelated to the discussion</li>
            <li>Solicitations or fundraising requests</li>
        </ul>

        <h2>Theological Discussions</h2>
        <p>We welcome thoughtful theological discussion. When engaging in these conversations:</p>
        <ul>
            <li>Share your perspective without attacking others' faith</li>
            <li>Recognise that sincere Christians may interpret Scripture differently</li>
            <li>Focus on what unites us in Christ rather than what divides</li>
            <li>Ask questions to understand rather than to prove a point</li>
            <li>Avoid "proof-texting" or taking Scripture out of context</li>
            <li>Remember that the goal is growth, not winning</li>
        </ul>

        <h2>Disagreement and Conflict</h2>
        <p>Disagreement is natural and can be healthy. When you disagree:</p>
        <ul>
            <li>Challenge ideas, not people</li>
            <li>Assume good intentions</li>
            <li>Use "I" statements ("I think..." rather than "You're wrong...")</li>
            <li>Know when to step away from a conversation</li>
            <li>Take heated discussions to private messages or contact our team</li>
        </ul>

        <h2>Prayer Requests and Personal Sharing</h2>
        <p>We encourage sharing prayer requests and personal testimonies. Please:</p>
        <ul>
            <li>Only share information about others with their permission</li>
            <li>Be mindful that posts may be visible publicly</li>
            <li>Avoid sharing sensitive personal details (addresses, financial information)</li>
            <li>Report any concerning content to our team</li>
        </ul>

        <h2>Moderation</h2>
        <p>Our team moderates comments and posts to maintain a positive community. We may:</p>
        <ul>
            <li><strong>Approve:</strong> Most comments are approved automatically or after brief review</li>
            <li><strong>Edit:</strong> In rare cases, we may edit comments to remove specific content while preserving the message</li>
            <li><strong>Remove:</strong> Comments that violate these guidelines will be removed</li>
            <li><strong>Restrict:</strong> Repeated violations may result in temporary or permanent posting restrictions</li>
        </ul>

        <h2>Reporting Concerns</h2>
        <p>If you see content that violates these guidelines:</p>
        <ul>
            <li>Use the "Report" function where available</li>
            <li>Contact us directly at <?= htmlspecialchars($site['email']); ?></li>
            <li>Do not engage with trolls or hostile users—report and move on</li>
        </ul>
        <p>We review all reports and take appropriate action.</p>

        <h2>Privacy</h2>
        <p>Respect the privacy of others:</p>
        <ul>
            <li>Do not share private conversations publicly</li>
            <li>Do not share others' personal information without consent</li>
            <li>Remember that comments may be visible to a wide audience</li>
        </ul>

        <h2>Children and Young People</h2>
        <p>Our online platforms are intended for adults (18+) unless otherwise stated. If you are under 18:</p>
        <ul>
            <li>We recommend having a parent or guardian aware of your online activity</li>
            <li>Do not share personal information</li>
            <li>Report any inappropriate contact to a trusted adult</li>
        </ul>

        <h2>Changes to Guidelines</h2>
        <p>We may update these guidelines as our community grows and evolves. Significant changes will be communicated through our usual channels.</p>

        <h2>Contact Us</h2>
        <p>Questions about these guidelines? Want to report a concern?</p>
        <ul class="contact-details">
            <li><strong>Email:</strong> <?= htmlspecialchars($site['email']); ?></li>
            <li><strong>Address:</strong> <?= htmlspecialchars($site['location']); ?></li>
        </ul>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
