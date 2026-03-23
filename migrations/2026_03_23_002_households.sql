-- Households Migration
-- Created: 2026-03-23
-- Purpose: Create households table for family groupings (must run before user fields migration)
--
-- Run this migration on production:
--   mysql -u [username] -p [database_name] < migrations/2026_03_23_002_households.sql

-- ============================================
-- Households Table
-- ============================================

CREATE TABLE IF NOT EXISTS households (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL COMMENT 'Usually "The [LastName] Family" or custom name',
    primary_contact_id INT NULL COMMENT 'Main contact person for household communications',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_primary_contact (primary_contact_id)
    -- Note: Foreign key to users will be added after users table is updated
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Verification
-- ============================================
SELECT 'Migration complete: households table created' AS status;
