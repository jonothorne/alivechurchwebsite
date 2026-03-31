-- Run Sheet / Service Flow Enhancement Migration
-- Created: 2026-03-24
-- Purpose: Add comprehensive run sheet features for service planning

-- ============================================
-- Enhance service_items table for run sheet
-- ============================================

-- Add timing fields
ALTER TABLE service_items
ADD COLUMN planned_duration INT NULL COMMENT 'Planned duration in minutes' AFTER duration_minutes,
ADD COLUMN actual_start_time DATETIME NULL COMMENT 'Actual start time when running live' AFTER planned_duration,
ADD COLUMN actual_end_time DATETIME NULL COMMENT 'Actual end time when running live' AFTER actual_start_time;

-- Add notes fields for different teams
ALTER TABLE service_items
ADD COLUMN worship_notes TEXT NULL COMMENT 'Notes for worship team' AFTER notes,
ADD COLUMN tech_notes TEXT NULL COMMENT 'Notes for tech team (lights, slides, etc.)' AFTER worship_notes,
ADD COLUMN transition_notes TEXT NULL COMMENT 'Transition notes to next item' AFTER tech_notes;

-- Add presenter/leader field
ALTER TABLE service_items
ADD COLUMN presenter VARCHAR(200) NULL COMMENT 'Who is leading/presenting this item' AFTER transition_notes;

-- Add fields for special item types
ALTER TABLE service_items
ADD COLUMN video_url VARCHAR(500) NULL COMMENT 'URL for video items' AFTER presenter,
ADD COLUMN slides_url VARCHAR(500) NULL COMMENT 'Link to presentation slides' AFTER video_url;

-- Update position field to be more robust (already exists but ensure it's there)
-- position field should already exist from previous migration

-- Add index for live tracking queries
ALTER TABLE service_items
ADD INDEX idx_service_position (service_id, position);

-- ============================================
-- Create run sheet templates table (optional)
-- ============================================
CREATE TABLE IF NOT EXISTS service_runsheet_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    service_type_id INT NULL,
    template_data JSON NOT NULL COMMENT 'Template items structure',
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_service_type (service_type_id),
    FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Add live mode tracking to services table
-- ============================================
ALTER TABLE services
ADD COLUMN live_mode_active BOOLEAN DEFAULT FALSE COMMENT 'Is service currently running in live mode' AFTER status,
ADD COLUMN live_started_at DATETIME NULL COMMENT 'When live mode was started' AFTER live_mode_active,
ADD COLUMN actual_start_time DATETIME NULL COMMENT 'Actual service start time' AFTER live_started_at,
ADD COLUMN actual_end_time DATETIME NULL COMMENT 'Actual service end time' AFTER actual_start_time;

-- ============================================
-- Create run sheet view for reporting
-- ============================================
CREATE OR REPLACE VIEW v_runsheet_items AS
SELECT
    si.*,
    s.service_date,
    s.start_time as service_start_time,
    s.title as service_title,
    st.name as service_type_name,
    song.title as song_title,
    song.artist as song_artist,
    CASE
        WHEN si.planned_duration IS NOT NULL THEN si.planned_duration
        WHEN si.duration_minutes IS NOT NULL THEN si.duration_minutes
        ELSE 5
    END as effective_duration,
    -- Calculate running total time (sum of all previous items + this item's offset)
    (SELECT SUM(COALESCE(si2.planned_duration, si2.duration_minutes, 5))
     FROM service_items si2
     WHERE si2.service_id = si.service_id
     AND si2.position < si.position) as cumulative_minutes_before,
    -- Calculate actual duration if item has been run
    CASE
        WHEN si.actual_end_time IS NOT NULL AND si.actual_start_time IS NOT NULL
        THEN TIMESTAMPDIFF(MINUTE, si.actual_start_time, si.actual_end_time)
        ELSE NULL
    END as actual_duration_minutes
FROM service_items si
JOIN services s ON si.service_id = s.id
JOIN service_types st ON s.service_type_id = st.id
LEFT JOIN songs song ON si.song_id = song.id
ORDER BY si.service_id, si.position;

-- ============================================
-- Default templates for common services
-- ============================================
-- Insert a basic Sunday morning template
INSERT INTO service_runsheet_templates (name, template_data, is_default) VALUES
('Sunday Morning - Traditional', JSON_ARRAY(
    JSON_OBJECT('item_type', 'song', 'title', 'Opening Song', 'planned_duration', 5),
    JSON_OBJECT('item_type', 'prayer', 'title', 'Opening Prayer', 'planned_duration', 3),
    JSON_OBJECT('item_type', 'song', 'title', 'Worship Set', 'planned_duration', 20),
    JSON_OBJECT('item_type', 'announcement', 'title', 'Announcements', 'planned_duration', 5),
    JSON_OBJECT('item_type', 'offering', 'title', 'Offering', 'planned_duration', 5),
    JSON_OBJECT('item_type', 'sermon', 'title', 'Message', 'planned_duration', 30),
    JSON_OBJECT('item_type', 'song', 'title', 'Closing Song', 'planned_duration', 5),
    JSON_OBJECT('item_type', 'prayer', 'title', 'Closing Prayer', 'planned_duration', 2)
), TRUE);

SELECT 'Migration complete: Run sheet enhancement added' AS status;
