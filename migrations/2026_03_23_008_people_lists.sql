-- People Lists Migration
-- Created: 2026-03-23
-- Purpose: Create lists/segments feature for organizing and filtering people
--
-- Run this migration on production:
--   mysql -u [username] -p [database_name] < migrations/2026_03_23_008_people_lists.sql

-- ============================================
-- People Lists Table (List Definitions)
-- ============================================

CREATE TABLE IF NOT EXISTS people_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    description TEXT NULL,
    list_type ENUM('static', 'dynamic') NOT NULL DEFAULT 'static' COMMENT 'Static = manual, Dynamic = criteria-based',

    -- For dynamic lists: JSON criteria
    criteria JSON NULL COMMENT 'Filter criteria for dynamic lists',

    -- Display settings
    color VARCHAR(7) DEFAULT '#6B7280',
    icon VARCHAR(50) NULL COMMENT 'Icon identifier',

    -- Ownership
    is_system BOOLEAN DEFAULT FALSE COMMENT 'System lists cannot be deleted',
    visibility ENUM('private', 'shared', 'public') DEFAULT 'shared',
    created_by INT NULL,

    -- Counts (cached for performance)
    member_count INT DEFAULT 0,
    last_refreshed_at TIMESTAMP NULL COMMENT 'When dynamic list was last recalculated',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_slug (slug),
    INDEX idx_type (list_type),
    INDEX idx_created_by (created_by),

    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- People List Members (for static lists)
-- ============================================

CREATE TABLE IF NOT EXISTS people_list_members (
    list_id INT NOT NULL,
    user_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_by INT NULL,
    notes VARCHAR(255) NULL COMMENT 'Optional note about why person is on this list',

    PRIMARY KEY (list_id, user_id),
    INDEX idx_user (user_id),
    INDEX idx_added_by (added_by),

    FOREIGN KEY (list_id) REFERENCES people_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Insert Default System Lists
-- ============================================

INSERT INTO people_lists (name, slug, description, list_type, criteria, color, is_system) VALUES
-- Dynamic system lists
('All Members', 'all-members', 'All people marked as church members', 'dynamic', '{"is_member": true}', '#10B981', TRUE),
('All Visitors', 'all-visitors', 'All people not marked as members', 'dynamic', '{"is_member": false}', '#3B82F6', TRUE),
('New This Month', 'new-this-month', 'People added in the current month', 'dynamic', '{"created_within": "month"}', '#F59E0B', TRUE),
('New This Week', 'new-this-week', 'People added in the current week', 'dynamic', '{"created_within": "week"}', '#8B5CF6', TRUE),
('Recently Active', 'recently-active', 'People who logged in within 30 days', 'dynamic', '{"last_login_within": "30_days"}', '#14B8A6', TRUE),
('Inactive', 'inactive', 'People who haven''t logged in for 90+ days', 'dynamic', '{"last_login_before": "90_days"}', '#EF4444', TRUE),
('Birthdays This Month', 'birthdays-this-month', 'People with birthdays in the current month', 'dynamic', '{"birthday_month": "current"}', '#EC4899', TRUE),
('Anniversaries This Month', 'anniversaries-this-month', 'Wedding anniversaries in the current month', 'dynamic', '{"anniversary_month": "current"}', '#D946EF', TRUE);

-- ============================================
-- Update member counts for system lists
-- ============================================

-- This would normally be done by the application, but we'll set initial counts
UPDATE people_lists SET member_count = (
    SELECT COUNT(*) FROM users WHERE is_member = 1 AND active = 1
) WHERE slug = 'all-members';

UPDATE people_lists SET member_count = (
    SELECT COUNT(*) FROM users WHERE (is_member = 0 OR is_member IS NULL) AND active = 1
) WHERE slug = 'all-visitors';

UPDATE people_lists SET member_count = (
    SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND active = 1
) WHERE slug = 'new-this-month';

-- ============================================
-- Verification
-- ============================================
SELECT 'Migration complete: people_lists and people_list_members tables created' AS status;
SELECT COUNT(*) AS system_lists_created FROM people_lists WHERE is_system = TRUE;
