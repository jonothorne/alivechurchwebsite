-- ============================================
-- Setlist AI Learning System
-- Created: 2026-03-24
-- Purpose: Store learned patterns for AI-powered setlist generation
-- ============================================

-- ============================================
-- 1. Song Transition Patterns (Markov Chain)
-- ============================================
-- Tracks which songs typically follow which songs
CREATE TABLE IF NOT EXISTS song_transition_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_song_id INT NULL COMMENT 'NULL means start of setlist',
    to_song_id INT NOT NULL,
    user_id INT NULL COMMENT 'NULL for global patterns, or specific user',
    team_id INT NULL COMMENT 'Team-specific patterns',
    transition_count INT DEFAULT 1 COMMENT 'How many times this transition occurred',
    weight DECIMAL(5,4) DEFAULT 1.0 COMMENT 'Calculated probability weight',
    last_used DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_transition (from_song_id, to_song_id, user_id, team_id),
    INDEX idx_from_song (from_song_id),
    INDEX idx_to_song (to_song_id),
    INDEX idx_user (user_id),

    FOREIGN KEY (from_song_id) REFERENCES songs(id) ON DELETE CASCADE,
    FOREIGN KEY (to_song_id) REFERENCES songs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES service_teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. Song Position Patterns
-- ============================================
-- Tracks where songs are typically placed in setlists
CREATE TABLE IF NOT EXISTS song_position_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    song_id INT NOT NULL,
    user_id INT NULL,
    team_id INT NULL,
    position_type ENUM('opener', 'early', 'middle', 'climax', 'closer') NOT NULL,
    occurrence_count INT DEFAULT 1,
    total_uses INT DEFAULT 1 COMMENT 'Total times this song was used',
    position_score DECIMAL(5,4) DEFAULT 0.0 COMMENT 'Calculated: occurrence_count / total_uses',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_song_position (song_id, position_type, user_id, team_id),
    INDEX idx_song (song_id),
    INDEX idx_position_type (position_type),

    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES service_teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. Key Progression Patterns
-- ============================================
-- Tracks which key transitions flow well together
CREATE TABLE IF NOT EXISTS key_progression_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_key VARCHAR(10) NOT NULL,
    to_key VARCHAR(10) NOT NULL,
    user_id INT NULL,
    transition_count INT DEFAULT 1,
    smoothness_rating DECIMAL(3,2) DEFAULT 0.5 COMMENT 'User-rated or calculated smoothness',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_key_transition (from_key, to_key, user_id),
    INDEX idx_from_key (from_key),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. Song Attributes (for AI learning)
-- ============================================
-- Extended song metadata for better AI suggestions
CREATE TABLE IF NOT EXISTS song_attributes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    song_id INT NOT NULL,
    energy_level ENUM('very_low', 'low', 'medium', 'high', 'very_high') DEFAULT 'medium',
    mood VARCHAR(50) NULL COMMENT 'celebratory, reflective, intimate, triumphant, etc.',
    season_affinity VARCHAR(100) NULL COMMENT 'easter, christmas, general, etc.',
    service_type_affinity VARCHAR(100) NULL COMMENT 'sunday_morning, evening, youth, etc.',
    calculated_energy_score DECIMAL(3,2) NULL COMMENT 'AI-calculated from tempo and other factors',
    congregational_familiarity ENUM('new', 'learning', 'familiar', 'classic') DEFAULT 'familiar',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_song (song_id),

    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. Setlist Preferences (User/Team Settings)
-- ============================================
CREATE TABLE IF NOT EXISTS setlist_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    team_id INT NULL,
    preference_key VARCHAR(50) NOT NULL,
    preference_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_preference (user_id, team_id, preference_key),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES service_teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. AI Setlist Suggestions History
-- ============================================
-- Track AI suggestions and user feedback for learning
CREATE TABLE IF NOT EXISTS ai_setlist_suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    user_id INT NOT NULL,
    suggested_songs JSON NOT NULL COMMENT 'Array of song IDs in order',
    final_songs JSON NULL COMMENT 'What the user actually used',
    acceptance_rate DECIMAL(3,2) NULL COMMENT 'How much of suggestion was kept',
    feedback_notes TEXT NULL,
    model_version VARCHAR(20) DEFAULT '1.0',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_service (service_id),
    INDEX idx_user (user_id),

    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. Song Freshness Tracking
-- ============================================
-- Override the songs.last_used_date with more granular per-team tracking
CREATE TABLE IF NOT EXISTS song_usage_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    song_id INT NOT NULL,
    service_id INT NOT NULL,
    team_id INT NULL,
    used_date DATE NOT NULL,
    position_in_setlist INT NOT NULL,
    key_used VARCHAR(10) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_song (song_id),
    INDEX idx_service (service_id),
    INDEX idx_date (used_date),

    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES service_teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Insert default key progressions (music theory based)
-- ============================================
-- Same key or relative major/minor = smooth
-- Up/down whole step = moderate
-- Up/down half step = can be jarring but works
INSERT INTO key_progression_patterns (from_key, to_key, user_id, transition_count, smoothness_rating) VALUES
-- Perfect transitions (relative keys, same key)
('C', 'Am', NULL, 10, 0.95),
('G', 'Em', NULL, 10, 0.95),
('D', 'Bm', NULL, 10, 0.95),
('A', 'F#m', NULL, 10, 0.95),
('E', 'C#m', NULL, 10, 0.95),
('F', 'Dm', NULL, 10, 0.95),
('Bb', 'Gm', NULL, 10, 0.95),

-- Common progressions (circle of fifths adjacent)
('C', 'G', NULL, 8, 0.90),
('G', 'D', NULL, 8, 0.90),
('D', 'A', NULL, 8, 0.90),
('A', 'E', NULL, 8, 0.90),
('F', 'C', NULL, 8, 0.90),
('Bb', 'F', NULL, 8, 0.90),

-- Up a step transitions (common in worship)
('C', 'D', NULL, 5, 0.80),
('D', 'E', NULL, 5, 0.80),
('E', 'F#', NULL, 5, 0.75),
('G', 'A', NULL, 5, 0.80),
('A', 'B', NULL, 5, 0.80),

-- Down transitions
('G', 'F', NULL, 4, 0.70),
('A', 'G', NULL, 4, 0.75),
('D', 'C', NULL, 4, 0.75),
('E', 'D', NULL, 4, 0.75)
ON DUPLICATE KEY UPDATE smoothness_rating = VALUES(smoothness_rating);

-- ============================================
-- Insert default setlist preferences
-- ============================================
INSERT INTO setlist_preferences (user_id, team_id, preference_key, preference_value) VALUES
(NULL, NULL, 'default_setlist_length', '5'),
(NULL, NULL, 'freshness_weeks', '4'),
(NULL, NULL, 'opener_energy', 'high'),
(NULL, NULL, 'closer_energy', 'medium'),
(NULL, NULL, 'allow_back_to_back_same_key', 'true'),
(NULL, NULL, 'prefer_key_progression', 'true')
ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value);

-- ============================================
-- Migration Complete
-- ============================================
SELECT 'Setlist AI Learning System Migration Complete!' AS status;
