-- User Notes Migration
-- Created: 2026-03-23
-- Purpose: Create user notes table for pastoral notes, prayer requests, follow-up tracking
--
-- Run this migration on production:
--   mysql -u [username] -p [database_name] < migrations/2026_03_23_007_user_notes.sql

-- ============================================
-- User Notes Table
-- ============================================

CREATE TABLE IF NOT EXISTS user_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'The person this note is about',

    -- Note content
    note TEXT NOT NULL,
    note_type ENUM('general', 'prayer', 'pastoral', 'follow_up', 'private') DEFAULT 'general',

    -- Display settings
    is_pinned BOOLEAN DEFAULT FALSE COMMENT 'Pin important notes to top',

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NOT NULL COMMENT 'Staff member who created the note',

    INDEX idx_user (user_id),
    INDEX idx_type (note_type),
    INDEX idx_pinned (is_pinned),
    INDEX idx_created (created_at),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Verification
-- ============================================
SELECT 'Migration complete: user_notes table created' AS status;
