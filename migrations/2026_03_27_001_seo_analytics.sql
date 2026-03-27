-- SEO Analytics Migration
-- Adds tables for 404 tracking, Google Search Console data, and referrer domain analysis

DELIMITER //

-- Helper procedure to add column if not exists
DROP PROCEDURE IF EXISTS add_column_if_not_exists//
CREATE PROCEDURE add_column_if_not_exists(
    IN table_name VARCHAR(64),
    IN column_name VARCHAR(64),
    IN column_definition VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = table_name
        AND COLUMN_NAME = column_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE ', table_name, ' ADD COLUMN ', column_name, ' ', column_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

-- Helper procedure to add index if not exists
DROP PROCEDURE IF EXISTS add_index_if_not_exists//
CREATE PROCEDURE add_index_if_not_exists(
    IN table_name VARCHAR(64),
    IN index_name VARCHAR(64),
    IN index_columns VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = table_name
        AND INDEX_NAME = index_name
    ) THEN
        SET @sql = CONCAT('CREATE INDEX ', index_name, ' ON ', table_name, '(', index_columns, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

DELIMITER ;

-- =====================================================
-- 1. Add referrer_domain column to page_visits
-- =====================================================

CALL add_column_if_not_exists('page_visits', 'referrer_domain', 'VARCHAR(255) NULL');
CALL add_index_if_not_exists('page_visits', 'idx_page_visits_referrer_domain', 'referrer_domain');

-- Backfill existing referrer_domain data
UPDATE page_visits
SET referrer_domain = SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(referrer, 'https://', ''), 'http://', ''), '/', 1), '?', 1)
WHERE referrer IS NOT NULL AND referrer != '' AND referrer_domain IS NULL
LIMIT 50000;

-- =====================================================
-- 2. 404 tracking - aggregated log
-- =====================================================

CREATE TABLE IF NOT EXISTS seo_404_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_url VARCHAR(500) NOT NULL,
    referrer VARCHAR(500) NULL,
    is_bot TINYINT(1) DEFAULT 0,
    bot_name VARCHAR(100) NULL,
    hit_count INT DEFAULT 1,
    first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved TINYINT(1) DEFAULT 0,
    redirect_to VARCHAR(500) NULL,
    INDEX idx_404_url (request_url(191)),
    INDEX idx_404_last_seen (last_seen_at),
    INDEX idx_404_resolved (resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. 404 tracking - individual hits for trend analysis
-- =====================================================

CREATE TABLE IF NOT EXISTS seo_404_hits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_url VARCHAR(500) NOT NULL,
    referrer VARCHAR(500) NULL,
    is_bot TINYINT(1) DEFAULT 0,
    bot_name VARCHAR(100) NULL,
    hit_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_404_hits_url (request_url(191)),
    INDEX idx_404_hits_date (hit_at),
    INDEX idx_404_hits_bot (is_bot, hit_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. Google Search Console cached data
-- =====================================================

CREATE TABLE IF NOT EXISTS seo_gsc_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_date DATE NOT NULL,
    page_url VARCHAR(500) NOT NULL,
    query VARCHAR(500) NOT NULL,
    clicks INT DEFAULT 0,
    impressions INT DEFAULT 0,
    ctr DECIMAL(5,4) DEFAULT 0,
    position DECIMAL(5,2) DEFAULT 0,
    device VARCHAR(20) NULL,
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_gsc_unique (data_date, page_url(100), query(100), device),
    INDEX idx_gsc_date (data_date),
    INDEX idx_gsc_page (page_url(191)),
    INDEX idx_gsc_query (query(191)),
    INDEX idx_gsc_clicks (clicks)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. Google Search Console configuration
-- =====================================================

CREATE TABLE IF NOT EXISTS seo_gsc_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Cleanup helper procedures
-- =====================================================

DROP PROCEDURE IF EXISTS add_column_if_not_exists;
DROP PROCEDURE IF EXISTS add_index_if_not_exists;
