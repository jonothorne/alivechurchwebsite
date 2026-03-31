-- ============================================
-- Songs Enhancement Migration
-- Adds SongSelect integration support, chord charts, and service item linking
-- ============================================

-- Add song_id and position to service_items to link to songs library
ALTER TABLE service_items
ADD COLUMN song_id INT NULL AFTER notes,
ADD COLUMN position INT DEFAULT 0 AFTER sort_order;

ALTER TABLE service_items
ADD INDEX idx_song (song_id),
ADD FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE SET NULL;

-- Enhance songs table with additional fields for SongSelect integration
ALTER TABLE songs
ADD COLUMN songselect_id VARCHAR(50) NULL AFTER id,
ADD COLUMN copyright TEXT NULL AFTER lyrics,
ADD COLUMN youtube_url VARCHAR(500) NULL AFTER copyright,
ADD COLUMN spotify_url VARCHAR(500) NULL AFTER youtube_url,
ADD COLUMN tempo INT NULL AFTER default_tempo,
ADD COLUMN time_signature VARCHAR(10) NULL AFTER tempo,
ADD COLUMN themes VARCHAR(500) NULL AFTER tags,
ADD COLUMN authors VARCHAR(500) NULL AFTER artist,
ADD COLUMN is_public_domain TINYINT(1) DEFAULT 0 AFTER themes,
ADD COLUMN chord_chart_original TEXT NULL COMMENT 'Original chord chart from SongSelect',
ADD COLUMN chord_chart_key VARCHAR(10) NULL COMMENT 'Key of the original chord chart',
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE songs
ADD UNIQUE INDEX idx_songselect (songselect_id),
ADD INDEX idx_ccli (ccli_number);

-- Create table for storing chord charts in different keys
CREATE TABLE IF NOT EXISTS song_chord_charts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    song_id INT NOT NULL,
    key_signature VARCHAR(10) NOT NULL,
    chart_type ENUM('chords', 'leadsheet', 'lyrics') DEFAULT 'chords',
    content TEXT NOT NULL COMMENT 'Chord chart content with chord markers',
    source ENUM('songselect', 'manual', 'transposed') DEFAULT 'manual',
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_song_key (song_id, key_signature),
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for storing PDF chord charts from SongSelect
CREATE TABLE IF NOT EXISTS song_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    song_id INT NOT NULL,
    attachment_type ENUM('chord_chart', 'lead_sheet', 'lyrics', 'audio', 'video', 'other') DEFAULT 'other',
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NULL,
    mime_type VARCHAR(100) NULL,
    key_signature VARCHAR(10) NULL COMMENT 'For transposed charts',
    source ENUM('songselect', 'upload') DEFAULT 'upload',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_song (song_id),
    INDEX idx_type (attachment_type),
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for SongSelect API credentials (per-church)
CREATE TABLE IF NOT EXISTS songselect_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id VARCHAR(255) NULL,
    client_secret VARCHAR(255) NULL,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    token_expires_at DATETIME NULL,
    ccli_license_number VARCHAR(50) NULL,
    is_active TINYINT(1) DEFAULT 0,
    last_sync_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for tracking song usage in services (for CCLI reporting)
CREATE TABLE IF NOT EXISTS song_usage_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    song_id INT NOT NULL,
    service_id INT NULL,
    usage_date DATE NOT NULL,
    ccli_number VARCHAR(20) NULL,
    reported_to_ccli TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_song (song_id),
    INDEX idx_date (usage_date),
    INDEX idx_reported (reported_to_ccli),
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
