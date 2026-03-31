<?php
/**
 * SongSelect Search API
 * Searches the CCLI SongSelect database for worship songs using web scraping
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/db-config.php';
require_once __DIR__ . '/../../../includes/services/SongSelectScraper.php';
require_once __DIR__ . '/../../../includes/services/CredentialEncryption.php';

// Check for search query
$query = trim($_GET['q'] ?? '');
if (empty($query)) {
    echo json_encode(['error' => 'Search query is required']);
    exit;
}

$pdo = getDbConnection();

// Check if SongSelect is configured
$config = $pdo->query("SELECT * FROM songselect_config WHERE is_active = 1 LIMIT 1")->fetch();

if (!$config || empty($config['client_id'])) {
    // Return demo data for testing if not configured
    echo json_encode([
        'demo_mode' => true,
        'message' => 'Running in demo mode. Configure SongSelect in Settings for full access.',
        'results' => getDemoResults($query)
    ]);
    exit;
}

// Decrypt the password
$password = CredentialEncryption::decrypt($config['client_secret']);

if (empty($password)) {
    // Password couldn't be decrypted, use demo mode
    echo json_encode([
        'demo_mode' => true,
        'message' => 'Please re-save your SongSelect password in Settings.',
        'results' => getDemoResults($query)
    ]);
    exit;
}

// Try to search SongSelect using the scraper
try {
    $scraper = new SongSelectScraper($config['client_id'], $password);

    $results = $scraper->search($query, 20);

    if (empty($results)) {
        // Fall back to demo results if search returned nothing
        echo json_encode([
            'results' => getDemoResults($query),
            'message' => 'No results from SongSelect. Showing demo songs.'
        ]);
    } else {
        echo json_encode(['results' => $results]);
    }
} catch (Exception $e) {
    // On error, return demo results with warning (not error, so UI can still display them)
    echo json_encode([
        'warning' => 'SongSelect connection unavailable. Showing demo songs. Error: ' . $e->getMessage(),
        'demo_mode' => true,
        'results' => getDemoResults($query)
    ]);
}

/**
 * Get demo results for testing without SongSelect configured
 */
function getDemoResults($query) {
    $demoSongs = [
        [
            'songselect_id' => 'demo_1',
            'ccli_number' => '7119315',
            'title' => 'Goodness of God',
            'artist' => 'Bethel Music',
            'authors' => 'Ben Fielding, Brian Johnson, Ed Cash, Jason Ingram, Jenn Johnson',
            'copyright' => '© 2018 Bethel Music Publishing',
            'themes' => 'faithfulness, praise, goodness',
            'default_key' => 'C',
            'tempo' => 72,
            'time_signature' => '4/4',
            'lyrics' => "Verse 1\nI love You, Lord\nOh Your mercy never fails me\nAll my days, I've been held in Your hands\nFrom the moment that I wake up\nUntil I lay my head\nI will sing of the goodness of God\n\nChorus\nAll my life You have been faithful\nAll my life You have been so, so good\nWith every breath that I am able\nI will sing of the goodness of God\n\nVerse 2\nI love Your voice\nYou have led me through the fire\nIn darkest night, You are close like no other\nI've known You as a father\nI've known You as a friend\nI have lived in the goodness of God\n\nBridge\nYour goodness is running after\nIt's running after me\nYour goodness is running after\nIt's running after me\nWith my life laid down\nI'm surrendered now\nI give You everything\nYour goodness is running after\nIt's running after me",
            'chord_chart' => "{title: Goodness of God}\n{artist: Bethel Music}\n{key: C}\n{tempo: 72}\n\n{verse: Verse 1}\n[C]I love You, Lord\n[G]Oh Your mercy never [Am]fails me\n[F]All my days, I've been [C]held in Your hands\n[C]From the moment that I [G]wake up\n[Am]Until I lay my head\n[F]I will sing of the [C]goodness of God\n\n{chorus}\n[F]All my life You have been [C]faithful\n[G]All my life You have been so, so [Am]good\n[F]With every breath that I am [C]able\n[G]I will sing of the [C]goodness of God\n\n{verse: Verse 2}\n[C]I love Your voice\n[G]You have led me through the [Am]fire\n[F]In darkest night, You are [C]close like no other\n[C]I've known You as a [G]father\n[Am]I've known You as a friend\n[F]I have lived in the [C]goodness of God\n\n{bridge}\n[Am]Your goodness is running after\n[G]It's running after me\n[Am]Your goodness is running after\n[G]It's running after me\n[F]With my life laid down\n[C]I'm surrendered now\n[G]I give You everything\n[Am]Your goodness is running after\n[G]It's running after [C]me"
        ],
        [
            'songselect_id' => 'demo_2',
            'ccli_number' => '7102401',
            'title' => 'Who You Say I Am',
            'artist' => 'Hillsong Worship',
            'authors' => 'Ben Fielding, Reuben Morgan',
            'copyright' => '© 2017 Hillsong Music Publishing',
            'themes' => 'identity, freedom, grace',
            'default_key' => 'G',
            'tempo' => 68,
            'time_signature' => '4/4',
            'lyrics' => "Verse 1\nWho am I that the highest King\nWould welcome me?\nI was lost but He brought me in\nOh His love for me\nOh His love for me\n\nChorus\nWho the Son sets free\nOh is free indeed\nI'm a child of God\nYes I am\n\nVerse 2\nFree at last, He has ransomed me\nHis grace runs deep\nWhile I was a slave to sin\nJesus died for me\nYes He died for me\n\nBridge\nI am chosen\nNot forsaken\nI am who You say I am\nYou are for me\nNot against me\nI am who You say I am",
            'chord_chart' => "{title: Who You Say I Am}\n{artist: Hillsong Worship}\n{key: G}\n{tempo: 68}\n\n{verse: Verse 1}\n[G]Who am I that the highest [D]King\nWould welcome [Em]me?\n[C]I was lost but He brought me [G]in\n[D]Oh His love for me\n[C]Oh His love for [G]me\n\n{chorus}\n[G]Who the Son sets free\n[D]Oh is free indeed\n[Em]I'm a child of [C]God\n[G]Yes I am\n\n{bridge}\n[Em]I am chosen\n[D]Not forsaken\n[G]I am who You say I [C]am\n[Em]You are for me\n[D]Not against me\n[G]I am who You say I [C]am"
        ],
        [
            'songselect_id' => 'demo_3',
            'ccli_number' => '7068424',
            'title' => 'Build My Life',
            'artist' => 'Pat Barrett',
            'authors' => 'Brett Younker, Karl Martin, Kirby Kaple, Matt Redman, Pat Barrett',
            'copyright' => '© 2016 Kaple Music, Capitol CMG Genesis',
            'themes' => 'surrender, devotion, worship',
            'default_key' => 'G',
            'tempo' => 68,
            'time_signature' => '4/4',
            'lyrics' => "Verse 1\nWorthy of every song we could ever sing\nWorthy of all the praise we could ever bring\nWorthy of every breath we could ever breathe\nWe live for You\n\nPre-Chorus\nHoly, there is no one like You\nThere is none beside You\nOpen up my eyes in wonder\n\nChorus\nAnd show me who You are\nAnd fill me with Your heart\nAnd lead me in Your love\nTo those around me\n\nBridge\nI will build my life upon Your love\nIt is a firm foundation\nI will put my trust in You alone\nAnd I will not be shaken",
            'chord_chart' => "{title: Build My Life}\n{artist: Pat Barrett}\n{key: G}\n{tempo: 68}\n\n{verse: Verse 1}\n[G]Worthy of every [D]song we could ever sing\n[Em]Worthy of all the [C]praise we could ever bring\n[G]Worthy of every [D]breath we could ever breathe\n[Em]We live for [C]You\n\n{pre-chorus}\n[G]Holy, there is [D]no one like You\n[Em]There is none be[C]side You\n[G]Open up my [D]eyes in wonder\n\n{chorus}\n[Em]And show me [C]who You are\n[G]And fill me [D]with Your heart\n[Em]And lead me [C]in Your love\n[G]To those a[D]round me\n\n{bridge}\n[C]I will build my life upon Your [G]love\n[D]It is a firm foun[Em]dation\n[C]I will put my trust in You a[G]lone\n[D]And I will not be [Em]shaken"
        ],
        [
            'songselect_id' => 'demo_4',
            'ccli_number' => '7065049',
            'title' => 'Reckless Love',
            'artist' => 'Cory Asbury',
            'authors' => 'Caleb Culver, Cory Asbury, Ran Jackson',
            'copyright' => '© 2017 Cory Asbury Publishing',
            'themes' => 'love, pursuit, grace',
            'default_key' => 'C',
            'tempo' => 75,
            'time_signature' => '4/4',
            'lyrics' => "Verse 1\nBefore I spoke a word, You were singing over me\nYou have been so, so good to me\nBefore I took a breath, You breathed Your life in me\nYou have been so, so kind to me\n\nChorus\nOh, the overwhelming, never-ending, reckless love of God\nOh, it chases me down, fights 'til I'm found, leaves the ninety-nine\nI couldn't earn it, and I don't deserve it, still, You give Yourself away\nOh, the overwhelming, never-ending, reckless love of God\n\nVerse 2\nWhen I was Your foe, still Your love fought for me\nYou have been so, so good to me\nWhen I felt no worth, You paid it all for me\nYou have been so, so kind to me",
            'chord_chart' => "{title: Reckless Love}\n{artist: Cory Asbury}\n{key: C}\n{tempo: 75}\n\n{verse: Verse 1}\n[C]Before I spoke a word, You were [G]singing over me\n[Am]You have been so, so [F]good to me\n[C]Before I took a breath, You [G]breathed Your life in me\n[Am]You have been so, so [F]kind to me\n\n{chorus}\n[C]Oh, the overwhelming, never-ending, [G]reckless love of God\n[Am]Oh, it chases me down, fights 'til I'm found, [F]leaves the ninety-nine\n[C]I couldn't earn it, and I don't de[G]serve it, still, You give Yourself away\n[Am]Oh, the overwhelming, never-ending, [F]reckless love of [C]God"
        ],
        [
            'songselect_id' => 'demo_5',
            'ccli_number' => '7017786',
            'title' => 'What A Beautiful Name',
            'artist' => 'Hillsong Worship',
            'authors' => 'Ben Fielding, Brooke Ligertwood',
            'copyright' => '© 2016 Hillsong Music Publishing',
            'themes' => 'Jesus, name of God, power',
            'default_key' => 'D',
            'tempo' => 68,
            'time_signature' => '4/4',
            'lyrics' => "Verse 1\nYou were the Word at the beginning\nOne with God the Lord Most High\nYour hidden glory in creation\nNow revealed in You our Christ\n\nChorus 1\nWhat a beautiful Name it is\nWhat a beautiful Name it is\nThe Name of Jesus Christ my King\nWhat a beautiful Name it is\nNothing compares to this\nWhat a beautiful Name it is\nThe Name of Jesus\n\nVerse 2\nYou didn't want heaven without us\nSo Jesus, You brought heaven down\nMy sin was great, Your love was greater\nWhat could separate us now?",
            'chord_chart' => "{title: What A Beautiful Name}\n{artist: Hillsong Worship}\n{key: D}\n{tempo: 68}\n\n{verse: Verse 1}\n[D]You were the Word at the be[A]ginning\n[Bm]One with God the Lord Most [G]High\n[D]Your hidden glory in cre[A]ation\n[Bm]Now revealed in You our [G]Christ\n\n{chorus}\n[D]What a beautiful Name it [A]is\n[Bm]What a beautiful Name it [G]is\n[D]The Name of Jesus [A]Christ my [Bm]King\n[G]What a beautiful Name it is\n[D]Nothing compares to [A]this\n[Bm]What a beautiful Name it [G]is\n[D]The Name of [A]Jesus"
        ],
        [
            'songselect_id' => 'demo_6',
            'ccli_number' => '7001228',
            'title' => '10,000 Reasons (Bless the Lord)',
            'artist' => 'Matt Redman',
            'authors' => 'Jonas Myrin, Matt Redman',
            'copyright' => '© 2011 Atlas Mountain Songs, Said And Done Music',
            'themes' => 'praise, worship, thankfulness',
            'default_key' => 'G',
            'tempo' => 73,
            'time_signature' => '4/4',
            'lyrics' => "Chorus\nBless the Lord, O my soul, O my soul\nWorship His holy name\nSing like never before, O my soul\nI'll worship Your holy name\n\nVerse 1\nThe sun comes up, it's a new day dawning\nIt's time to sing Your song again\nWhatever may pass and whatever lies before me\nLet me be singing when the evening comes\n\nVerse 2\nYou're rich in love and You're slow to anger\nYour name is great and Your heart is kind\nFor all Your goodness, I will keep on singing\nTen thousand reasons for my heart to find",
            'chord_chart' => "{title: 10,000 Reasons (Bless the Lord)}\n{artist: Matt Redman}\n{key: G}\n{tempo: 73}\n\n{chorus}\n[G]Bless the Lord, O my soul, [D]O my soul\n[Em]Worship His [C]holy name\n[G]Sing like never before, [D]O my soul\n[Em]I'll worship Your [C]holy [G]name\n\n{verse: Verse 1}\n[G]The sun comes up, it's a [Em]new day dawning\n[C]It's time to sing Your [D]song again\n[G]Whatever may pass and what[Em]ever lies before me\n[C]Let me be singing when the [D]evening comes\n\n{verse: Verse 2}\n[G]You're rich in love and You're [Em]slow to anger\n[C]Your name is great and Your [D]heart is kind\n[G]For all Your goodness, I will [Em]keep on singing\n[C]Ten thousand reasons for my [D]heart to find"
        ],
        [
            'songselect_id' => 'demo_7',
            'ccli_number' => '7106807',
            'title' => 'Living Hope',
            'artist' => 'Phil Wickham',
            'authors' => 'Brian Johnson, Phil Wickham',
            'copyright' => '© 2017 Phil Wickham Music, Bethel Music Publishing',
            'themes' => 'resurrection, hope, salvation',
            'default_key' => 'C',
            'tempo' => 72,
            'time_signature' => '4/4',
            'lyrics' => "Verse 1\nHow great the chasm that lay between us\nHow high the mountain I could not climb\nIn desperation I turned to heaven\nAnd spoke Your name into the night\n\nChorus\nHallelujah, praise the One who set me free\nHallelujah, death has lost its grip on me\nYou have broken every chain\nThere's salvation in Your name\nJesus Christ, my living hope",
            'chord_chart' => "{title: Living Hope}\n{artist: Phil Wickham}\n{key: C}\n{tempo: 72}\n\n{verse: Verse 1}\n[C]How great the chasm that [G]lay between us\n[Am]How high the mountain I [F]could not climb\n[C]In desperation I [G]turned to heaven\n[Am]And spoke Your name in[F]to the night\n\n{chorus}\n[F]Hallelujah, [C]praise the One who set me free\n[F]Hallelujah, [C]death has lost its grip on me\n[Am]You have broken every [G]chain\n[F]There's salvation in Your [C]name\n[G]Jesus Christ, my living [C]hope"
        ],
        [
            'songselect_id' => 'demo_8',
            'ccli_number' => '7089024',
            'title' => 'Great Are You Lord',
            'artist' => 'All Sons & Daughters',
            'authors' => 'David Leonard, Jason Ingram, Leslie Jordan',
            'copyright' => '© 2012 Open Hands Music, Sony/ATV Tree Publishing',
            'themes' => 'praise, creation, breath of God',
            'default_key' => 'G',
            'tempo' => 78,
            'time_signature' => '6/8',
            'lyrics' => "Verse\nYou give life, You are love\nYou bring light to the darkness\nYou give hope, You restore every heart that is broken\nGreat are You, Lord\n\nChorus\nIt's Your breath in our lungs\nSo we pour out our praise\nWe pour out our praise\nIt's Your breath in our lungs\nSo we pour out our praise to You only",
            'chord_chart' => "{title: Great Are You Lord}\n{artist: All Sons & Daughters}\n{key: G}\n{tempo: 78}\n{time: 6/8}\n\n{verse}\n[G]You give life, You are love\n[C]You bring light to the [Em]darkness\n[G]You give hope, You restore\n[C]Every heart that is [Em]broken\n[C]Great are You, [D]Lord\n\n{chorus}\n[G]It's Your breath in our lungs\n[C]So we pour out our [Em]praise\nWe pour out our [D]praise\n[G]It's Your breath in our lungs\n[C]So we pour out our [Em]praise\nTo You [D]only"
        ]
    ];

    // Filter demo songs by query
    $query = strtolower($query);
    $results = [];

    foreach ($demoSongs as $song) {
        if (strpos(strtolower($song['title']), $query) !== false ||
            strpos(strtolower($song['artist']), $query) !== false ||
            strpos(strtolower($song['authors']), $query) !== false ||
            strpos($song['ccli_number'], $query) !== false ||
            strpos(strtolower($song['themes']), $query) !== false) {
            $results[] = $song;
        }
    }

    // If no matches, return all demo songs
    if (empty($results)) {
        $results = $demoSongs;
    }

    return $results;
}
