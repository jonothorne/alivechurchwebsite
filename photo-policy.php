<?php
require __DIR__ . '/config.php';
$page_title = 'Photo & Video Policy | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow">Policy</p>
        <h1>Photo & Video Policy</h1>
        <p>How we capture and use images and recordings at <?= htmlspecialchars($site['name']); ?>.</p>
    </div>
</section>

<section class="legal-content">
    <div class="container narrow">
        <p class="legal-updated">Last updated: <?= date('F j, Y'); ?></p>

        <h2>Introduction</h2>
        <p><?= htmlspecialchars($site['name']); ?> regularly captures photographs and video recordings during our services, events, and activities. This policy explains how we use these images and how you can manage your preferences.</p>

        <h2>Why We Capture Images</h2>
        <p>We use photographs and videos to:</p>
        <ul>
            <li>Share the life of our church on our website and social media</li>
            <li>Create promotional materials for events and services</li>
            <li>Document church history and milestones</li>
            <li>Provide online sermon recordings and livestreams</li>
            <li>Create resources for church communications</li>
        </ul>

        <h2>Where Images May Be Used</h2>
        <p>Images captured at our church may appear in:</p>
        <ul>
            <li>Our church website</li>
            <li>Social media platforms (Facebook, Instagram, YouTube)</li>
            <li>Church newsletters and bulletins</li>
            <li>Printed promotional materials</li>
            <li>Presentation slides during services</li>
            <li>Video recordings of services (available online)</li>
        </ul>

        <h2>Consent at Services and Events</h2>
        <p>By attending our services and events, you acknowledge that photography and filming may take place. We display notices at our venue entrance informing attendees of this.</p>
        <p>We rely on legitimate interest as our lawful basis for general photography at public church gatherings. However, we respect your right to privacy and offer opt-out options.</p>

        <h2>Opting Out</h2>
        <p>If you do not wish to be photographed or filmed:</p>
        <ul>
            <li><strong>Before an event:</strong> Let a member of our welcome team know when you arrive</li>
            <li><strong>During an event:</strong> Speak to any team member or sit in designated "no photo" areas where available</li>
            <li><strong>After publication:</strong> Contact us to request removal of specific images</li>
        </ul>
        <p>We will make reasonable efforts to accommodate your preferences, though we cannot guarantee that you will not appear in the background of wide-angle shots.</p>

        <h2>Children and Young People</h2>
        <p>We take extra care with images of children and young people:</p>
        <ul>
            <li>We obtain parental/guardian consent for any child registered with our children's or youth programmes</li>
            <li>Consent forms are provided during registration</li>
            <li>We do not identify children by name alongside their photo without specific consent</li>
            <li>Parents/guardians may update consent preferences at any time</li>
            <li>We avoid images that could be considered inappropriate or that show children in vulnerable situations</li>
        </ul>

        <h2>Live Streaming</h2>
        <p>Our services are livestreamed online. During livestreams:</p>
        <ul>
            <li>Cameras primarily focus on the stage/platform area</li>
            <li>Congregation shots may be included but are typically wide-angle</li>
            <li>If you prefer not to appear on livestream, please sit outside the main camera angles (our welcome team can advise)</li>
            <li>Recordings remain available on our YouTube channel</li>
        </ul>

        <h2>Photography by Attendees</h2>
        <p>We ask that attendees taking their own photos or videos:</p>
        <ul>
            <li>Respect others' privacy</li>
            <li>Do not photograph children who are not their own without permission</li>
            <li>Avoid disruptive flash photography during services</li>
            <li>Consider others if sharing images on social media</li>
            <li>Follow any specific guidance given for particular events</li>
        </ul>

        <h2>Professional Photography</h2>
        <p>For weddings, dedications, and other special occasions:</p>
        <ul>
            <li>Professional photographers must be approved in advance</li>
            <li>Specific agreements will be made regarding image usage</li>
            <li>Photographers must respect the privacy of other attendees</li>
        </ul>

        <h2>Image Storage and Retention</h2>
        <p>Images are:</p>
        <ul>
            <li>Stored securely on church systems and approved cloud services</li>
            <li>Retained for as long as they remain useful for church purposes</li>
            <li>Periodically reviewed and deleted when no longer needed</li>
            <li>Not shared with third parties except as described in this policy</li>
        </ul>

        <h2>Your Rights</h2>
        <p>Under data protection law, you have the right to:</p>
        <ul>
            <li>Request access to images we hold of you</li>
            <li>Request removal of images from our website and social media</li>
            <li>Object to the use of your image</li>
            <li>Update your consent preferences at any time</li>
        </ul>
        <p>Please note that once images are published online, we cannot control copies made by third parties.</p>

        <h2>Safeguarding</h2>
        <p>This policy operates in conjunction with our <a href="/safeguarding">Safeguarding Policy</a>. Any concerns about the inappropriate use of images should be reported to our Safeguarding Coordinator.</p>

        <h2>Contact Us</h2>
        <p>To update your preferences, request image removal, or ask questions:</p>
        <ul class="contact-details">
            <li><strong>Email:</strong> <?= htmlspecialchars($site['email']); ?></li>
            <li><strong>Address:</strong> <?= htmlspecialchars($site['location']); ?></li>
        </ul>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
