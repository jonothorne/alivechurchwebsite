<?php
// Load site settings from database with fallback defaults
require_once __DIR__ . '/includes/db-config.php';

// Default values (used as fallback if database is unavailable)
$site = [
    'name' => 'Alive Church',
    'location' => 'Alive House, Nelson Street, Norwich NR2 4DR',
    'email' => 'office@alive.me.uk',
    'phone' => '+44 (0)1603 000000',
    'service_times' => 'Sundays • 11:00AM',
    'service_details' => 'Coffee & light breakfast from 10:15AM',
    'tagline' => 'You Belong Here',
    'maps_url' => 'https://maps.google.com/?q=Alive+House+Nelson+Street+Norwich+NR2+4DR',
    'social' => [
        'facebook' => 'https://facebook.com/alivechurchonline',
        'instagram' => 'https://instagram.com/alivechurch',
        'youtube' => 'https://youtube.com/@alivechurchnorwich'
    ]
];

// Load settings from database
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    $db_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Map database keys to $site array keys
    if (!empty($db_settings['site_name'])) $site['name'] = $db_settings['site_name'];
    if (!empty($db_settings['site_tagline'])) $site['tagline'] = $db_settings['site_tagline'];
    if (!empty($db_settings['site_location'])) $site['location'] = $db_settings['site_location'];
    if (!empty($db_settings['site_email'])) $site['email'] = $db_settings['site_email'];
    if (!empty($db_settings['site_phone'])) $site['phone'] = $db_settings['site_phone'];
    if (!empty($db_settings['maps_url'])) $site['maps_url'] = $db_settings['maps_url'];
    if (!empty($db_settings['service_times'])) $site['service_times'] = $db_settings['service_times'];
    if (!empty($db_settings['service_details'])) $site['service_details'] = $db_settings['service_details'];
    if (!empty($db_settings['social_facebook'])) $site['social']['facebook'] = $db_settings['social_facebook'];
    if (!empty($db_settings['social_instagram'])) $site['social']['instagram'] = $db_settings['social_instagram'];
    if (!empty($db_settings['social_youtube'])) $site['social']['youtube'] = $db_settings['social_youtube'];
} catch (Exception $e) {
    // Database unavailable - use defaults
    error_log('Config: Could not load site settings from database: ' . $e->getMessage());
}

$nav_links = [
    ['label' => 'Home', 'url' => '/'],
    [
        'label' => 'I\'m New',
        'url' => '/visit',
        'dropdown' => [
            ['label' => 'Plan Your Visit', 'url' => '/visit'],
            ['label' => 'About Us', 'url' => '/about'],
            ['label' => 'What We Believe', 'url' => '/about#beliefs'],
            ['label' => 'Our Team', 'url' => '/about#team'],
            ['label' => 'Watch Online', 'url' => '/watch'],
        ]
    ],
    ['label' => 'Events', 'url' => '/events'],
    ['label' => 'Bible Study', 'url' => '/bible-study'],
    ['label' => 'Connect', 'url' => '/connect'],
    ['label' => 'Blog', 'url' => '/blog'],
];

$ministries = [
    ['title' => 'Revive Café', 'summary' => 'Our cafe is open throughout the week and provides a safe place for you to connect over coffee. <a class="btn-link" href="https://revive-cafe.co.uk">Visit Revive</a>'],
    ['title' => 'Foodbank', 'summary' => 'Meeting urgent needs for families across Norwich in crisis.'],
    ['title' => 'Alive Youth', 'summary' => 'A place for young people to belong, connect with friends and Jesus.'],
    ['title' => 'Prayer & Care', 'summary' => 'Prayer room, pastoral support, and mentoring.'],
    ['title' => 'Groups', 'summary' => 'Mens, womens, gateway, and connect groups meeting regularly.'],
    ['title' => 'Worship', 'summary' => 'Teams leading Spirit-led worship moments.'],
    ['title' => 'Alive UK Emergency Response', 'summary' => 'Our charity work providing response in emergency and support to families in crisis.'],
    ['title' => 'Community Regeneration', 'summary' => 'Our mission is to see transformation in our local community, and this is core to everything we do.'],
];

$next_steps = [
    ['title' => 'Baptism', 'copy' => 'Celebrate new life and declare your faith publicly.', 'link' => '/next-steps/baptism'],
    ['title' => 'Join a Group', 'copy' => 'Find community that encourages and challenges you.', 'link' => '/groups/join'],
    ['title' => 'Serve on a Team', 'copy' => 'Use your gifts across kids, creative, outreach, and guest experience.', 'link' => '/serve/apply'],
    ['title' => 'Prayer Request', 'copy' => 'Our team prays daily. Share what is on your heart.', 'link' => '/prayer'],
];

// NOTE: $weekend_events and $events are now populated from the live calendar below
// They will be set after $all_events is loaded from Planning Center

// Featured sermon for homepage video player
$featured_sermon = [
    'title' => 'Make Room – Week 3',
    'speaker' => 'Ps. Philip Thorne',
    'date' => 'February 2, 2025',
    'length' => '38 mins',
    'video_id' => 'zIXzSyQIEks', // Placeholder YouTube ID
    'series' => 'Make Room'
];

$sermons = [
    [
        'title' => 'Make Room – Week 3',
        'speaker' => 'Ps. Jo Thorne',
        'length' => '38 mins',
        'url' => '#',
        'video_id' => 'dQw4w9WgXcQ'
    ],
    [
        'title' => 'Walk With Me',
        'speaker' => 'Ps. Philip Thorne',
        'length' => '42 mins',
        'url' => '#',
        'video_id' => 'dQw4w9WgXcQ'
    ],
    [
        'title' => 'Habits of Hope',
        'speaker' => 'Ps. Sara Plastow',
        'length' => '35 mins',
        'url' => '#',
        'video_id' => 'dQw4w9WgXcQ'
    ],
];

// Live stream detection helper function
function is_stream_live() {
    $now = new DateTime('now', new DateTimeZone('Europe/London'));
    $day = $now->format('w'); // 0 = Sunday
    $time = $now->format('H:i');

    // Live on Sundays 9:15-10:45 and 11:15-12:45
    if ($day === '0') {
        if (($time >= '10:55' && $time <= '12:15')){
            return true;
        }
    }
    return false;
}

$is_live = is_stream_live();
$live_stream_url = 'https://www.youtube.com/embed/dQw4w9WgXcQ'; // Placeholder

// Sermon series for watch page
$sermon_series = [
    [
        'title' => 'Make Room',
        'slug' => 'make-room',
        'description' => 'Creating space in your life for what matters most.',
        'image' => '/assets/imgs/gallery/alive-church-worship-team-stage.jpg',
        'date_range' => 'January 2025',
        'message_count' => 4
    ],
    [
        'title' => 'Walk With Me',
        'slug' => 'walk-with-me',
        'description' => 'Following Jesus through every season of life.',
        'image' => '/assets/imgs/gallery/alive-church-live-worship-band-lincolnshire.jpg',
        'date_range' => 'December 2024',
        'message_count' => 5
    ],
    [
        'title' => 'Habits of Hope',
        'slug' => 'habits-of-hope',
        'description' => 'Building daily practices that transform your faith.',
        'image' => '/assets/imgs/gallery/alive-church-acoustic-worship-prayer.jpg',
        'date_range' => 'November 2024',
        'message_count' => 6
    ]
];

// All events for events page - Dynamically fetched from Planning Center calendar
require_once __DIR__ . '/includes/calendar-parser.php';

$calendarUrl = 'webcal://calendar.planningcenteronline.com/icals/eJxj4ajmsGLLz2SSX2HFlVqcX1BSzW7FkZyY46nEk5KalliaU8JmxeYaYsVWmsk80YTbirsgsSgxt7iaAQDL3xBEe6ee4363009c56f2415c848b96c06e7a66287bb0';

$calendar = new CalendarParser($calendarUrl);
$all_events = $calendar->getEvents();

// Fallback events if calendar fetch fails
if (empty($all_events)) {
    $all_events = [
        [
            'title' => 'Sunday Gatherings',
            'category' => 'weekly',
            'date' => 'Every Sunday',
            'time' => '11:00AM',
            'location' => 'Alive House, Norwich',
            'description' => 'Join us for passionate worship, practical teaching, and authentic community. Kids programs available for all ages. Coffee and light breakfast from 10:15AM.',
            'image' => '/assets/imgs/gallery/alive-church-worship-congregation.jpg',
            'cost' => 'Free',
            'registration_required' => false,
            'info_url' => '/visit',
            'slug' => 'sunday-gatherings'
        ],
        [
            'title' => 'Alive Youth',
            'category' => 'youth',
            'date' => 'Every Saturday',
            'time' => '4:00PM',
            'location' => 'Alive House, Norwich',
            'description' => 'Games, worship, teaching, and community for ages 11-18. A safe place to belong, have fun, and grow in faith.',
            'image' => '/assets/imgs/gallery/alive-church-drummer-worship-team.jpg',
            'cost' => 'Free',
            'registration_required' => false,
            'info_url' => '/connect',
            'slug' => 'youth'
        ]
    ];
}

// Populate homepage event sections from calendar
// "This Weekend" section - Next 3-4 upcoming events
$weekend_events = [];
$event_count = 0;
foreach ($all_events as $event) {
    if ($event_count >= 3) break;

    // Format for weekend events display
    $weekend_events[] = [
        'title' => $event['title'],
        'time' => !empty($event['is_recurring'])
            ? str_replace('Weekly on ', '', $event['frequency'] ?? '') . ' • ' . ($event['time'] ?? '')
            : (isset($event['start_datetime']) ? date('D', strtotime($event['start_datetime'])) . ' • ' : '') . ($event['time'] ?? $event['date'] ?? ''),
        'description' => $event['description'] ?? '',
        'url' => !empty($event['registration_required']) ? ($event['registration_url'] ?? '/events') : '/events'
    ];
    $event_count++;
}

// "What's Happening" section - Featured/special events (prefer non-recurring)
$events = [];
$featured_count = 0;
// First, try to get special/featured events (non-recurring)
foreach ($all_events as $event) {
    if ($featured_count >= 2) break;
    if (empty($event['is_recurring']) && in_array($event['category'], ['special', 'youth'])) {
        $events[] = [
            'title' => $event['title'],
            'date' => $event['date'],
            'description' => $event['description'],
            'cta' => $event['registration_required'] ? ($event['registration_url'] ?? '/events') : '/events'
        ];
        $featured_count++;
    }
}

// If we don't have enough special events, add some recurring ones
if ($featured_count < 2) {
    foreach ($all_events as $event) {
        if ($featured_count >= 2) break;
        if (!empty($event['is_recurring'])) {
            $events[] = [
                'title' => $event['title'],
                'date' => $event['frequency'],
                'description' => $event['description'],
                'cta' => '/events'
            ];
            $featured_count++;
        }
    }
}

// Groups for connect page
$groups = [
    [
        'title' => 'Gateway Group',
        'description' => 'Perfect for newcomers exploring faith and community. A safe space to ask questions and make friends.',
        'schedule' => 'Every Tuesday, 7:30PM',
        'location' => 'Alive House',
        'image' => '/assets/imgs/gallery/alive-church-community-craft-activity.jpg',
        'signup_url' => '/groups/join'
    ],
    [
        'title' => 'Men\'s Breakfast',
        'description' => 'Monthly gathering for men to connect, eat, and grow together in faith and life.',
        'schedule' => 'First Saturday, 8:30AM',
        'location' => 'Various Locations',
        'image' => '/assets/imgs/gallery/alive-church-family-worship-lincolnshire.jpg',
        'signup_url' => '/groups/join'
    ],
    [
        'title' => 'Women\'s Evening',
        'description' => 'Monthly gathering for women to connect, share, and support each other through all of life\'s seasons.',
        'schedule' => 'Third Thursday, 7:00PM',
        'location' => 'Alive House',
        'image' => '/assets/imgs/gallery/alive-church-community-craft-activity.jpg',
        'signup_url' => '/groups/join'
    ]
];

// Serve opportunities for connect page
$serve_opportunities = [
    [
        'title' => 'Guest Experience Team',
        'description' => 'Be the first smile people see when they walk in. Help with parking, hosting, coffee, and connections.',
        'commitment' => 'Once a month',
        'areas' => ['Parking', 'Hosting', 'Coffee Bar', 'Connection Points'],
        'image' => '/assets/imgs/gallery/alive-church-christmas-service-celebration.jpg'
    ],
    [
        'title' => 'Kids Ministry',
        'description' => 'Help kids discover Jesus through games, teaching, and fun. Make Sunday the best day of their week!',
        'commitment' => 'Twice a month',
        'areas' => ['Nursery', 'Preschool', 'Elementary', 'Check-In'],
        'image' => '/assets/imgs/gallery/alive-church-family-worship-lincolnshire.jpg'
    ],
    [
        'title' => 'Worship Team',
        'description' => 'Use your musical gifts to lead people into God\'s presence through passionate worship.',
        'commitment' => 'Once a month',
        'areas' => ['Vocals', 'Band', 'Tech/Sound', 'Media'],
        'image' => '/assets/imgs/gallery/alive-church-worship-team-stage.jpg'
    ],
    [
        'title' => 'Prayer Team',
        'description' => 'Intercede for our church, city, and world. Serve on Sundays or join the prayer room team.',
        'commitment' => 'Flexible',
        'areas' => ['Sunday Prayer', 'Prayer Room', 'Email Prayer'],
        'image' => '/assets/imgs/gallery/alive-church-acoustic-worship-prayer.jpg'
    ]
];
