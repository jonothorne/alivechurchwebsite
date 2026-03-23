-- Membership Statuses Migration
-- Created: 2026-03-23
-- Purpose: Create membership status lookup table (must run before user fields migration)
--
-- Run this migration on production:
--   mysql -u [username] -p [database_name] < migrations/2026_03_23_001_membership_statuses.sql

-- ============================================
-- Membership Statuses Table
-- ============================================

CREATE TABLE IF NOT EXISTS membership_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#6B7280',
    is_member BOOLEAN DEFAULT FALSE COMMENT 'TRUE if this status counts as a church member',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_is_member (is_member),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default membership statuses
INSERT INTO membership_statuses (name, description, color, is_member, sort_order) VALUES
('Visitor', 'First-time or occasional visitor', '#9CA3AF', FALSE, 1),
('Regular Attender', 'Attends regularly but not yet a member', '#3B82F6', FALSE, 2),
('Member', 'Official church member', '#10B981', TRUE, 3),
('Leader', 'Church leader or ministry head', '#8B5CF6', TRUE, 4),
('Staff', 'Church staff member', '#EC4899', TRUE, 5),
('Inactive', 'Previously active, no longer attending', '#EF4444', FALSE, 6);

-- ============================================
-- Verification
-- ============================================
SELECT 'Migration complete: membership_statuses table created with default statuses' AS status;
SELECT COUNT(*) AS statuses_created FROM membership_statuses;
