-- Addresses Migration
-- Created: 2026-03-23
-- Purpose: Create addresses table for user/household addresses
--
-- Run this migration on production:
--   mysql -u [username] -p [database_name] < migrations/2026_03_23_004_addresses.sql

-- ============================================
-- Addresses Table
-- ============================================

CREATE TABLE IF NOT EXISTS addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'Individual user address',
    household_id INT NULL COMMENT 'Shared household address',

    -- Address fields
    street VARCHAR(255) NULL,
    street2 VARCHAR(255) NULL COMMENT 'Flat/unit number, building name, etc.',
    city VARCHAR(100) NULL,
    county VARCHAR(100) NULL,
    postcode VARCHAR(20) NULL,
    country VARCHAR(100) DEFAULT 'United Kingdom',

    -- Classification
    location_type ENUM('home', 'work', 'mailing', 'other') DEFAULT 'home',
    is_primary BOOLEAN DEFAULT FALSE,

    -- Geolocation (for group finder, etc.)
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    INDEX idx_household (household_id),
    INDEX idx_postcode (postcode),
    INDEX idx_primary (is_primary),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE,

    -- Ensure address belongs to either user OR household, not both
    CONSTRAINT chk_address_owner CHECK (
        (user_id IS NOT NULL AND household_id IS NULL) OR
        (user_id IS NULL AND household_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Verification
-- ============================================
SELECT 'Migration complete: addresses table created' AS status;
