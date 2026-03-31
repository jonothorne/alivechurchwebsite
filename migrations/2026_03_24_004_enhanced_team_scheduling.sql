-- ============================================
-- Enhanced Team Scheduling Migration
-- Created: 2026-03-24
-- Purpose: Add position/role management, availability tracking, and confirmation workflow
-- ============================================

-- ============================================
-- 1. Schema notes
-- ============================================
-- service_team_members and service_assignments already use user_id
-- We'll work with user_id directly instead of adding member_id
-- This keeps backward compatibility

-- ============================================
-- 2. Service Roles (Positions within teams)
-- ============================================
CREATE TABLE IF NOT EXISTS service_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    name VARCHAR(100) NOT NULL COMMENT 'e.g., Worship Leader, Drums, Bass, Keys, Vocals 1-3, Sound, Projection',
    description TEXT NULL,
    sort_order INT DEFAULT 0,
    min_skill_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_team (team_id),
    INDEX idx_active (is_active),

    FOREIGN KEY (team_id) REFERENCES service_teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. Member Role Capabilities (What roles each member can perform)
-- ============================================
CREATE TABLE IF NOT EXISTS member_role_capabilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    role_id INT NOT NULL,
    skill_level ENUM('beginner', 'competent', 'proficient', 'expert') DEFAULT 'competent',
    preference_level ENUM('unwilling', 'willing', 'prefer', 'strong_prefer') DEFAULT 'willing',
    notes TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_member_role (member_id, role_id),
    INDEX idx_role (role_id),

    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES service_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. Service Rota (Specific role assignments for a service)
-- ============================================
CREATE TABLE IF NOT EXISTS service_rota (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    role_id INT NOT NULL,
    member_id INT NULL COMMENT 'Null if unassigned',
    status ENUM('unassigned', 'pending', 'confirmed', 'declined') DEFAULT 'unassigned',
    assigned_at TIMESTAMP NULL,
    responded_at TIMESTAMP NULL,
    confirmation_token VARCHAR(64) NULL COMMENT 'For email confirmation links',
    decline_reason TEXT NULL,
    notes TEXT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_service (service_id),
    INDEX idx_member (member_id),
    INDEX idx_role (role_id),
    INDEX idx_status (status),
    INDEX idx_token (confirmation_token),

    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES service_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. Member Availability (Blackout dates)
-- ============================================
CREATE TABLE IF NOT EXISTS member_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    unavailable_date DATE NOT NULL COMMENT 'Specific date they cannot serve',
    reason VARCHAR(255) NULL,
    is_recurring BOOLEAN DEFAULT FALSE COMMENT 'If true, recurs annually',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_member_date (member_id, unavailable_date),
    INDEX idx_date (unavailable_date),

    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. Assignment Notification Log
-- ============================================
CREATE TABLE IF NOT EXISTS service_assignment_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rota_id INT NOT NULL,
    notification_type ENUM('assignment', 'reminder', 'change', 'cancellation') DEFAULT 'assignment',
    sent_to_email VARCHAR(255) NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    opened_at TIMESTAMP NULL,
    responded_at TIMESTAMP NULL,

    INDEX idx_rota (rota_id),
    INDEX idx_sent_at (sent_at),

    FOREIGN KEY (rota_id) REFERENCES service_rota(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. Scheduling Conflicts Log
-- ============================================
CREATE TABLE IF NOT EXISTS service_scheduling_conflicts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    member_id INT NOT NULL,
    conflict_type ENUM('double_booked', 'unavailable', 'over_scheduled', 'insufficient_skill') DEFAULT 'double_booked',
    conflict_details TEXT NULL,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_service (service_id),
    INDEX idx_member (member_id),
    INDEX idx_resolved (resolved),

    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. Update existing services table
-- ============================================
-- Note: description and location columns already exist in services table
-- Skipping ALTER TABLE for these columns

-- ============================================
-- 9. Insert default roles for existing teams
-- ============================================

-- Worship Team roles
INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Worship Leader', 1 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Vocals 1', 2 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Vocals 2', 3 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Vocals 3', 4 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Keys', 5 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Electric Guitar', 6 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Acoustic Guitar', 7 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Bass', 8 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Drums', 9 FROM service_teams WHERE slug = 'worship'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

-- Tech/AV Team roles
INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Sound Engineer', 1 FROM service_teams WHERE slug = 'tech'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Projection Operator', 2 FROM service_teams WHERE slug = 'tech'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Lighting', 3 FROM service_teams WHERE slug = 'tech'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Camera Operator', 4 FROM service_teams WHERE slug = 'tech'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Live Stream Director', 5 FROM service_teams WHERE slug = 'tech'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

-- Welcome Team roles
INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Greeter - Front Door', 1 FROM service_teams WHERE slug = 'welcome'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Greeter - Auditorium', 2 FROM service_teams WHERE slug = 'welcome'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Usher', 3 FROM service_teams WHERE slug = 'welcome'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Info Desk', 4 FROM service_teams WHERE slug = 'welcome'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

-- Kids Ministry roles
INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Teacher', 1 FROM service_teams WHERE slug = 'kids'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Helper', 2 FROM service_teams WHERE slug = 'kids'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Check-in', 3 FROM service_teams WHERE slug = 'kids'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

-- Prayer Team roles
INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Prayer Minister', 1 FROM service_teams WHERE slug = 'prayer'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

INSERT INTO service_roles (team_id, name, sort_order)
SELECT id, 'Prayer Room Host', 2 FROM service_teams WHERE slug = 'prayer'
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

-- ============================================
-- 10. Create indexes for performance
-- ============================================
ALTER TABLE services
ADD INDEX idx_date_status (service_date, status);

ALTER TABLE service_rota
ADD INDEX idx_service_status (service_id, status);

-- ============================================
-- Migration Complete
-- ============================================
SELECT 'Enhanced Team Scheduling Migration Complete!' AS status,
       (SELECT COUNT(*) FROM service_roles) AS roles_created,
       (SELECT COUNT(DISTINCT team_id) FROM service_roles) AS teams_with_roles;
