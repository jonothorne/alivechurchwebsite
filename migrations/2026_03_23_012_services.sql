-- Services Module Migration (Worship Planning)
-- Created: 2026-03-23
-- Purpose: Service planning, scheduling, and team management

-- ============================================
-- Service Types (e.g., Sunday AM, Sunday PM, Midweek)
-- ============================================
CREATE TABLE IF NOT EXISTS service_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    default_day ENUM('sunday','monday','tuesday','wednesday','thursday','friday','saturday') DEFAULT 'sunday',
    default_time TIME NULL,
    default_duration_minutes INT DEFAULT 90,
    color VARCHAR(7) DEFAULT '#6B7280',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Services (Individual service instances)
-- ============================================
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_type_id INT NOT NULL,
    service_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NULL,
    title VARCHAR(200) NULL COMMENT 'Optional custom title',
    notes TEXT NULL,
    status ENUM('planned', 'confirmed', 'completed', 'cancelled') DEFAULT 'planned',
    attendance_count INT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_date (service_date),
    INDEX idx_type_date (service_type_id, service_date),

    FOREIGN KEY (service_type_id) REFERENCES service_types(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Service Teams (Positions/Roles needed)
-- ============================================
CREATE TABLE IF NOT EXISTS service_teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) DEFAULT '#6B7280',
    min_required INT DEFAULT 1,
    max_allowed INT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Service Team Members (Who can serve on each team)
-- ============================================
CREATE TABLE IF NOT EXISTS service_team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('member', 'leader') DEFAULT 'member',
    is_active BOOLEAN DEFAULT TRUE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_team_user (team_id, user_id),
    INDEX idx_user (user_id),

    FOREIGN KEY (team_id) REFERENCES service_teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Service Assignments (Who is scheduled)
-- ============================================
CREATE TABLE IF NOT EXISTS service_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    position VARCHAR(100) NULL COMMENT 'Specific position (e.g., Lead Vocals, Drums)',
    status ENUM('pending', 'confirmed', 'declined') DEFAULT 'pending',
    confirmed_at TIMESTAMP NULL,
    notes TEXT NULL,
    assigned_by INT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_service (service_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),

    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES service_teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Service Items (Order of service)
-- ============================================
CREATE TABLE IF NOT EXISTS service_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    item_type ENUM('song', 'scripture', 'prayer', 'announcement', 'sermon', 'offering', 'video', 'other') DEFAULT 'other',
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    duration_minutes INT NULL,
    sort_order INT DEFAULT 0,
    notes TEXT NULL,

    -- Song-specific fields
    song_key VARCHAR(10) NULL,
    song_tempo INT NULL,
    song_ccli VARCHAR(20) NULL,

    -- Scripture-specific
    scripture_reference VARCHAR(100) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_service (service_id),

    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Songs Library
-- ============================================
CREATE TABLE IF NOT EXISTS songs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    artist VARCHAR(200) NULL,
    ccli_number VARCHAR(20) NULL,
    default_key VARCHAR(10) NULL,
    default_tempo INT NULL,
    lyrics TEXT NULL,
    notes TEXT NULL,
    tags VARCHAR(500) NULL COMMENT 'Comma-separated tags',
    times_used INT DEFAULT 0,
    last_used_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_title (title),
    INDEX idx_ccli (ccli_number),
    FULLTEXT INDEX ft_search (title, artist, lyrics)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Blockout Dates (When team members can't serve)
-- ============================================
CREATE TABLE IF NOT EXISTS service_blockouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_date (user_id, start_date, end_date),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Default Data
-- ============================================
INSERT INTO service_types (name, slug, default_day, default_time, color, sort_order) VALUES
('Sunday Morning', 'sunday-am', 'sunday', '10:30:00', '#3B82F6', 1),
('Sunday Evening', 'sunday-pm', 'sunday', '18:30:00', '#8B5CF6', 2),
('Midweek Service', 'midweek', 'wednesday', '19:30:00', '#10B981', 3);

INSERT INTO service_teams (name, slug, color, min_required, sort_order) VALUES
('Worship Team', 'worship', '#EC4899', 3, 1),
('Tech/AV', 'tech', '#6366F1', 2, 2),
('Welcome Team', 'welcome', '#F59E0B', 4, 3),
('Kids Ministry', 'kids', '#14B8A6', 2, 4),
('Prayer Team', 'prayer', '#A855F7', 2, 5);

SELECT 'Migration complete: Services module tables created' AS status;
