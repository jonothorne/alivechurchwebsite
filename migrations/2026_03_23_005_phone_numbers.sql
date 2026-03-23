-- Phone Numbers Migration
-- Created: 2026-03-23
-- Purpose: Create phone_numbers table for multiple phone numbers per user
--
-- Run this migration on production:
--   mysql -u [username] -p [database_name] < migrations/2026_03_23_005_phone_numbers.sql

-- ============================================
-- Phone Numbers Table
-- ============================================

CREATE TABLE IF NOT EXISTS phone_numbers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,

    -- Phone details
    number VARCHAR(30) NOT NULL,
    country_code VARCHAR(5) DEFAULT '+44' COMMENT 'UK default',
    location_type ENUM('home', 'work', 'mobile', 'other') DEFAULT 'mobile',

    -- Settings
    is_primary BOOLEAN DEFAULT FALSE,
    can_receive_sms BOOLEAN DEFAULT TRUE COMMENT 'For text message communications',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    INDEX idx_primary (is_primary),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Verification
-- ============================================
SELECT 'Migration complete: phone_numbers table created' AS status;
