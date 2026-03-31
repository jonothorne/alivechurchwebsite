-- Music Stand Annotations
-- Stores personal annotations/drawings/edits for chord charts

CREATE TABLE IF NOT EXISTS musicstand_annotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    -- Can be linked to service item OR song (for library view)
    service_item_id INT NULL,
    song_id INT NULL,
    -- Annotation data
    drawing_data JSON NULL COMMENT 'Canvas drawing paths as JSON',
    text_notes TEXT NULL COMMENT 'Text annotations/notes',
    chart_edits TEXT NULL COMMENT 'Personal edits to chord chart',
    -- Settings per instance
    chord_size INT DEFAULT 14,
    lyric_size INT DEFAULT 16,
    transpose_key VARCHAR(10) NULL,
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Foreign keys
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_item_id) REFERENCES service_items(id) ON DELETE CASCADE,
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
    -- Indexes
    INDEX idx_user_item (user_id, service_item_id),
    INDEX idx_user_song (user_id, song_id),
    -- Ensure one annotation per user per item/song
    UNIQUE KEY unique_user_item (user_id, service_item_id),
    UNIQUE KEY unique_user_song (user_id, song_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
