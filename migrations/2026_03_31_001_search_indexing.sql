-- Search Indexing API Migration
-- Adds config and log tables for IndexNow and Google Indexing API

CREATE TABLE IF NOT EXISTS seo_indexing_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_indexing_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(500) NOT NULL,
    service ENUM('indexnow','google') NOT NULL,
    action ENUM('updated','deleted') DEFAULT 'updated',
    status ENUM('pending','success','error') DEFAULT 'pending',
    http_code SMALLINT NULL,
    response TEXT NULL,
    submitted_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_indexing_log_url (url(191)),
    INDEX idx_indexing_log_service (service, created_at),
    INDEX idx_indexing_log_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Track which pages have been bulk-submitted
CREATE TABLE IF NOT EXISTS seo_indexing_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(500) NOT NULL,
    service ENUM('indexnow','google') NOT NULL,
    submitted_at TIMESTAMP NULL,
    status ENUM('pending','submitted','error') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_queue_unique (url(191), service),
    INDEX idx_queue_status (status, service)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
