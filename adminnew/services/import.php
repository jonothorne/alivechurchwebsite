<?php
/**
 * Planning Center Import
 * Comprehensive import from Planning Center Services
 * Includes songs, chord charts, usage history, service types, teams, and people
 */

// Increase limits for large imports
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

$page_title = 'Planning Center Import';
$current_app = 'services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

$success = null;
$error = null;
$importResults = null;

// Load saved Planning Center credentials
$savedAppId = '';
$savedSecret = '';
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute(['pc_app_id']);
    $savedAppId = $stmt->fetchColumn() ?: '';
    $stmt->execute(['pc_secret']);
    $savedSecret = $stmt->fetchColumn() ?: '';
} catch (PDOException $e) {
    // Table might not exist or settings not saved yet
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $importType = $_POST['import_type'] ?? '';

    try {
        if ($importType === 'planning_center') {
            $appId = trim($_POST['pc_app_id'] ?? '');
            $secret = trim($_POST['pc_secret'] ?? '');

            // Save credentials if checkbox is checked
            if (isset($_POST['save_credentials']) && !empty($appId) && !empty($secret)) {
                $saveStmt = $pdo->prepare("
                    INSERT INTO site_settings (setting_key, setting_value, setting_type, setting_group, display_name)
                    VALUES (?, ?, 'text', 'integrations', ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $saveStmt->execute(['pc_app_id', $appId, 'Planning Center App ID']);
                $saveStmt->execute(['pc_secret', $secret, 'Planning Center Secret']);
                $savedAppId = $appId;
                $savedSecret = $secret;
            }

            // Import options
            $importSongs = isset($_POST['import_songs']);
            $importChords = isset($_POST['import_chords']);
            $importHistory = isset($_POST['import_history']);
            $importServiceTypes = isset($_POST['import_service_types']);
            $importTeams = isset($_POST['import_teams']);
            $importPeople = isset($_POST['import_people']);
            $importServicePlans = isset($_POST['import_service_plans']);

            if (empty($appId) || empty($secret)) {
                throw new Exception('Planning Center Application ID and Secret are required.');
            }

            $importResults = importFromPlanningCenter($pdo, $appId, $secret, [
                'songs' => $importSongs,
                'chords' => $importChords,
                'history' => $importHistory,
                'service_types' => $importServiceTypes,
                'teams' => $importTeams,
                'people' => $importPeople,
                'service_plans' => $importServicePlans
            ]);
            $success = "Import complete!";

        } elseif ($importType === 'csv') {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Please upload a valid CSV file.');
            }

            $importResults = importFromCSV($pdo, $_FILES['csv_file']['tmp_name']);
            $success = "Import complete!";

        } elseif ($importType === 'train_ai') {
            // Train AI from imported service history
            require_once __DIR__ . '/../../includes/services/SetlistAI.php';
            $setlistAI = new SetlistAI($pdo, $_SESSION['admin_user_id'] ?? null);
            $aiResults = $setlistAI->learnFromHistory();

            // Get accurate counts from database
            $transCount = 0;
            $posCount = 0;
            $keyCount = 0;
            try { $transCount = $pdo->query("SELECT COUNT(*) FROM song_transition_patterns")->fetchColumn(); } catch (PDOException $e) {}
            try { $posCount = $pdo->query("SELECT COUNT(*) FROM song_position_patterns")->fetchColumn(); } catch (PDOException $e) {}
            try { $keyCount = $pdo->query("SELECT COUNT(*) FROM key_progression_patterns")->fetchColumn(); } catch (PDOException $e) {}

            $importResults = [
                'ai_transitions' => $transCount,
                'ai_positions' => $posCount,
                'ai_keys' => $keyCount,
                'services_analyzed' => $aiResults['services_analyzed'] ?? 0
            ];
            $success = "AI training complete!";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

/**
 * Make a request to Planning Center API
 */
function pcApiRequest(string $url, string $appId, string $secret): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "{$appId}:{$secret}");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 401) {
        throw new Exception('Authentication failed. Please check your Application ID and Secret.');
    }

    if ($httpCode !== 200) {
        throw new Exception("Planning Center API returned error code: {$httpCode}");
    }

    return json_decode($response, true) ?: [];
}

/**
 * Import from Planning Center Services API
 */
function importFromPlanningCenter(PDO $pdo, string $appId, string $secret, array $options): array {
    $results = [
        'songs_imported' => 0,
        'songs_skipped' => 0,
        'songs_updated' => 0,
        'chords_imported' => 0,
        'history_records' => 0,
        'service_types_imported' => 0,
        'teams_imported' => 0,
        'team_positions_imported' => 0,
        'people_imported' => 0,
        'people_updated' => 0,
        'services_imported' => 0,
        'service_items_imported' => 0,
        'log' => []
    ];

    $baseUrl = 'https://api.planningcenteronline.com/services/v2';

    // Ensure required database columns/tables exist
    ensureDatabaseSchema($pdo);

    // Step 1: Import Service Types
    if ($options['service_types'] ?? false) {
        $serviceTypeResults = importServiceTypes($pdo, $baseUrl, $appId, $secret);
        $results['service_types_imported'] = $serviceTypeResults['imported'];
        $results['log'] = array_merge($results['log'], $serviceTypeResults['log']);
    }

    // Step 2: Import Teams and Positions
    if ($options['teams'] ?? false) {
        $teamResults = importTeams($pdo, $baseUrl, $appId, $secret);
        $results['teams_imported'] = $teamResults['teams_imported'];
        $results['team_positions_imported'] = $teamResults['positions_imported'];
        $results['log'] = array_merge($results['log'], $teamResults['log']);
    }

    // Step 3: Import People
    if ($options['people'] ?? false) {
        $peopleResults = importPeople($pdo, $baseUrl, $appId, $secret);
        $results['people_imported'] = $peopleResults['imported'];
        $results['people_updated'] = $peopleResults['updated'];
        $results['log'] = array_merge($results['log'], $peopleResults['log']);
    }

    // Step 4: Import Songs
    if ($options['songs'] ?? false) {
        $songResults = importSongs($pdo, $baseUrl, $appId, $secret, $options['chords'] ?? false);
        $results['songs_imported'] = $songResults['imported'];
        $results['songs_updated'] = $songResults['updated'];
        $results['chords_imported'] = $songResults['chords'];
        $results['log'] = array_merge($results['log'], $songResults['log']);
    }

    // Step 5: Import Usage History
    if ($options['history'] ?? false) {
        $results['history_records'] = importSongUsageHistory($pdo, $baseUrl, $appId, $secret);
    }

    // Step 6: Import Service Plans (past services)
    if ($options['service_plans'] ?? false) {
        $planResults = importServicePlans($pdo, $baseUrl, $appId, $secret);
        $results['services_imported'] = $planResults['services'];
        $results['service_items_imported'] = $planResults['items'];
        $results['log'] = array_merge($results['log'], $planResults['log']);
    }

    return $results;
}

/**
 * Ensure database schema has required columns and tables
 */
function ensureDatabaseSchema(PDO $pdo): void {
    // Add pc_song_id to songs table
    try {
        $pdo->exec("ALTER TABLE songs ADD COLUMN pc_song_id VARCHAR(50) NULL");
        $pdo->exec("CREATE INDEX idx_songs_pc_id ON songs(pc_song_id)");
    } catch (PDOException $e) {}

    // Add chord_chart column to songs table (for imported chord charts)
    try {
        $pdo->exec("ALTER TABLE songs ADD COLUMN chord_chart TEXT NULL");
    } catch (PDOException $e) {}

    // Add tempo column if missing
    try {
        $pdo->exec("ALTER TABLE songs ADD COLUMN tempo INT NULL");
    } catch (PDOException $e) {}

    // Add time_signature column if missing
    try {
        $pdo->exec("ALTER TABLE songs ADD COLUMN time_signature VARCHAR(10) NULL");
    } catch (PDOException $e) {}

    // Add updated_at column if missing
    try {
        $pdo->exec("ALTER TABLE songs ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    } catch (PDOException $e) {}

    // Add pc_id and timestamps to service_types
    try { $pdo->exec("ALTER TABLE service_types ADD COLUMN pc_service_type_id VARCHAR(50) NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE service_types ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE service_types ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"); } catch (PDOException $e) {}

    // Add pc_id and timestamps to service_teams
    try { $pdo->exec("ALTER TABLE service_teams ADD COLUMN pc_team_id VARCHAR(50) NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE service_teams ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE service_teams ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"); } catch (PDOException $e) {}

    // Create team_positions table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS team_positions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            sort_order INT DEFAULT 0,
            pc_position_id VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_team (team_id),
            FOREIGN KEY (team_id) REFERENCES service_teams(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    // Add columns if table already existed without them
    try { $pdo->exec("ALTER TABLE team_positions ADD COLUMN pc_position_id VARCHAR(50) NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE team_positions ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE team_positions ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"); } catch (PDOException $e) {}

    // Add pc_person_id, phone, and timestamps to users table
    try { $pdo->exec("ALTER TABLE users ADD COLUMN pc_person_id VARCHAR(50) NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("CREATE INDEX idx_users_pc_id ON users(pc_person_id)"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(50) NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN is_member TINYINT(1) DEFAULT 0"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN active TINYINT(1) DEFAULT 1"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"); } catch (PDOException $e) {}

    // Ensure song_usage_history table exists for AI learning
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS song_usage_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            song_id INT NOT NULL,
            service_date DATE NOT NULL,
            service_type VARCHAR(100) NULL,
            song_key VARCHAR(10) NULL,
            position_in_set INT NULL,
            imported_from VARCHAR(50) DEFAULT 'planning_center',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_song_date (song_id, service_date),
            INDEX idx_date (service_date),
            FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Import Service Types from Planning Center
 */
function importServiceTypes(PDO $pdo, string $baseUrl, string $appId, string $secret): array {
    $results = ['imported' => 0, 'log' => []];

    $url = "{$baseUrl}/service_types";
    $data = pcApiRequest($url, $appId, $secret);

    if (!isset($data['data'])) return $results;

    $checkStmt = $pdo->prepare("SELECT id FROM service_types WHERE pc_service_type_id = ? OR name = ?");
    $insertStmt = $pdo->prepare("
        INSERT INTO service_types (name, slug, description, color, pc_service_type_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $updateStmt = $pdo->prepare("UPDATE service_types SET pc_service_type_id = ? WHERE id = ?");

    // Define some nice colors for service types
    $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4'];
    $colorIndex = 0;

    foreach ($data['data'] as $serviceType) {
        $pcId = $serviceType['id'];
        $attrs = $serviceType['attributes'] ?? [];
        $name = $attrs['name'] ?? 'Unknown';

        // Check if exists
        $checkStmt->execute([$pcId, $name]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            // Update with PC ID
            $updateStmt->execute([$pcId, $existing['id']]);
            $results['log'][] = ['status' => 'updated', 'type' => 'service_type', 'name' => $name];
        } else {
            // Insert new
            $color = $colors[$colorIndex % count($colors)];
            $colorIndex++;
            // Generate slug from name
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            $slug = trim($slug, '-') . '-' . substr(md5($pcId), 0, 6); // Add hash to ensure uniqueness

            try {
                $insertStmt->execute([$name, $slug, '', $color, $pcId]);
                $results['imported']++;
                $results['log'][] = ['status' => 'imported', 'type' => 'service_type', 'name' => $name];
            } catch (PDOException $e) {
                $results['log'][] = ['status' => 'error', 'type' => 'service_type', 'name' => $name, 'reason' => $e->getMessage()];
            }
        }
    }

    return $results;
}

/**
 * Import Teams and Team Positions from Planning Center
 */
function importTeams(PDO $pdo, string $baseUrl, string $appId, string $secret): array {
    $results = ['teams_imported' => 0, 'positions_imported' => 0, 'log' => []];

    // First get all service types to get teams
    $serviceTypesUrl = "{$baseUrl}/service_types";
    $serviceTypesData = pcApiRequest($serviceTypesUrl, $appId, $secret);

    if (!isset($serviceTypesData['data'])) return $results;

    $checkTeamStmt = $pdo->prepare("SELECT id FROM service_teams WHERE pc_team_id = ? OR name = ?");
    $insertTeamStmt = $pdo->prepare("
        INSERT INTO service_teams (name, slug, description, color, is_active, pc_team_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, 1, ?, NOW(), NOW())
    ");
    $updateTeamStmt = $pdo->prepare("UPDATE service_teams SET pc_team_id = ? WHERE id = ?");

    $checkPosStmt = $pdo->prepare("SELECT id FROM team_positions WHERE team_id = ? AND (pc_position_id = ? OR name = ?)");
    $insertPosStmt = $pdo->prepare("
        INSERT INTO team_positions (team_id, name, sort_order, pc_position_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");

    // Team colors
    $teamColors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#F97316'];
    $colorIndex = 0;

    foreach ($serviceTypesData['data'] as $serviceType) {
        $serviceTypeId = $serviceType['id'];

        // Get teams for this service type
        $teamsUrl = "{$baseUrl}/service_types/{$serviceTypeId}/teams";
        try {
            $teamsData = pcApiRequest($teamsUrl, $appId, $secret);

            if (!isset($teamsData['data'])) continue;

            foreach ($teamsData['data'] as $team) {
                $pcTeamId = $team['id'];
                $teamAttrs = $team['attributes'] ?? [];
                $teamName = $teamAttrs['name'] ?? 'Unknown Team';

                // Check if team exists
                $checkTeamStmt->execute([$pcTeamId, $teamName]);
                $existingTeam = $checkTeamStmt->fetch();

                $localTeamId = null;
                if ($existingTeam) {
                    $localTeamId = $existingTeam['id'];
                    $updateTeamStmt->execute([$pcTeamId, $localTeamId]);
                    $results['log'][] = ['status' => 'updated', 'type' => 'team', 'name' => $teamName];
                } else {
                    // Insert new team
                    $color = $teamColors[$colorIndex % count($teamColors)];
                    $colorIndex++;
                    // Generate slug from team name
                    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $teamName));
                    $slug = trim($slug, '-') . '-' . substr(md5($pcTeamId), 0, 6); // Add hash to ensure uniqueness

                    try {
                        $insertTeamStmt->execute([$teamName, $slug, '', $color, $pcTeamId]);
                        $localTeamId = $pdo->lastInsertId();
                        $results['teams_imported']++;
                        $results['log'][] = ['status' => 'imported', 'type' => 'team', 'name' => $teamName];
                    } catch (PDOException $e) {
                        $results['log'][] = ['status' => 'error', 'type' => 'team', 'name' => $teamName, 'reason' => $e->getMessage()];
                        continue;
                    }
                }

                // Import team positions
                if ($localTeamId) {
                    $positionsUrl = "{$baseUrl}/teams/{$pcTeamId}/team_positions";
                    try {
                        $positionsData = pcApiRequest($positionsUrl, $appId, $secret);

                        if (isset($positionsData['data'])) {
                            $sortOrder = 0;
                            foreach ($positionsData['data'] as $position) {
                                $pcPosId = $position['id'];
                                $posAttrs = $position['attributes'] ?? [];
                                $posName = $posAttrs['name'] ?? 'Team Member';

                                $checkPosStmt->execute([$localTeamId, $pcPosId, $posName]);
                                if (!$checkPosStmt->fetch()) {
                                    $sortOrder++;
                                    try {
                                        $insertPosStmt->execute([$localTeamId, $posName, $sortOrder, $pcPosId]);
                                        $results['positions_imported']++;
                                    } catch (PDOException $e) {
                                        // Position might already exist
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // Continue on position fetch error
                    }
                }
            }
        } catch (Exception $e) {
            $results['log'][] = ['status' => 'error', 'type' => 'team_fetch', 'reason' => $e->getMessage()];
        }
    }

    return $results;
}

/**
 * Import People from Planning Center
 */
function importPeople(PDO $pdo, string $baseUrl, string $appId, string $secret): array {
    $results = ['imported' => 0, 'updated' => 0, 'log' => []];

    $perPage = 100;
    $offset = 0;
    $hasMore = true;

    $checkEmailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkPcIdStmt = $pdo->prepare("SELECT id FROM users WHERE pc_person_id = ?");

    $insertStmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, email, phone, is_member, pc_person_id, active, created_at, updated_at)
        VALUES (?, ?, ?, ?, 1, ?, 1, NOW(), NOW())
    ");
    $updateStmt = $pdo->prepare("
        UPDATE users SET
            first_name = COALESCE(NULLIF(?, ''), first_name),
            last_name = COALESCE(NULLIF(?, ''), last_name),
            phone = COALESCE(NULLIF(?, ''), phone),
            pc_person_id = ?
        WHERE id = ?
    ");

    while ($hasMore) {
        $url = "{$baseUrl}/people?per_page={$perPage}&offset={$offset}";
        try {
            $data = pcApiRequest($url, $appId, $secret);

            if (!isset($data['data']) || !is_array($data['data'])) {
                break;
            }

            foreach ($data['data'] as $person) {
                $pcPersonId = $person['id'];
                $attrs = $person['attributes'] ?? [];

                $firstName = $attrs['first_name'] ?? '';
                $lastName = $attrs['last_name'] ?? '';
                $email = $attrs['email'] ?? null;
                $phone = $attrs['phone'] ?? null;

                // Skip people without email or first name
                if (empty($firstName) && empty($email)) continue;

                // Check if exists
                $existingId = null;

                // Check by PC ID first
                $checkPcIdStmt->execute([$pcPersonId]);
                if ($row = $checkPcIdStmt->fetch()) {
                    $existingId = $row['id'];
                }

                // Then by email
                if (!$existingId && !empty($email)) {
                    $checkEmailStmt->execute([$email]);
                    if ($row = $checkEmailStmt->fetch()) {
                        $existingId = $row['id'];
                    }
                }

                if ($existingId) {
                    // Update existing
                    $updateStmt->execute([$firstName, $lastName, $phone, $pcPersonId, $existingId]);
                    $results['updated']++;
                } else {
                    // Insert new
                    // Generate a placeholder email if none exists
                    $userEmail = $email ?: strtolower($firstName . '.' . $lastName . '.pc' . $pcPersonId . '@placeholder.local');

                    try {
                        $insertStmt->execute([$firstName, $lastName, $userEmail, $phone, $pcPersonId]);
                        $results['imported']++;
                        $results['log'][] = ['status' => 'imported', 'type' => 'person', 'name' => "$firstName $lastName"];
                    } catch (PDOException $e) {
                        $results['log'][] = ['status' => 'error', 'type' => 'person', 'name' => "$firstName $lastName", 'reason' => $e->getMessage()];
                    }
                }
            }

            $meta = $data['meta'] ?? [];
            $totalCount = $meta['total_count'] ?? 0;
            $offset += $perPage;
            $hasMore = $offset < $totalCount && $offset < 2000; // Safety limit for people
        } catch (Exception $e) {
            $results['log'][] = ['status' => 'error', 'type' => 'people_fetch', 'reason' => $e->getMessage()];
            break;
        }
    }

    return $results;
}

/**
 * Import Songs from Planning Center
 */
function importSongs(PDO $pdo, string $baseUrl, string $appId, string $secret, bool $importChords): array {
    $results = ['imported' => 0, 'updated' => 0, 'chords' => 0, 'log' => []];

    $perPage = 100;
    $offset = 0;
    $hasMore = true;

    $insertSongStmt = $pdo->prepare("
        INSERT INTO songs (title, artist, ccli_number, default_key, tempo, time_signature, pc_song_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    $updateSongStmt = $pdo->prepare("
        UPDATE songs SET
            artist = COALESCE(?, artist),
            ccli_number = COALESCE(?, ccli_number),
            pc_song_id = ?
        WHERE id = ?
    ");

    $checkCcliStmt = $pdo->prepare("SELECT id FROM songs WHERE ccli_number = ? AND ccli_number IS NOT NULL AND ccli_number != ''");
    $checkTitleStmt = $pdo->prepare("SELECT id FROM songs WHERE title = ?");
    $checkPcIdStmt = $pdo->prepare("SELECT id FROM songs WHERE pc_song_id = ?");

    while ($hasMore) {
        $url = "{$baseUrl}/songs?per_page={$perPage}&offset={$offset}";
        $data = pcApiRequest($url, $appId, $secret);

        if (!isset($data['data']) || !is_array($data['data'])) {
            break;
        }

        foreach ($data['data'] as $song) {
            $pcSongId = $song['id'];
            $attrs = $song['attributes'] ?? [];
            $title = $attrs['title'] ?? '';
            $author = $attrs['author'] ?? null;
            $ccli = $attrs['ccli_number'] ?? null;

            if (empty($title)) continue;

            // Check if song exists
            $existingId = null;

            // Check by PC ID first
            $checkPcIdStmt->execute([$pcSongId]);
            if ($row = $checkPcIdStmt->fetch()) {
                $existingId = $row['id'];
            }

            // Then by CCLI
            if (!$existingId && !empty($ccli)) {
                $checkCcliStmt->execute([$ccli]);
                if ($row = $checkCcliStmt->fetch()) {
                    $existingId = $row['id'];
                }
            }

            // Then by title
            if (!$existingId) {
                $checkTitleStmt->execute([$title]);
                if ($row = $checkTitleStmt->fetch()) {
                    $existingId = $row['id'];
                }
            }

            if ($existingId) {
                // Update existing song with PC ID
                $updateSongStmt->execute([$author, $ccli, $pcSongId, $existingId]);
                $results['updated']++;
                $results['log'][] = ['status' => 'updated', 'type' => 'song', 'title' => $title];
            } else {
                // Insert new song
                try {
                    $insertSongStmt->execute([
                        $title, $author, $ccli ?: null, null, null, null, $pcSongId
                    ]);
                    $existingId = $pdo->lastInsertId();
                    $results['imported']++;
                    $results['log'][] = ['status' => 'imported', 'type' => 'song', 'title' => $title];
                } catch (PDOException $e) {
                    $results['log'][] = ['status' => 'error', 'type' => 'song', 'title' => $title, 'reason' => $e->getMessage()];
                    continue;
                }
            }

            // Import chord charts if requested
            if ($importChords && $existingId) {
                $chordsImported = importSongChordCharts($pdo, $baseUrl, $appId, $secret, $pcSongId, $existingId);
                $results['chords'] += $chordsImported;
            }
        }

        $meta = $data['meta'] ?? [];
        $totalCount = $meta['total_count'] ?? 0;
        $offset += $perPage;
        $hasMore = $offset < $totalCount && $offset < 5000; // Safety limit
    }

    return $results;
}

/**
 * Import chord charts for a specific song
 */
function importSongChordCharts(PDO $pdo, string $baseUrl, string $appId, string $secret, string $pcSongId, int $localSongId): int {
    $imported = 0;

    try {
        // Get arrangements for this song
        $url = "{$baseUrl}/songs/{$pcSongId}/arrangements";
        $data = pcApiRequest($url, $appId, $secret);

        if (!isset($data['data'])) return 0;

        foreach ($data['data'] as $arrangement) {
            $arrId = $arrangement['id'];
            $arrAttrs = $arrangement['attributes'] ?? [];
            $key = $arrAttrs['chord_chart_key'] ?? null;
            $chordChart = $arrAttrs['chord_chart'] ?? null;

            // If there's a chord chart in the arrangement, save it
            if (!empty($chordChart)) {
                // Update the song with this chord chart
                $updateStmt = $pdo->prepare("
                    UPDATE songs SET
                        chord_chart = ?,
                        default_key = COALESCE(?, default_key),
                        updated_at = NOW()
                    WHERE id = ? AND (chord_chart IS NULL OR chord_chart = '')
                ");
                $updateStmt->execute([$chordChart, $key, $localSongId]);

                if ($updateStmt->rowCount() > 0) {
                    $imported++;
                }
            }

            // Also check for attachments (chord chart files)
            $attachUrl = "{$baseUrl}/songs/{$pcSongId}/arrangements/{$arrId}/attachments";
            try {
                $attachData = pcApiRequest($attachUrl, $appId, $secret);

                if (isset($attachData['data'])) {
                    foreach ($attachData['data'] as $attach) {
                        $attachAttrs = $attach['attributes'] ?? [];
                        $filename = strtolower($attachAttrs['filename'] ?? '');
                        $attachmentType = $attachAttrs['attachment_type'] ?? '';

                        // Look for chord chart files
                        if ($attachmentType === 'chord_chart' ||
                            strpos($filename, 'chord') !== false ||
                            strpos($filename, '.chopro') !== false ||
                            strpos($filename, '.cho') !== false) {

                            // Get the download URL and fetch content
                            $downloadUrl = $attachAttrs['url'] ?? null;
                            if ($downloadUrl) {
                                $content = fetchAttachmentContent($downloadUrl, $appId, $secret);
                                if ($content && strlen($content) > 10) {
                                    $updateStmt = $pdo->prepare("
                                        UPDATE songs SET
                                            chord_chart = ?,
                                            updated_at = NOW()
                                        WHERE id = ? AND (chord_chart IS NULL OR chord_chart = '')
                                    ");
                                    $updateStmt->execute([$content, $localSongId]);
                                    if ($updateStmt->rowCount() > 0) {
                                        $imported++;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Attachment fetch failed, continue
            }
        }
    } catch (Exception $e) {
        // API call failed, continue
    }

    return $imported;
}

/**
 * Fetch attachment content from Planning Center
 */
function fetchAttachmentContent(string $url, string $appId, string $secret): ?string {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "{$appId}:{$secret}");
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200) ? $content : null;
}

/**
 * Import song usage history from Planning Center service plans
 */
function importSongUsageHistory(PDO $pdo, string $baseUrl, string $appId, string $secret): int {
    $historyCount = 0;

    // Get service types first
    $serviceTypesUrl = "{$baseUrl}/service_types";
    $serviceTypesData = pcApiRequest($serviceTypesUrl, $appId, $secret);

    if (!isset($serviceTypesData['data'])) return 0;

    $insertHistoryStmt = $pdo->prepare("
        INSERT IGNORE INTO song_usage_history (song_id, used_date, key_used, position_in_setlist)
        VALUES (?, ?, ?, ?)
    ");

    $getSongByPcId = $pdo->prepare("SELECT id FROM songs WHERE pc_song_id = ?");

    foreach ($serviceTypesData['data'] as $serviceType) {
        $serviceTypeId = $serviceType['id'];
        $serviceTypeName = $serviceType['attributes']['name'] ?? 'Unknown';

        // Get plans for this service type (last 2 years)
        $twoYearsAgo = date('Y-m-d', strtotime('-2 years'));
        $plansUrl = "{$baseUrl}/service_types/{$serviceTypeId}/plans?filter=after&after={$twoYearsAgo}&per_page=100";

        try {
            $plansData = pcApiRequest($plansUrl, $appId, $secret);

            if (!isset($plansData['data'])) continue;

            foreach ($plansData['data'] as $plan) {
                $planId = $plan['id'];
                $planDate = $plan['attributes']['sort_date'] ?? null;

                if (!$planDate) continue;
                $planDate = substr($planDate, 0, 10); // Just the date part

                // Get items (songs) in this plan
                $itemsUrl = "{$baseUrl}/service_types/{$serviceTypeId}/plans/{$planId}/items?include=song";

                try {
                    $itemsData = pcApiRequest($itemsUrl, $appId, $secret);

                    if (!isset($itemsData['data'])) continue;

                    $position = 0;
                    foreach ($itemsData['data'] as $item) {
                        $itemAttrs = $item['attributes'] ?? [];
                        $itemType = $itemAttrs['item_type'] ?? '';

                        if ($itemType !== 'song') continue;

                        $position++;
                        $songKey = $itemAttrs['key_name'] ?? null;

                        // Get the song relationship
                        $songRel = $item['relationships']['song']['data'] ?? null;
                        if (!$songRel) continue;

                        $pcSongId = $songRel['id'];

                        // Find our local song
                        $getSongByPcId->execute([$pcSongId]);
                        $localSong = $getSongByPcId->fetch();

                        if ($localSong) {
                            try {
                                $insertHistoryStmt->execute([
                                    $localSong['id'],
                                    $planDate,
                                    $songKey,
                                    $position
                                ]);
                                $historyCount++;
                            } catch (PDOException $e) {
                                // Duplicate, ignore
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Continue on error
                }
            }
        } catch (Exception $e) {
            // Continue on error
        }
    }

    return $historyCount;
}

/**
 * Import Service Plans (past services) from Planning Center
 */
function importServicePlans(PDO $pdo, string $baseUrl, string $appId, string $secret): array {
    $results = ['services' => 0, 'items' => 0, 'log' => []];

    // Get service types to map PC IDs to local IDs
    $serviceTypeMap = [];
    $stmt = $pdo->query("SELECT id, pc_service_type_id FROM service_types WHERE pc_service_type_id IS NOT NULL");
    while ($row = $stmt->fetch()) {
        $serviceTypeMap[$row['pc_service_type_id']] = $row['id'];
    }

    if (empty($serviceTypeMap)) {
        $results['log'][] = ['status' => 'error', 'type' => 'service_plans', 'reason' => 'No service types imported. Import service types first.'];
        return $results;
    }

    // Get song map for linking service items to songs
    $songMap = [];
    $stmt = $pdo->query("SELECT id, pc_song_id FROM songs WHERE pc_song_id IS NOT NULL");
    while ($row = $stmt->fetch()) {
        $songMap[$row['pc_song_id']] = $row['id'];
    }

    // Prepare statements
    $checkServiceStmt = $pdo->prepare("SELECT id FROM services WHERE service_type_id = ? AND service_date = ? AND start_time = ?");
    $insertServiceStmt = $pdo->prepare("
        INSERT INTO services (service_type_id, service_date, start_time, title, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'completed', NOW(), NOW())
    ");
    $insertItemStmt = $pdo->prepare("
        INSERT INTO service_items (service_id, item_type, title, sort_order, song_id, song_key, notes, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    // Import last 2 years of services from each service type
    $twoYearsAgo = date('Y-m-d', strtotime('-2 years'));

    foreach ($serviceTypeMap as $pcTypeId => $localTypeId) {
        $plansUrl = "{$baseUrl}/service_types/{$pcTypeId}/plans?filter=after&after={$twoYearsAgo}&per_page=100";

        try {
            $plansData = pcApiRequest($plansUrl, $appId, $secret);

            if (!isset($plansData['data'])) continue;

            foreach ($plansData['data'] as $plan) {
                $planId = $plan['id'];
                $planAttrs = $plan['attributes'] ?? [];
                $planDate = $planAttrs['sort_date'] ?? null;
                $planTitle = $planAttrs['title'] ?? null;

                if (!$planDate) continue;

                // Parse date and time
                $dateTime = new DateTime($planDate);
                $serviceDate = $dateTime->format('Y-m-d');
                $startTime = $dateTime->format('H:i:s');

                // Check if service already exists
                $checkServiceStmt->execute([$localTypeId, $serviceDate, $startTime]);
                if ($checkServiceStmt->fetch()) {
                    continue; // Skip existing
                }

                // Insert service
                try {
                    $insertServiceStmt->execute([
                        $localTypeId,
                        $serviceDate,
                        $startTime,
                        $planTitle
                    ]);
                    $localServiceId = $pdo->lastInsertId();
                    $results['services']++;

                    // Import items for this plan
                    $itemsUrl = "{$baseUrl}/service_types/{$pcTypeId}/plans/{$planId}/items?include=song";

                    try {
                        $itemsData = pcApiRequest($itemsUrl, $appId, $secret);

                        if (isset($itemsData['data'])) {
                            $sortOrder = 0;
                            foreach ($itemsData['data'] as $item) {
                                $itemAttrs = $item['attributes'] ?? [];
                                $itemType = $itemAttrs['item_type'] ?? 'other';
                                $itemTitle = $itemAttrs['title'] ?? '';
                                $itemKey = $itemAttrs['key_name'] ?? null;
                                $itemNotes = $itemAttrs['description'] ?? null;

                                // Map item type
                                $localItemType = 'other';
                                if ($itemType === 'song') $localItemType = 'song';
                                elseif ($itemType === 'header') $localItemType = 'other';
                                elseif ($itemType === 'item') $localItemType = 'other';

                                // Get song_id if this is a song
                                $songId = null;
                                if ($itemType === 'song') {
                                    $songRel = $item['relationships']['song']['data'] ?? null;
                                    if ($songRel && isset($songMap[$songRel['id']])) {
                                        $songId = $songMap[$songRel['id']];
                                    }
                                }

                                $sortOrder++;

                                try {
                                    $insertItemStmt->execute([
                                        $localServiceId,
                                        $localItemType,
                                        $itemTitle,
                                        $sortOrder,
                                        $songId,
                                        $itemKey,
                                        $itemNotes
                                    ]);
                                    $results['items']++;
                                } catch (PDOException $e) {
                                    // Continue on item error
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // Continue on items fetch error
                    }

                } catch (PDOException $e) {
                    $results['log'][] = ['status' => 'error', 'type' => 'service', 'date' => $serviceDate, 'reason' => $e->getMessage()];
                }
            }
        } catch (Exception $e) {
            $results['log'][] = ['status' => 'error', 'type' => 'plans_fetch', 'reason' => $e->getMessage()];
        }
    }

    return $results;
}

/**
 * Import songs from CSV file
 */
function importFromCSV(PDO $pdo, string $filePath): array {
    $results = [
        'songs_imported' => 0,
        'songs_skipped' => 0,
        'log' => []
    ];

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception('Could not open CSV file.');
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
        throw new Exception('CSV file is empty or invalid.');
    }

    $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

    $titleCol = array_search('title', $headers);
    $artistCol = array_search('artist', $headers) ?: array_search('author', $headers);
    $ccliCol = array_search('ccli number', $headers) ?: array_search('ccli', $headers);
    $keyCol = array_search('key', $headers) ?: array_search('default key', $headers);
    $tempoCol = array_search('tempo', $headers) ?: array_search('bpm', $headers);
    $timeSigCol = array_search('time signature', $headers) ?: array_search('time', $headers);

    if ($titleCol === false) {
        throw new Exception('CSV must have a "Title" column.');
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO songs (title, artist, ccli_number, default_key, tempo, time_signature, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $checkTitleStmt = $pdo->prepare("SELECT id FROM songs WHERE title = ?");

    while (($row = fgetcsv($handle)) !== false) {
        $title = trim($row[$titleCol] ?? '');
        if (empty($title)) continue;

        $checkTitleStmt->execute([$title]);
        if ($checkTitleStmt->fetch()) {
            $results['songs_skipped']++;
            $results['log'][] = ['status' => 'skipped', 'title' => $title, 'reason' => 'Already exists'];
            continue;
        }

        try {
            $insertStmt->execute([
                $title,
                $artistCol !== false ? trim($row[$artistCol] ?? '') ?: null : null,
                $ccliCol !== false ? trim($row[$ccliCol] ?? '') ?: null : null,
                $keyCol !== false ? trim($row[$keyCol] ?? '') ?: null : null,
                $tempoCol !== false ? trim($row[$tempoCol] ?? '') ?: null : null,
                $timeSigCol !== false ? trim($row[$timeSigCol] ?? '') ?: null : null
            ]);
            $results['songs_imported']++;
            $results['log'][] = ['status' => 'imported', 'title' => $title];
        } catch (PDOException $e) {
            $results['log'][] = ['status' => 'error', 'title' => $title, 'reason' => $e->getMessage()];
        }
    }

    fclose($handle);
    return $results;
}

// Get current stats (with error handling for missing tables/columns)
$songCount = 0;
$historyCount = 0;
$chordsCount = 0;
$serviceTypeCount = 0;
$teamCount = 0;
$memberCount = 0;

try { $songCount = $pdo->query("SELECT COUNT(*) FROM songs")->fetchColumn(); } catch (PDOException $e) {}
try { $historyCount = $pdo->query("SELECT COUNT(*) FROM song_usage_history")->fetchColumn(); } catch (PDOException $e) {}
try { $chordsCount = $pdo->query("SELECT COUNT(*) FROM songs WHERE chord_chart IS NOT NULL AND chord_chart != ''")->fetchColumn(); } catch (PDOException $e) {}
try { $serviceTypeCount = $pdo->query("SELECT COUNT(*) FROM service_types")->fetchColumn(); } catch (PDOException $e) {}
try { $teamCount = $pdo->query("SELECT COUNT(*) FROM service_teams")->fetchColumn(); } catch (PDOException $e) {}
try { $memberCount = $pdo->query("SELECT COUNT(*) FROM users WHERE is_member = 1")->fetchColumn(); } catch (PDOException $e) {}
try { $servicesCount = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn(); } catch (PDOException $e) { $servicesCount = 0; }

// AI learning stats
$aiTransitionsCount = 0;
$aiPositionsCount = 0;
$aiKeysCount = 0;
try { $aiTransitionsCount = $pdo->query("SELECT COUNT(*) FROM song_transition_patterns")->fetchColumn(); } catch (PDOException $e) {}
try { $aiPositionsCount = $pdo->query("SELECT COUNT(*) FROM song_position_patterns")->fetchColumn(); } catch (PDOException $e) {}
try { $aiKeysCount = $pdo->query("SELECT COUNT(*) FROM key_progression_patterns")->fetchColumn(); } catch (PDOException $e) {}
$aiTotalPatterns = $aiTransitionsCount + $aiPositionsCount + $aiKeysCount;
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Planning Center Import</h1>
        <p class="admin-page-subtitle">Import your data from Planning Center Services</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/services" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Services
        </a>
    </div>
</div>

<?php if ($success): ?>
<div class="admin-alert admin-alert-success">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
        <polyline points="22 4 12 14.01 9 11.01"></polyline>
    </svg>
    <div>
        <strong><?= htmlspecialchars($success) ?></strong>
        <?php if ($importResults): ?>
        <ul style="margin: 0.5rem 0 0; padding-left: 1.25rem;">
            <?php if (($importResults['service_types_imported'] ?? 0) > 0): ?>
            <li><?= $importResults['service_types_imported'] ?> service types imported</li>
            <?php endif; ?>
            <?php if (($importResults['teams_imported'] ?? 0) > 0): ?>
            <li><?= $importResults['teams_imported'] ?> teams imported</li>
            <?php endif; ?>
            <?php if (($importResults['team_positions_imported'] ?? 0) > 0): ?>
            <li><?= $importResults['team_positions_imported'] ?> team positions imported</li>
            <?php endif; ?>
            <?php if (($importResults['people_imported'] ?? 0) > 0): ?>
            <li><?= $importResults['people_imported'] ?> people imported</li>
            <?php endif; ?>
            <?php if (($importResults['people_updated'] ?? 0) > 0): ?>
            <li><?= $importResults['people_updated'] ?> people updated</li>
            <?php endif; ?>
            <?php if (($importResults['songs_imported'] ?? 0) > 0): ?>
            <li><?= $importResults['songs_imported'] ?> songs imported</li>
            <?php endif; ?>
            <?php if (($importResults['songs_updated'] ?? 0) > 0): ?>
            <li><?= $importResults['songs_updated'] ?> songs updated</li>
            <?php endif; ?>
            <?php if (($importResults['chords_imported'] ?? 0) > 0): ?>
            <li><?= $importResults['chords_imported'] ?> chord charts imported</li>
            <?php endif; ?>
            <?php if (($importResults['history_records'] ?? 0) > 0): ?>
            <li><?= $importResults['history_records'] ?> usage history records imported</li>
            <?php endif; ?>
            <?php if (($importResults['services_imported'] ?? 0) > 0): ?>
            <li><?= $importResults['services_imported'] ?> services imported</li>
            <?php endif; ?>
            <?php if (($importResults['service_items_imported'] ?? 0) > 0): ?>
            <li><?= $importResults['service_items_imported'] ?> service items imported</li>
            <?php endif; ?>
            <?php if (($importResults['services_analyzed'] ?? 0) > 0): ?>
            <li><?= $importResults['services_analyzed'] ?> services analyzed for AI training</li>
            <?php endif; ?>
            <?php if (($importResults['ai_transitions'] ?? 0) > 0): ?>
            <li><?= $importResults['ai_transitions'] ?> song transition patterns learned</li>
            <?php endif; ?>
            <?php if (($importResults['ai_positions'] ?? 0) > 0): ?>
            <li><?= $importResults['ai_positions'] ?> position patterns learned</li>
            <?php endif; ?>
            <?php if (($importResults['ai_keys'] ?? 0) > 0): ?>
            <li><?= $importResults['ai_keys'] ?> key progression patterns learned</li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="admin-alert admin-alert-danger">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="8" x2="12" y2="12"></line>
        <line x1="12" y1="16" x2="12.01" y2="16"></line>
    </svg>
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- Current Library Stats -->
<div class="stats-row">
    <div class="stat-card">
        <span class="stat-value"><?= number_format($serviceTypeCount) ?></span>
        <span class="stat-label">Service Types</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= number_format($teamCount) ?></span>
        <span class="stat-label">Teams</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= number_format($servicesCount) ?></span>
        <span class="stat-label">Services</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= number_format($memberCount) ?></span>
        <span class="stat-label">Members</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= number_format($songCount) ?></span>
        <span class="stat-label">Songs</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= number_format($chordsCount) ?></span>
        <span class="stat-label">Chord Charts</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= number_format($historyCount) ?></span>
        <span class="stat-label">Usage Records</span>
    </div>
    <div class="stat-card <?= $aiTotalPatterns > 0 ? 'stat-card-ai' : '' ?>">
        <span class="stat-value"><?= number_format($aiTotalPatterns) ?></span>
        <span class="stat-label">AI Patterns</span>
    </div>
</div>

<!-- Planning Center Import -->
<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="admin-card-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M8 12h8"></path>
                <path d="M12 8v8"></path>
            </svg>
            Planning Center Import
        </h2>
    </div>
    <div class="admin-card-body">
        <p class="admin-text-muted" style="margin-bottom: 1rem;">
            Import your service types, teams, people, songs, chord charts, and usage history from Planning Center Services.
        </p>

        <div class="import-steps">
            <h4>Setup Instructions:</h4>
            <ol>
                <li>Go to <a href="https://api.planningcenteronline.com/oauth/applications" target="_blank" rel="noopener">Planning Center Developer Portal</a></li>
                <li>Click "New Personal Access Token"</li>
                <li>Give it a name (e.g., "Alive Church Import")</li>
                <li>Copy the <strong>Application ID</strong> and <strong>Secret</strong></li>
            </ol>
        </div>

        <form method="POST" action="" class="import-form" id="pc-import-form">
            <input type="hidden" name="import_type" value="planning_center">

            <?php if ($savedAppId && $savedSecret): ?>
            <div class="credentials-saved-notice">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                Credentials saved
            </div>
            <?php endif; ?>

            <div class="admin-form-group">
                <label class="admin-label">Application ID</label>
                <input type="text" name="pc_app_id" class="admin-input" placeholder="e.g., abc123def456..." value="<?= htmlspecialchars($savedAppId) ?>" required>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Secret</label>
                <input type="password" name="pc_secret" class="admin-input" placeholder="Your secret token" value="<?= htmlspecialchars($savedSecret) ?>" required>
            </div>

            <?php if (!$savedAppId || !$savedSecret): ?>
            <div class="admin-form-group">
                <label class="admin-checkbox">
                    <input type="checkbox" name="save_credentials" checked>
                    <span>Save credentials for future imports</span>
                </label>
            </div>
            <?php else: ?>
            <input type="hidden" name="save_credentials" value="1">
            <?php endif; ?>

            <div class="admin-form-group">
                <label class="admin-label">What to Import</label>

                <div class="import-options-grid">
                    <div class="import-option-card">
                        <label class="admin-checkbox">
                            <input type="checkbox" name="import_service_types" checked>
                            <span><strong>Service Types</strong></span>
                        </label>
                        <p class="option-desc">Sunday services, midweek, special events, etc.</p>
                    </div>

                    <div class="import-option-card">
                        <label class="admin-checkbox">
                            <input type="checkbox" name="import_teams" checked>
                            <span><strong>Teams &amp; Positions</strong></span>
                        </label>
                        <p class="option-desc">Worship team, tech team, and all team positions.</p>
                    </div>

                    <div class="import-option-card">
                        <label class="admin-checkbox">
                            <input type="checkbox" name="import_people" checked>
                            <span><strong>People</strong></span>
                        </label>
                        <p class="option-desc">All team members and volunteers from Planning Center.</p>
                    </div>

                    <div class="import-option-card">
                        <label class="admin-checkbox">
                            <input type="checkbox" name="import_songs" checked>
                            <span><strong>Songs</strong></span>
                        </label>
                        <p class="option-desc">Your entire song library with metadata.</p>
                    </div>

                    <div class="import-option-card">
                        <label class="admin-checkbox">
                            <input type="checkbox" name="import_chords" checked>
                            <span><strong>Chord Charts</strong></span>
                        </label>
                        <p class="option-desc">ChordPro charts from song arrangements.</p>
                    </div>

                    <div class="import-option-card">
                        <label class="admin-checkbox">
                            <input type="checkbox" name="import_history" checked>
                            <span><strong>Usage History</strong></span>
                        </label>
                        <p class="option-desc">2 years of service history for AI suggestions.</p>
                    </div>

                    <div class="import-option-card">
                        <label class="admin-checkbox">
                            <input type="checkbox" name="import_service_plans">
                            <span><strong>Service Plans</strong></span>
                        </label>
                        <p class="option-desc">Import past 2 years of services with setlists.</p>
                    </div>
                </div>
            </div>

            <button type="submit" class="admin-btn admin-btn-primary admin-btn-lg">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Import from Planning Center
            </button>
        </form>
    </div>
</div>

<div class="import-grid">
    <!-- CSV Import -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                CSV Song Import
            </h2>
        </div>
        <div class="admin-card-body">
            <p class="admin-text-muted" style="margin-bottom: 1rem;">
                Upload a CSV file with your song library.
            </p>

            <div class="import-steps">
                <h4>CSV Format:</h4>
                <p>Include these columns (Title is required):</p>
                <code style="display: block; padding: 0.5rem; background: var(--admin-bg); border-radius: 4px; font-size: 0.75rem;">
                    Title, Artist, CCLI Number, Key, Tempo, Time Signature
                </code>
            </div>

            <form method="POST" enctype="multipart/form-data" class="import-form">
                <input type="hidden" name="import_type" value="csv">

                <div class="admin-form-group">
                    <label class="admin-label">CSV File</label>
                    <input type="file" name="csv_file" class="admin-input" accept=".csv" required>
                </div>

                <button type="submit" class="admin-btn admin-btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    Upload &amp; Import
                </button>
            </form>
        </div>
    </div>
</div>

<!-- AI Training Section -->
<div class="admin-card" style="margin-top: 1.5rem;">
    <div class="admin-card-header">
        <h2 class="admin-card-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                <line x1="9" y1="9" x2="9.01" y2="9"></line>
                <line x1="15" y1="9" x2="15.01" y2="9"></line>
            </svg>
            AI Setlist Training
        </h2>
    </div>
    <div class="admin-card-body">
        <p class="admin-text-muted" style="margin-bottom: 1rem;">
            Train the AI to make smart setlist suggestions based on your service history. The AI learns song transitions,
            position preferences, and key progressions from your past services.
        </p>

        <div class="ai-stats-grid">
            <div class="ai-stat">
                <span class="ai-stat-value"><?= number_format($aiTransitionsCount) ?></span>
                <span class="ai-stat-label">Song Transitions</span>
            </div>
            <div class="ai-stat">
                <span class="ai-stat-value"><?= number_format($aiPositionsCount) ?></span>
                <span class="ai-stat-label">Position Patterns</span>
            </div>
            <div class="ai-stat">
                <span class="ai-stat-value"><?= number_format($aiKeysCount) ?></span>
                <span class="ai-stat-label">Key Progressions</span>
            </div>
        </div>

        <?php if ($servicesCount > 0): ?>
        <div class="import-steps">
            <h4>How Training Works:</h4>
            <ul>
                <li>The AI analyzes all confirmed services with song items</li>
                <li>It learns which songs typically follow each other (transitions)</li>
                <li>It learns common positions for each song (opener, closer, etc.)</li>
                <li>It learns key progressions that sound good together</li>
                <li>This data powers the smart setlist suggestions when building services</li>
            </ul>
        </div>

        <form method="POST" action="" class="import-form">
            <input type="hidden" name="import_type" value="train_ai">
            <button type="submit" class="admin-btn admin-btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"></path>
                </svg>
                <?= $aiTotalPatterns > 0 ? 'Retrain AI from History' : 'Train AI from Service History' ?>
            </button>
            <?php if ($aiTotalPatterns > 0): ?>
            <p class="form-help-text">AI has already been trained. Retraining will update patterns with any new services.</p>
            <?php endif; ?>
        </form>
        <?php else: ?>
        <div class="admin-alert admin-alert-info">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            Import service plans first to train the AI. The AI needs historical service data to learn patterns.
        </div>
        <?php endif; ?>
    </div>
</div>

<style <?= csp_nonce(); ?>>
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: var(--admin-card-bg);
    border-radius: var(--admin-radius);
    padding: 1rem;
    text-align: center;
    border: 1px solid var(--admin-border);
}

.stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--admin-text);
}

.stat-label {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}

.import-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.import-steps {
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.import-steps h4 {
    margin: 0 0 0.5rem 0;
    font-size: 0.875rem;
}

.import-steps ol, .import-steps ul, .import-steps p {
    margin: 0;
    padding-left: 1.25rem;
    font-size: 0.8125rem;
    color: var(--admin-text-muted);
}

.import-steps li {
    margin-bottom: 0.25rem;
}

.import-steps a {
    color: var(--current-app-color);
}

.import-options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.import-option-card {
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
    padding: 0.875rem;
    border: 1px solid var(--admin-border);
}

.import-option-card .admin-checkbox {
    margin-bottom: 0.25rem;
}

.import-option-card .option-desc {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
    margin: 0;
    padding-left: 1.5rem;
}

.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-help-text {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
    margin-top: 0.5rem;
}

.admin-btn-lg {
    padding: 0.875rem 1.5rem;
    font-size: 1rem;
}

.admin-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: var(--admin-radius);
    margin-bottom: 1rem;
}

.admin-alert svg {
    flex-shrink: 0;
    margin-top: 0.125rem;
}

.admin-alert-success {
    background: color-mix(in srgb, var(--admin-success) 15%, var(--admin-card-bg));
    color: var(--admin-success);
}

.admin-alert-danger {
    background: color-mix(in srgb, var(--admin-danger) 15%, var(--admin-card-bg));
    color: var(--admin-danger);
}

.credentials-saved-notice {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 0.875rem;
    background: color-mix(in srgb, var(--admin-success) 15%, var(--admin-card-bg));
    color: var(--admin-success);
    border-radius: var(--admin-radius);
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 1rem;
}

.btn-loading {
    position: relative;
    pointer-events: none;
    opacity: 0.7;
}

.btn-loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid transparent;
    border-top-color: currentColor;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* AI Training Section */
.ai-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.ai-stat {
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
    padding: 1rem;
    text-align: center;
    border: 1px solid var(--admin-border);
}

.ai-stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--admin-primary);
}

.ai-stat-label {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}

.stat-card-ai {
    border-color: var(--admin-primary);
    background: color-mix(in srgb, var(--admin-primary) 8%, var(--admin-card-bg));
}

.stat-card-ai .stat-value {
    color: var(--admin-primary);
}

.admin-alert-info {
    background: color-mix(in srgb, var(--admin-primary) 15%, var(--admin-card-bg));
    color: var(--admin-primary);
}
</style>

<script <?= csp_nonce(); ?>>
document.addEventListener('DOMContentLoaded', function() {
    // Handle form submission with loading state
    document.querySelectorAll('.import-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var btn = form.querySelector('button[type="submit"]');
            var importType = form.querySelector('input[name="import_type"]');
            if (btn) {
                btn.classList.add('btn-loading');
                if (importType && importType.value === 'train_ai') {
                    btn.innerHTML = 'Training AI... Please wait';
                } else {
                    btn.innerHTML = 'Importing... Please wait';
                }
                btn.disabled = true;
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
