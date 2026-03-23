-- Analytics Enhancement Migration
-- Run this on production to add new analytics features
-- Safe to run - uses procedures to check existence before adding

DELIMITER //

-- =====================================================
-- Helper procedure to add column if not exists
-- =====================================================
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

-- =====================================================
-- Helper procedure to add index if not exists
-- =====================================================
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
-- 1. Add new columns to page_visits table
-- =====================================================

CALL add_column_if_not_exists('page_visits', 'country_code', 'VARCHAR(2) NULL');
CALL add_column_if_not_exists('page_visits', 'country_name', 'VARCHAR(100) NULL');
CALL add_column_if_not_exists('page_visits', 'city', 'VARCHAR(100) NULL');
CALL add_column_if_not_exists('page_visits', 'region', 'VARCHAR(100) NULL');
CALL add_column_if_not_exists('page_visits', 'latitude', 'DECIMAL(10,8) NULL');
CALL add_column_if_not_exists('page_visits', 'longitude', 'DECIMAL(11,8) NULL');
CALL add_column_if_not_exists('page_visits', 'is_new_visitor', 'TINYINT(1) DEFAULT 0');
CALL add_column_if_not_exists('page_visits', 'page_load_time', 'INT NULL');
CALL add_column_if_not_exists('page_visits', 'screen_width', 'INT NULL');
CALL add_column_if_not_exists('page_visits', 'screen_height', 'INT NULL');

CALL add_index_if_not_exists('page_visits', 'idx_page_visits_country', 'country_code');
CALL add_index_if_not_exists('page_visits', 'idx_page_visits_city', 'city');

-- =====================================================
-- 2. Create analytics_sessions table
-- =====================================================

CREATE TABLE IF NOT EXISTS analytics_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL UNIQUE,
    user_id INT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    page_count INT DEFAULT 1,
    total_duration INT DEFAULT 0,
    entry_page VARCHAR(500) NULL,
    exit_page VARCHAR(500) NULL,
    is_bounce TINYINT(1) DEFAULT 1,
    device_type ENUM('desktop', 'tablet', 'mobile') DEFAULT 'desktop',
    browser VARCHAR(50) NULL,
    country_code VARCHAR(2) NULL,
    country_name VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    referrer_source VARCHAR(100) NULL,
    referrer_url VARCHAR(500) NULL,
    INDEX idx_sessions_started (started_at),
    INDEX idx_sessions_device (device_type),
    INDEX idx_sessions_country (country_code)
);

-- =====================================================
-- 3. Create analytics_searches table
-- =====================================================

CREATE TABLE IF NOT EXISTS analytics_searches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    search_term VARCHAR(255) NOT NULL,
    results_count INT DEFAULT 0,
    search_type VARCHAR(50) DEFAULT 'site',
    user_id INT NULL,
    session_id VARCHAR(64) NULL,
    searched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_searches_term (search_term),
    INDEX idx_searches_date (searched_at),
    INDEX idx_searches_type (search_type)
);

-- =====================================================
-- 4. Create analytics_hourly_traffic table
-- =====================================================

CREATE TABLE IF NOT EXISTS analytics_hourly_traffic (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    hour TINYINT NOT NULL,
    day_of_week TINYINT NOT NULL,
    page_views INT DEFAULT 0,
    unique_visitors INT DEFAULT 0,
    UNIQUE KEY idx_hourly_unique (date, hour),
    INDEX idx_hourly_dow (day_of_week, hour)
);

-- =====================================================
-- Cleanup helper procedures
-- =====================================================

DROP PROCEDURE IF EXISTS add_column_if_not_exists;
DROP PROCEDURE IF EXISTS add_index_if_not_exists;

-- =====================================================
-- Migration complete
-- =====================================================
