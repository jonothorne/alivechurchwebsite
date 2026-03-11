<?php
require __DIR__ . '/config.php';
$page_title = 'Accessibility Statement | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow">Accessibility</p>
        <h1>Accessibility Statement</h1>
        <p>Our commitment to making this website accessible to everyone.</p>
    </div>
</section>

<section class="legal-content">
    <div class="container narrow">
        <p class="legal-updated">Last updated: <?= date('F j, Y'); ?></p>

        <h2>Our Commitment</h2>
        <p><?= htmlspecialchars($site['name']); ?> is committed to ensuring digital accessibility for people with disabilities. We are continually improving the user experience for everyone and applying the relevant accessibility standards.</p>

        <h2>Conformance Status</h2>
        <p>We aim to conform to the Web Content Accessibility Guidelines (WCAG) 2.1 at Level AA. These guidelines explain how to make web content more accessible for people with disabilities and more user-friendly for everyone.</p>

        <h2>Accessibility Features</h2>
        <p>We have implemented the following features to improve accessibility:</p>

        <h3>Navigation and Structure</h3>
        <ul>
            <li>Clear, consistent navigation throughout the site</li>
            <li>Logical heading structure (H1, H2, H3, etc.)</li>
            <li>Skip to main content link</li>
            <li>Breadcrumb navigation where appropriate</li>
            <li>Sitemap available</li>
        </ul>

        <h3>Visual Design</h3>
        <ul>
            <li>Sufficient colour contrast between text and backgrounds</li>
            <li>Text can be resized up to 200% without loss of functionality</li>
            <li>Dark mode option for reduced eye strain</li>
            <li>No content flashes more than three times per second</li>
        </ul>

        <h3>Images and Media</h3>
        <ul>
            <li>Alternative text (alt text) for meaningful images</li>
            <li>Decorative images are marked appropriately</li>
            <li>Video content includes captions where available</li>
        </ul>

        <h3>Forms and Interactive Elements</h3>
        <ul>
            <li>All form fields have associated labels</li>
            <li>Error messages are clear and descriptive</li>
            <li>Focus indicators are visible for keyboard users</li>
            <li>Interactive elements are keyboard accessible</li>
        </ul>

        <h3>Content</h3>
        <ul>
            <li>Plain language used where possible</li>
            <li>Links are descriptive (avoiding "click here")</li>
            <li>PDF documents are avoided where possible; HTML alternatives provided</li>
        </ul>

        <h2>Keyboard Navigation</h2>
        <p>All functionality on this website is accessible using a keyboard. You can use the following keys:</p>
        <ul>
            <li><strong>Tab:</strong> Move forward through interactive elements</li>
            <li><strong>Shift + Tab:</strong> Move backward through interactive elements</li>
            <li><strong>Enter:</strong> Activate buttons and links</li>
            <li><strong>Escape:</strong> Close modals and popups</li>
            <li><strong>Arrow keys:</strong> Navigate within menus and some components</li>
        </ul>

        <h2>Assistive Technologies</h2>
        <p>This website is designed to be compatible with:</p>
        <ul>
            <li>Screen readers (JAWS, NVDA, VoiceOver, TalkBack)</li>
            <li>Screen magnification software</li>
            <li>Speech recognition software</li>
            <li>Keyboard-only navigation</li>
        </ul>

        <h2>Browser Compatibility</h2>
        <p>This website is designed to work with recent versions of the following browsers:</p>
        <ul>
            <li>Google Chrome</li>
            <li>Mozilla Firefox</li>
            <li>Apple Safari</li>
            <li>Microsoft Edge</li>
        </ul>

        <h2>Known Limitations</h2>
        <p>While we strive for full accessibility, some content may have limitations:</p>
        <ul>
            <li><strong>Third-party content:</strong> Embedded content from YouTube and other platforms may not fully meet accessibility standards.</li>
            <li><strong>Older PDF documents:</strong> Some older documents may not be fully accessible. Contact us if you need an alternative format.</li>
            <li><strong>Live video streams:</strong> Live content may not have captions available in real-time.</li>
        </ul>

        <h2>Feedback and Contact</h2>
        <p>We welcome your feedback on the accessibility of this website. If you encounter any accessibility barriers or have suggestions for improvement, please contact us:</p>
        <ul class="contact-details">
            <li><strong>Email:</strong> <?= htmlspecialchars($site['email']); ?></li>
            <li><strong>Address:</strong> <?= htmlspecialchars($site['location']); ?></li>
        </ul>
        <p>We aim to respond to accessibility feedback within 5 working days.</p>

        <h2>Alternative Formats</h2>
        <p>If you need information from this website in a different format (such as large print, audio, or easy read), please contact us and we will do our best to accommodate your request.</p>

        <h2>Enforcement Procedure</h2>
        <p>If you are not satisfied with our response to your accessibility concern, you can contact the Equality Advisory Support Service (EASS):</p>
        <ul class="contact-details">
            <li><strong>Website:</strong> <a href="https://www.equalityadvisoryservice.com/" target="_blank" rel="noopener">equalityadvisoryservice.com</a></li>
            <li><strong>Phone:</strong> 0808 800 0082</li>
        </ul>

        <h2>Technical Specifications</h2>
        <p>This website relies on the following technologies for accessibility:</p>
        <ul>
            <li>HTML5</li>
            <li>WAI-ARIA</li>
            <li>CSS</li>
            <li>JavaScript</li>
        </ul>

        <h2>Assessment and Testing</h2>
        <p>This website has been assessed using:</p>
        <ul>
            <li>Automated accessibility testing tools</li>
            <li>Manual keyboard navigation testing</li>
            <li>Screen reader testing</li>
            <li>Colour contrast analysis</li>
        </ul>

        <h2>Continuous Improvement</h2>
        <p>We are committed to continually improving the accessibility of our website. This statement will be reviewed and updated regularly.</p>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
