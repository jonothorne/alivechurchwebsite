<?php
/**
 * Migrate existing pages to database with EXACT matching sections
 * This preserves all CSS classes, structure, and styling
 */

require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();

echo "Starting exact page migration...\n\n";

// First, update or create the pages
$pages = [
    [
        'slug' => 'about',
        'title' => 'About | Alive Church',
        'meta_description' => 'Learn about Alive Church Norwich - our mission, vision, values and leadership team.',
        'template' => 'default',
        'published' => 1
    ],
    [
        'slug' => 'visit',
        'title' => 'Plan a Visit | Alive Church',
        'meta_description' => 'Plan your first visit to Alive Church Norwich. Service times, directions, what to expect, and FAQs.',
        'template' => 'default',
        'published' => 1
    ],
    [
        'slug' => 'prayer',
        'title' => 'Prayer Request | Alive Church',
        'meta_description' => 'Submit a prayer request to Alive Church Norwich. Our prayer team prays daily.',
        'template' => 'default',
        'published' => 1
    ]
];

foreach ($pages as $page_data) {
    $stmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ?");
    $stmt->execute([$page_data['slug']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE pages SET title = ?, meta_description = ?, template = ?, published = ? WHERE id = ?");
        $stmt->execute([$page_data['title'], $page_data['meta_description'], $page_data['template'], $page_data['published'], $existing['id']]);
        echo "✓ Updated page: {$page_data['slug']}\n";
    } else {
        $stmt = $pdo->prepare("INSERT INTO pages (slug, title, meta_description, template, published, created_by) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$page_data['slug'], $page_data['title'], $page_data['meta_description'], $page_data['template'], $page_data['published']]);
        echo "✓ Created page: {$page_data['slug']}\n";
    }
}

// Get page IDs
$stmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ?");

$stmt->execute(['about']);
$about_id = $stmt->fetch()['id'];

$stmt->execute(['visit']);
$visit_id = $stmt->fetch()['id'];

$stmt->execute(['prayer']);
$prayer_id = $stmt->fetch()['id'];

// Clear existing sections
$pdo->prepare("DELETE FROM page_sections WHERE page_id IN (?, ?, ?)")->execute([$about_id, $visit_id, $prayer_id]);
echo "\n✓ Cleared old sections\n\n";

// ===== ABOUT PAGE SECTIONS =====
echo "Building About page sections...\n";

$about_sections = [
    [
        'page_id' => $about_id,
        'section_type' => 'custom-html',
        'section_order' => 0,
        'heading' => 'About Alive',
        'subheading' => 'Our story & values.',
        'content' => '<p>From a small prayer gathering to a growing family, we are passionate about helping people live fully alive in Jesus and taking hope to every street.</p>',
        'additional_data' => json_encode(['css_class' => 'page-hero', 'container_class' => 'container narrow'])
    ],
    [
        'page_id' => $about_id,
        'section_type' => 'custom-html',
        'section_order' => 1,
        'content' => '
<div class="container split">
    <div>
        <h2>Who we are</h2>
        <p>Alive Church began as a prayer meeting above a shop in Norwich. Today, we gather in our building, homes, and online. Everything we do flows from Jesus\' Great Commission and the Acts 2 church—devoted to teaching, fellowship, generosity, and prayer.</p>
        <p>We champion unity across generations, creativity that communicates the gospel, and audacious generosity for our cities. We believe in empowering the next generation of leaders through coaching, Alive Leadership College, and hands-on ministry.</p>
        <img src="/assets/imgs/gallery/alive-church-worship-congregation.jpg" alt="Alive Church worship service" style="border-radius: 1rem; margin-top: 1.5rem; box-shadow: 0 20px 40px rgba(75, 38, 121, 0.15); width: 100%;">
    </div>
    <div class="card">
        <h3>Our values</h3>
        <ul class="info-list">
            <li><strong>Presence:</strong> Everything begins with worship.</li>
            <li><strong>People:</strong> Everyone is seen, known, and needed.</li>
            <li><strong>Purpose:</strong> We equip everyday saints to influence their world.</li>
            <li><strong>Play:</strong> Joy is our culture; laughter is welcome.</li>
        </ul>
    </div>
</div>',
        'additional_data' => json_encode(['css_class' => 'content-section'])
    ],
    [
        'page_id' => $about_id,
        'section_type' => 'custom-html',
        'section_order' => 2,
        'content' => '
<div class="container">
    <div class="section-heading">
        <p class="eyebrow">Leadership</p>
        <h2>Meet some of the vision team.</h2>
    </div>
    <div class="card-grid profile-grid">
        <article class="profile-card">
            <img src="/assets/imgs/gallery/alive-church-congregation-hands-raised.jpg" alt="Pastor speaking at Alive Church" style="border-radius: 0.75rem; margin-bottom: 1rem; width: 100%; height: 200px; object-fit: cover;">
            <h3>Pastors Phil & Jo Thorne</h3>
            <p>Senior Pastors</p>
            <p>Phil started Alive Church over 40 years ago with a vision for community transformation in Norwich. Phil pastors the church with Jo, his wife. They have both shaped the church we see today.</p>
        </article>
        <article class="profile-card">
            <img src="/assets/imgs/gallery/alive-church-worship-leaders-performance.jpg" alt="Worship leaders at Alive Church" style="border-radius: 0.75rem; margin-bottom: 1rem; width: 100%; height: 200px; object-fit: cover;">
            <h3>Pastor Jono Thorne</h3>
            <p>Worship & Creative Pastor</p>
            <p>Jono oversees many aspects of church life. He is always doing something new and is responsible for Worship, Creative, Youth, Facilities management and Operations in the church.</p>
        </article>
        <article class="profile-card">
            <img src="/assets/imgs/gallery/alive-church-live-worship-band-lincolnshire.jpg" alt="Youth worship at Alive Church" style="border-radius: 0.75rem; margin-bottom: 1rem; width: 100%; height: 200px; object-fit: cover;">
            <h3>Pastors Jon and Sara Plastow</h3>
            <p>Pastoral, Children & Compliance</p>
            <p>Sara grew up in the church, and with her husband Jon, lead many aspects of church life. Sara can often be found preaching, whilst Jon plays drums. Together they lead Kids Church and Jon is trustee of Alive UK.</p>
        </article>
        <article class="profile-card">
            <img src="/assets/imgs/gallery/alive-church-live-worship-band-lincolnshire.jpg" alt="Youth worship at Alive Church" style="border-radius: 0.75rem; margin-bottom: 1rem; width: 100%; height: 200px; object-fit: cover;">
            <h3>Pastors Abiodun & Ruth</h3>
            <p>Pastoral Team</p>
            <p>Abiodun and Ruth are passionate about pastoral care and community outreach. They lead various initiatives to help people connect and grow in their faith journey.</p>
        </article>
    </div>
</div>',
        'additional_data' => json_encode(['css_class' => 'content-section alt'])
    ]
];

foreach ($about_sections as $section) {
    $stmt = $pdo->prepare("INSERT INTO page_sections (page_id, section_type, section_order, heading, subheading, content, additional_data, visible) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$section['page_id'], $section['section_type'], $section['section_order'], $section['heading'] ?? null, $section['subheading'] ?? null, $section['content'], $section['additional_data']]);
}

echo "  ✓ Created " . count($about_sections) . " sections for About page\n";

echo "\n✅ Migration Complete!\n\n";
echo "Note: Visit and Prayer pages include forms which need the PHP files.\n";
echo "For these pages, you should use the page content field instead of sections,\n";
echo "or keep them as PHP files to preserve form functionality.\n";
?>