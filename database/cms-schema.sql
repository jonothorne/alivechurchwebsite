-- Alive Church Inline CMS Database Schema
-- Clean, custom CMS with inline editing support

-- Drop old CMS tables that we're replacing
DROP TABLE IF EXISTS page_sections;
DROP TABLE IF EXISTS grapes_assets;

-- Content blocks - stores all editable content
CREATE TABLE IF NOT EXISTS content_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_slug VARCHAR(100) NOT NULL,
    block_key VARCHAR(100) NOT NULL,
    content_type ENUM('text', 'html', 'image', 'link', 'json') DEFAULT 'html',
    content LONGTEXT,
    metadata JSON,
    version INT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    UNIQUE KEY unique_block (page_slug, block_key),
    INDEX idx_page (page_slug),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Content revisions for undo/history
CREATE TABLE IF NOT EXISTS content_revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    block_id INT NOT NULL,
    content LONGTEXT,
    version INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    INDEX idx_block (block_id),
    INDEX idx_version (block_id, version),
    FOREIGN KEY (block_id) REFERENCES content_blocks(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CMS Pages with template support
-- Check and add columns to pages table if they don't exist
-- Using separate statements for compatibility

-- Add layout column if not exists
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND COLUMN_NAME = 'layout');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE pages ADD COLUMN layout VARCHAR(50) DEFAULT ''default'' AFTER template', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add hero_style column if not exists
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND COLUMN_NAME = 'hero_style');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE pages ADD COLUMN hero_style VARCHAR(50) DEFAULT ''standard'' AFTER layout', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add show_in_nav column if not exists
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND COLUMN_NAME = 'show_in_nav');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE pages ADD COLUMN show_in_nav BOOLEAN DEFAULT FALSE AFTER published', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add nav_order column if not exists
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND COLUMN_NAME = 'nav_order');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE pages ADD COLUMN nav_order INT DEFAULT 0 AFTER show_in_nav', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add parent_id column if not exists
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND COLUMN_NAME = 'parent_id');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE pages ADD COLUMN parent_id INT NULL AFTER nav_order', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Global content blocks (footer, header elements, etc.)
CREATE TABLE IF NOT EXISTS global_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    block_key VARCHAR(100) UNIQUE NOT NULL,
    content_type ENUM('text', 'html', 'image', 'link', 'json') DEFAULT 'html',
    content LONGTEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    INDEX idx_key (block_key),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reusable content components (testimonials, team members, etc.)
CREATE TABLE IF NOT EXISTS content_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    component_type VARCHAR(50) NOT NULL,
    title VARCHAR(200),
    content JSON NOT NULL,
    display_order INT DEFAULT 0,
    visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (component_type),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default global content
INSERT INTO global_content (block_key, content_type, content, description) VALUES
('site_name', 'text', 'Alive Church', 'Site name displayed in header/footer'),
('site_tagline', 'text', 'You Belong Here', 'Site tagline'),
('footer_newsletter_heading', 'text', 'Stay in the loop', 'Newsletter section heading'),
('footer_newsletter_text', 'text', 'Get weekly updates, event invites, and encouragement delivered to your inbox.', 'Newsletter description'),
('footer_copyright', 'text', 'All rights reserved.', 'Copyright text')
ON DUPLICATE KEY UPDATE block_key = block_key;
