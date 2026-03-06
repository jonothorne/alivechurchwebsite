<?php
/**
 * Data Migration Script
 * Migrates all existing data from config.php to database
 */

require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../config.php';

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    echo "Starting data migration...\n\n";

    // 1. Migrate Site Settings
    echo "1. Migrating site settings...\n";
    $settings = [
        // General
        ['site_name', $site['name'], 'text', 'general', 'Site Name', 'The name of your church'],
        ['site_tagline', $site['tagline'], 'text', 'general', 'Tagline', 'Your church tagline'],
        ['site_location', $site['location'], 'text', 'general', 'Location', 'Church address'],
        ['site_email', $site['email'], 'text', 'general', 'Email', 'Contact email'],
        ['site_phone', $site['phone'], 'text', 'general', 'Phone', 'Contact phone'],
        ['maps_url', $site['maps_url'], 'text', 'general', 'Maps URL', 'Google Maps link'],

        // Service Times
        ['service_times', $site['service_times'], 'text', 'services', 'Service Times', 'Main service times'],
        ['service_details', $site['service_details'], 'text', 'services', 'Service Details', 'Additional service info'],

        // Social Media
        ['social_facebook', $site['social']['facebook'], 'text', 'social', 'Facebook URL', ''],
        ['social_instagram', $site['social']['instagram'], 'text', 'social', 'Instagram URL', ''],
        ['social_youtube', $site['social']['youtube'], 'text', 'social', 'YouTube URL', ''],

        // Live Stream
        ['live_stream_url', $live_stream_url ?? '', 'text', 'media', 'Live Stream URL', 'YouTube live stream embed URL'],

        // Featured Sermon
        ['featured_sermon', json_encode($featured_sermon), 'json', 'media', 'Featured Sermon', 'Homepage featured sermon'],
    ];

    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value, setting_type, setting_group, display_name, description) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
    echo "   ✓ Site settings migrated\n";

    // 2. Migrate Ministries
    echo "2. Migrating ministries...\n";
    $stmt = $pdo->prepare("INSERT INTO ministries (title, summary, display_order, visible) VALUES (?, ?, ?, 1)");
    foreach ($ministries as $index => $ministry) {
        $stmt->execute([$ministry['title'], $ministry['summary'], $index]);
    }
    echo "   ✓ " . count($ministries) . " ministries migrated\n";

    // 3. Migrate Groups
    echo "3. Migrating groups...\n";
    $stmt = $pdo->prepare("INSERT INTO groups_list (title, description, schedule, location, image_url, signup_url, display_order, visible) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    foreach ($groups as $index => $group) {
        $stmt->execute([
            $group['title'],
            $group['description'],
            $group['schedule'],
            $group['location'],
            $group['image'],
            $group['signup_url'],
            $index
        ]);
    }
    echo "   ✓ " . count($groups) . " groups migrated\n";

    // 4. Migrate Serve Opportunities
    echo "4. Migrating serve opportunities...\n";
    $stmt = $pdo->prepare("INSERT INTO serve_opportunities (title, description, commitment, areas, image_url, display_order, visible) VALUES (?, ?, ?, ?, ?, ?, 1)");
    foreach ($serve_opportunities as $index => $serve) {
        $stmt->execute([
            $serve['title'],
            $serve['description'],
            $serve['commitment'],
            json_encode($serve['areas']),
            $serve['image'],
            $index
        ]);
    }
    echo "   ✓ " . count($serve_opportunities) . " serve opportunities migrated\n";

    // 5. Migrate Next Steps
    echo "5. Migrating next steps...\n";
    $stmt = $pdo->prepare("INSERT INTO next_steps (title, copy, link, display_order, visible) VALUES (?, ?, ?, ?, 1)");
    foreach ($next_steps as $index => $step) {
        $stmt->execute([
            $step['title'],
            $step['copy'],
            $step['link'],
            $index
        ]);
    }
    echo "   ✓ " . count($next_steps) . " next steps migrated\n";

    // 6. Migrate Sermon Series
    echo "6. Migrating sermon series...\n";
    $stmt = $pdo->prepare("INSERT INTO sermon_series (title, slug, description, image_url, date_range, message_count, display_order, visible) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    foreach ($sermon_series as $index => $series) {
        $stmt->execute([
            $series['title'],
            $series['slug'],
            $series['description'],
            $series['image'],
            $series['date_range'],
            $series['message_count'],
            $index
        ]);
    }
    echo "   ✓ " . count($sermon_series) . " sermon series migrated\n";

    // 7. Migrate Individual Sermons
    echo "7. Migrating sermons...\n";
    $stmt = $pdo->prepare("INSERT INTO sermons (title, speaker, length, video_id, display_order, visible) VALUES (?, ?, ?, ?, ?, 1)");
    foreach ($sermons as $index => $sermon) {
        $stmt->execute([
            $sermon['title'],
            $sermon['speaker'],
            $sermon['length'],
            $sermon['video_id'],
            $index
        ]);
    }
    echo "   ✓ " . count($sermons) . " sermons migrated\n";

    // 8. Migrate Navigation
    echo "8. Creating navigation menu...\n";
    $stmt = $pdo->prepare("INSERT INTO navigation (label, url, menu_order, css_class, visible) VALUES (?, ?, ?, ?, 1)");
    foreach ($nav_links as $index => $link) {
        $stmt->execute([
            $link['label'],
            $link['url'],
            $index,
            $link['class'] ?? ''
        ]);
    }
    echo "   ✓ " . count($nav_links) . " navigation items migrated\n";

    $pdo->commit();

    echo "\n✅ Migration complete! All data has been migrated to the database.\n";
    echo "Your existing config.php file has been preserved for reference.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
