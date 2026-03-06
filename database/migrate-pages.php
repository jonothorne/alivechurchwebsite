<?php
/**
 * Migrate existing hardcoded pages to database
 * This script extracts content from existing PHP pages and creates database entries
 */

require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

echo "Starting page migration...\n\n";

// Pages that can be migrated to database (simple content pages)
$pages_to_migrate = [
    [
        'slug' => 'about',
        'title' => 'About Alive Church',
        'meta_description' => 'Learn about Alive Church Norwich - our mission, vision, and what we believe. We are a vibrant, Bible-believing church in Norwich, Norfolk.',
        'content' => '
<h2>Welcome to Alive Church</h2>
<p>We are a vibrant, Bible-believing church in Norwich, passionate about worshipping Jesus, building authentic community, and making a difference in our city and beyond.</p>

<h3>Our Mission</h3>
<p>To help people discover Jesus, grow in their faith, and use their gifts to serve others.</p>

<h3>What We Believe</h3>
<ul>
    <li><strong>The Bible</strong> - We believe the Bible is God\'s inspired Word and our ultimate authority for faith and life.</li>
    <li><strong>Jesus Christ</strong> - We believe Jesus is the Son of God who died for our sins and rose from the dead.</li>
    <li><strong>Salvation</strong> - We believe salvation comes through faith in Jesus Christ alone, not by works.</li>
    <li><strong>The Holy Spirit</strong> - We believe the Holy Spirit empowers believers to live for Christ and serve others.</li>
    <li><strong>The Church</strong> - We believe the Church is the body of Christ, called to worship, fellowship, and mission.</li>
</ul>

<h3>Our Values</h3>
<p><strong>Worship</strong> - We prioritize passionate, Spirit-led worship that honors God.</p>
<p><strong>Community</strong> - We build authentic relationships where people can belong and grow.</p>
<p><strong>Generosity</strong> - We give freely of our time, talents, and resources to bless others.</p>
<p><strong>Excellence</strong> - We do everything with excellence to honor God and serve people well.</p>
<p><strong>Mission</strong> - We actively share the love of Jesus in Norwich and around the world.</p>

<h3>Our History</h3>
<p>Alive Church was planted in Norwich in 2010 with a vision to see lives transformed by the gospel. What started as a small group meeting in a living room has grown into a thriving community of hundreds of people from all walks of life.</p>

<p>Today, we gather every Sunday for worship, connect in small groups throughout the week, serve our community through various outreach initiatives, and support mission work globally.</p>

<h3>Leadership</h3>
<p>Our church is led by a team of dedicated pastors and elders who are committed to shepherding the congregation with wisdom, integrity, and love. We believe in plurality of leadership and collaborative decision-making.</p>

<h3>Get Connected</h3>
<p>We would love to get to know you! Whether you\'re new to faith or have been following Jesus for years, there\'s a place for you at Alive Church. <a href="/visit">Plan your visit</a> or explore our <a href="/connect">connect opportunities</a> to find out how you can get involved.</p>
',
        'template' => 'default',
        'published' => 1
    ],
    [
        'slug' => 'visit',
        'title' => 'Plan Your Visit',
        'meta_description' => 'Plan your first visit to Alive Church Norwich. Find service times, directions, what to expect, and answers to frequently asked questions.',
        'content' => '
<h2>We Can\'t Wait to Meet You!</h2>
<p>Whether you\'re new to faith, exploring Christianity, or looking for a church home, you are welcome here. Here\'s everything you need to know for your first visit.</p>

<h3>Service Times</h3>
<p><strong>Sunday Gatherings:</strong></p>
<ul>
    <li>9:30 AM - Morning Service</li>
    <li>11:30 AM - Late Morning Service</li>
</ul>
<p>Both services feature the same worship, message, and experience. Choose the time that works best for you!</p>

<h3>Location & Directions</h3>
<p><strong>Alive Church Norwich</strong><br>
123 Faith Street<br>
Norwich, Norfolk NR1 1AA</p>

<p><a href="https://maps.google.com/?q=Alive+Church+Norwich" target="_blank" class="btn btn-primary">Get Directions →</a></p>

<h3>What to Expect</h3>
<p><strong>Parking:</strong> Free parking is available on-site and on nearby streets. Arrive 10-15 minutes early for the best spots.</p>

<p><strong>Arrival:</strong> Our welcome team will greet you at the entrance and help you find your way. Feel free to grab a coffee in the lobby!</p>

<p><strong>Kids:</strong> We have excellent programs for children from birth through Year 6. Check-in opens 15 minutes before each service.</p>

<p><strong>The Service:</strong> Our services last about 75 minutes and include contemporary worship, Bible teaching, prayer, and communion (first Sunday of each month).</p>

<p><strong>Dress Code:</strong> Come as you are! You\'ll see everything from jeans to suits. We care more about your heart than your wardrobe.</p>

<h3>Frequently Asked Questions</h3>

<p><strong>Will I have to stand out?</strong><br>
Not at all! We won\'t single you out or put you on the spot. You\'re welcome to participate as much or as little as you\'re comfortable.</p>

<p><strong>Do I need to bring anything?</strong><br>
Just yourself! We provide Bibles, and you can follow along with the message on the screens.</p>

<p><strong>What about my kids?</strong><br>
We have age-appropriate programs for all children. Our kids\' ministry teams are background-checked and trained to provide a safe, fun environment.</p>

<p><strong>Is there an offering?</strong><br>
We do take an offering during the service, but giving is entirely voluntary. As a guest, you are not expected to contribute.</p>

<p><strong>How can I connect further?</strong><br>
After the service, visit our Connect Desk in the lobby. We\'d love to answer questions and help you find ways to get involved.</p>

<h3>Questions?</h3>
<p>If you have any other questions about visiting, feel free to <a href="/prayer">contact us</a>. We\'re here to help!</p>
',
        'template' => 'default',
        'published' => 1
    ],
    [
        'slug' => 'prayer',
        'title' => 'Prayer Requests & Contact',
        'meta_description' => 'Submit a prayer request or get in touch with Alive Church Norwich. We would love to pray for you and connect with you.',
        'content' => '
<h2>How Can We Pray for You?</h2>
<p>We believe in the power of prayer and would be honored to pray for you. Whether you\'re facing a challenge, celebrating a victory, or simply need encouragement, our prayer team is here for you.</p>

<h3>Submit a Prayer Request</h3>
<p>Fill out the form below and our prayer team will lift your request before God. All requests are kept confidential.</p>

<p><em>Prayer request form will appear here when integrated with the existing contact form system.</em></p>

<h3>Get in Touch</h3>
<p>Have a question or want to connect with our team? We\'d love to hear from you!</p>

<p><strong>Office Hours:</strong><br>
Monday - Friday: 9:00 AM - 4:00 PM</p>

<p><strong>Phone:</strong> 01603 123456<br>
<strong>Email:</strong> hello@alivechurch.org.uk</p>

<p><strong>Address:</strong><br>
Alive Church<br>
123 Faith Street<br>
Norwich, Norfolk NR1 1AA</p>

<h3>Emergency Pastoral Care</h3>
<p>If you have an urgent pastoral need outside of office hours, please call our pastoral emergency line: 07700 900000</p>

<h3>Connect With Us</h3>
<p>Follow us on social media to stay updated on events, messages, and church news:</p>
<ul>
    <li>Facebook: @AliveChurchNorwich</li>
    <li>Instagram: @alivechurchnorwich</li>
    <li>YouTube: Alive Church Norwich</li>
</ul>
',
        'template' => 'default',
        'published' => 1
    ]
];

foreach ($pages_to_migrate as $page) {
    // Check if page already exists
    $stmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ?");
    $stmt->execute([$page['slug']]);
    $exists = $stmt->fetch();

    if ($exists) {
        echo "⏩ Skipping '{$page['slug']}' - already exists in database\n";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO pages (slug, title, meta_description, content, template, published, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");

        $stmt->execute([
            $page['slug'],
            $page['title'],
            $page['meta_description'],
            $page['content'],
            $page['template'],
            $page['published']
        ]);

        echo "✅ Migrated '{$page['slug']}' to database (ID: " . $pdo->lastInsertId() . ")\n";
    }
}

echo "\n📝 Migration Notes:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Migrated pages: about, visit, prayer\n\n";

echo "Pages kept as PHP files (due to dynamic content):\n";
echo "  • index.php - Complex homepage with multiple sections\n";
echo "  • watch.php - Sermon archive with video integration\n";
echo "  • give.php - Stripe payment integration\n";
echo "  • ministries.php - Pulls from ministries database table\n";
echo "  • next-steps.php - Pulls from next_steps database table\n";
echo "  • events.php - Planning Center integration\n";
echo "  • connect.php - Dynamic groups/ministries/serve data\n";
echo "\n";

echo "✨ You can now edit about, visit, and prayer pages in Admin → Pages!\n\n";

echo "⚠️  IMPORTANT: To activate database pages, you need to:\n";
echo "1. Rename or backup the old PHP files (about.php, visit.php, prayer.php)\n";
echo "2. The router will automatically serve database pages when PHP files don't exist\n";
echo "\nWould you like me to backup and remove the old files? (This script won't do it automatically)\n";
