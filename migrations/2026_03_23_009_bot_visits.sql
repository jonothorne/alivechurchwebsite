-- Bot Visits Tracking Table
-- Stores bot/crawler visits separately from human analytics

CREATE TABLE IF NOT EXISTS bot_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_name VARCHAR(100) NOT NULL,
    bot_category VARCHAR(50) NOT NULL COMMENT 'Search Engine, Social Media, SEO Tool, etc.',
    bot_owner VARCHAR(100) DEFAULT NULL COMMENT 'Google, Microsoft, etc.',
    classification ENUM('good', 'suspicious', 'unknown') NOT NULL DEFAULT 'unknown',
    user_agent VARCHAR(500) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    request_url VARCHAR(500) NOT NULL,
    pattern_matched VARCHAR(50) DEFAULT NULL COMMENT 'The pattern that identified this as a bot',
    visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_bot_name (bot_name),
    INDEX idx_classification (classification),
    INDEX idx_bot_category (bot_category),
    INDEX idx_visited_at (visited_at),
    INDEX idx_ip_address (ip_address),
    INDEX idx_request_url (request_url(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comment to table
ALTER TABLE bot_visits COMMENT = 'Tracks bot and crawler visits separately from human analytics';
