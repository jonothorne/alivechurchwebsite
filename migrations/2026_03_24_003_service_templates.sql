-- ============================================
-- Service Templates Migration
-- Allows saving services as templates for quick reuse
-- Created: 2026-03-24
-- ============================================

-- Service Templates Table
-- Stores template configurations for services
CREATE TABLE IF NOT EXISTS service_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    service_type_id INT NOT NULL,
    default_duration_minutes INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_service_type (service_type_id),
    INDEX idx_active (is_active),

    FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Service Template Items Table
-- Stores the default order of service items for a template
CREATE TABLE IF NOT EXISTS service_template_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    item_type ENUM('song', 'scripture', 'prayer', 'announcement', 'sermon', 'offering', 'communion', 'video', 'other') DEFAULT 'other',
    song_id INT NULL,
    title VARCHAR(200) NULL,
    duration_minutes INT NULL,
    notes TEXT NULL,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_template (template_id),
    INDEX idx_song (song_id),

    FOREIGN KEY (template_id) REFERENCES service_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Service Template Roles Table
-- Stores the default team roles needed for a template
CREATE TABLE IF NOT EXISTS service_template_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    role_id INT NOT NULL,
    quantity INT DEFAULT 1 COMMENT 'How many people needed for this role',
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_template (template_id),
    INDEX idx_role (role_id),

    FOREIGN KEY (template_id) REFERENCES service_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES service_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create missing tables if they don't exist yet
-- (service_roles and service_rota are referenced in code but might not be in migrations)
CREATE TABLE IF NOT EXISTS service_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_team (team_id),

    FOREIGN KEY (team_id) REFERENCES service_teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_rota (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    role_id INT NOT NULL,
    member_id INT NULL,
    status ENUM('unassigned', 'pending', 'confirmed', 'declined') DEFAULT 'unassigned',
    sort_order INT DEFAULT 0,
    notes TEXT NULL,
    assigned_at TIMESTAMP NULL,
    responded_at TIMESTAMP NULL,
    decline_reason VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_service (service_id),
    INDEX idx_role (role_id),
    INDEX idx_member (member_id),
    INDEX idx_status (status),

    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES service_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create member_role_capabilities if it doesn't exist
CREATE TABLE IF NOT EXISTS member_role_capabilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    role_id INT NOT NULL,
    skill_level ENUM('learning', 'competent', 'proficient', 'expert') DEFAULT 'competent',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_member_role (member_id, role_id),
    INDEX idx_role (role_id),

    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES service_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create member_availability if it doesn't exist
CREATE TABLE IF NOT EXISTS member_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    unavailable_date DATE NOT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_member_date (member_id, unavailable_date),

    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Migration complete: Service templates tables created' AS status;
