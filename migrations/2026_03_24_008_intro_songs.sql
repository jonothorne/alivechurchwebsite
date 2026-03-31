-- Add intro song support
-- Allows marking songs as intro songs and flows to optionally start with them

-- Add is_intro_song column to songs table (ignore error if exists)
ALTER TABLE songs ADD COLUMN is_intro_song TINYINT(1) DEFAULT 0 COMMENT 'Whether this is an intro/countdown song';

-- Add start_with_intro column to custom_worship_flows table (ignore error if exists)
ALTER TABLE custom_worship_flows ADD COLUMN start_with_intro TINYINT(1) DEFAULT 0 COMMENT 'Whether to start with an intro song';
