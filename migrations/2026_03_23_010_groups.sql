-- Groups Module Migration
-- Created: 2026-03-23
-- Purpose: Small groups / life groups management system

-- ============================================
-- Group Types
-- ============================================
CREATE TABLE IF NOT EXISTS group_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) DEFAULT '#6B7280',
    default_visibility ENUM('public', 'private', 'unlisted') DEFAULT 'public',
    allow_signups BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Groups
-- ============================================
CREATE TABLE IF NOT EXISTS `groups` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_type_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    description TEXT NULL,

    -- Schedule
    meeting_day ENUM('sunday','monday','tuesday','wednesday','thursday','friday','saturday') NULL,
    meeting_time TIME NULL,
    meeting_frequency ENUM('weekly','bi-weekly','monthly','custom') DEFAULT 'weekly',
    meeting_frequency_note VARCHAR(255) NULL,

    -- Location
    location_type ENUM('physical','online','hybrid') DEFAULT 'physical',
    location_name VARCHAR(200) NULL,
    location_address TEXT NULL,
    location_city VARCHAR(100) NULL,
    location_postcode VARCHAR(20) NULL,
    online_url VARCHAR(500) NULL,

    -- Visibility & Signup
    visibility ENUM('public','private','unlisted') DEFAULT 'public',
    allow_signups BOOLEAN DEFAULT TRUE,
    requires_approval BOOLEAN DEFAULT FALSE,

    -- Capacity
    max_members INT NULL,

    -- Contact
    contact_email VARCHAR(255) NULL,
    contact_phone VARCHAR(30) NULL,

    -- Features
    childcare_available BOOLEAN DEFAULT FALSE,

    -- Media
    image_url VARCHAR(500) NULL,

    -- Status
    status ENUM('active','inactive','archived') DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,

    UNIQUE INDEX idx_slug (slug),
    INDEX idx_type (group_type_id),
    INDEX idx_status (status),
    INDEX idx_day (meeting_day),
    FULLTEXT INDEX ft_search (name, description),

    FOREIGN KEY (group_type_id) REFERENCES group_types(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Group Members
-- ============================================
CREATE TABLE IF NOT EXISTS group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('member','leader','co-leader','admin') DEFAULT 'member',
    status ENUM('active','inactive','pending') DEFAULT 'active',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_group_user (group_id, user_id),
    INDEX idx_user (user_id),
    INDEX idx_role (role),

    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Group Events/Meetings
-- ============================================
CREATE TABLE IF NOT EXISTS group_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    event_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    location_name VARCHAR(200) NULL,
    location_address TEXT NULL,
    is_cancelled BOOLEAN DEFAULT FALSE,
    cancelled_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,

    INDEX idx_group_date (group_id, event_date),

    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Group Attendance
-- ============================================
CREATE TABLE IF NOT EXISTS group_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_event_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('present','absent','excused') DEFAULT 'present',
    checked_in_at TIMESTAMP NULL,
    notes VARCHAR(255) NULL,

    UNIQUE INDEX idx_event_user (group_event_id, user_id),

    FOREIGN KEY (group_event_id) REFERENCES group_events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Group Signup Requests
-- ============================================
CREATE TABLE IF NOT EXISTS group_signup_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NULL,
    status ENUM('pending','approved','denied') DEFAULT 'pending',
    response_notes TEXT NULL,
    responded_by INT NULL,
    responded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_group_status (group_id, status),
    INDEX idx_user (user_id),

    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Default Group Types
-- ============================================
INSERT INTO group_types (name, slug, description, color, sort_order) VALUES
('Life Groups', 'life-groups', 'Weekly small groups for community and discipleship', '#10B981', 1),
('Bible Studies', 'bible-studies', 'In-depth Bible study groups', '#3B82F6', 2),
('Ministry Teams', 'ministry-teams', 'Serving teams and ministry groups', '#8B5CF6', 3),
('Interest Groups', 'interest-groups', 'Groups based on shared interests', '#F59E0B', 4),
('Support Groups', 'support-groups', 'Recovery and support groups', '#EC4899', 5),
('Youth Groups', 'youth-groups', 'Groups for young people', '#06B6D4', 6);

-- ============================================
-- Verification
-- ============================================
SELECT 'Migration complete: Groups module tables created' AS status;
SELECT COUNT(*) AS group_types_created FROM group_types;
