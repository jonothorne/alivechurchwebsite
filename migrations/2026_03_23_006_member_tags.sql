-- Member Tags Migration
-- Created: 2026-03-23
-- Purpose: Create member tagging system for categorization and segmentation
--
-- Run this migration on production:
--   mysql -u [username] -p [database_name] < migrations/2026_03_23_006_member_tags.sql

-- ============================================
-- Member Tags Table (Tag Definitions)
-- ============================================

CREATE TABLE IF NOT EXISTS member_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    tag_group VARCHAR(100) NULL COMMENT 'Group tags together (e.g., "Interests", "Skills", "Ministry")',
    color VARCHAR(7) DEFAULT '#6B7280' COMMENT 'Hex color for display',
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_slug (slug),
    INDEX idx_group (tag_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- User Tags Junction Table
-- ============================================

CREATE TABLE IF NOT EXISTS user_tags (
    user_id INT NOT NULL,
    tag_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_by INT NULL COMMENT 'User who added this tag',

    PRIMARY KEY (user_id, tag_id),
    INDEX idx_tag (tag_id),
    INDEX idx_added_by (added_by),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES member_tags(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Insert Default Tags
-- ============================================

INSERT INTO member_tags (name, slug, tag_group, color, description) VALUES
-- Involvement tags
('New Believer', 'new-believer', 'Spiritual Journey', '#10B981', 'Recently accepted Christ'),
('Seeking Baptism', 'seeking-baptism', 'Spiritual Journey', '#3B82F6', 'Interested in being baptized'),
('Small Group Leader', 'small-group-leader', 'Leadership', '#8B5CF6', 'Leads a small group'),
('Ministry Leader', 'ministry-leader', 'Leadership', '#EC4899', 'Leads a ministry area'),

-- Volunteer tags
('Worship Team', 'worship-team', 'Serving', '#F59E0B', 'Serves on worship team'),
('Tech Team', 'tech-team', 'Serving', '#6366F1', 'Serves on tech/AV team'),
('Kids Ministry', 'kids-ministry', 'Serving', '#14B8A6', 'Serves in children''s ministry'),
('Welcome Team', 'welcome-team', 'Serving', '#F97316', 'Serves as greeter/usher'),
('Prayer Team', 'prayer-team', 'Serving', '#A855F7', 'Serves on prayer team'),

-- Life stage tags
('Young Adult', 'young-adult', 'Life Stage', '#0EA5E9', '18-30 years old'),
('Parent', 'parent', 'Life Stage', '#22C55E', 'Has children'),
('Senior', 'senior', 'Life Stage', '#64748B', '65+ years old'),

-- Special tags
('First-Time Guest', 'first-time-guest', 'Status', '#EAB308', 'First-time visitor'),
('Requires Follow-Up', 'requires-follow-up', 'Status', '#EF4444', 'Needs pastoral follow-up'),
('VIP', 'vip', 'Status', '#D946EF', 'Special attention needed');

-- ============================================
-- Verification
-- ============================================
SELECT 'Migration complete: member_tags and user_tags tables created' AS status;
SELECT COUNT(*) AS default_tags_created FROM member_tags;
