-- Custom Worship Flows for AI Setlist Generator
-- Allows users to create and save their own energy curve patterns

CREATE TABLE IF NOT EXISTS custom_worship_flows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    pattern JSON NOT NULL COMMENT 'Array of energy levels: high, medium, low',
    is_default TINYINT(1) DEFAULT 0 COMMENT 'Whether this is a system default',
    user_id INT NULL COMMENT 'NULL for global/shared flows',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default flows
INSERT INTO custom_worship_flows (name, description, pattern, is_default, sort_order) VALUES
('Standard', 'High energy start, intimate moment, medium end', '["high", "high", "medium", "low", "medium"]', 1, 1),
('Building', 'Builds to climax in the middle', '["medium", "medium", "high", "high", "medium"]', 1, 2),
('Intimate', 'Reflective and quiet throughout', '["medium", "low", "low", "low", "medium"]', 1, 3),
('Celebration', 'High energy throughout', '["high", "high", "high", "medium", "high"]', 1, 4);
