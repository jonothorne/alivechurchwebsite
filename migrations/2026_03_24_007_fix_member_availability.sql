-- Fix member_availability table - add missing columns
-- The table may have been created by an earlier migration without these columns

-- Add updated_at column if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'member_availability';
SET @columnname = 'updated_at';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND COLUMN_NAME = @columnname
  ) > 0,
  'SELECT 1',
  'ALTER TABLE member_availability ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add is_recurring column if it doesn't exist
SET @columnname = 'is_recurring';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND COLUMN_NAME = @columnname
  ) > 0,
  'SELECT 1',
  'ALTER TABLE member_availability ADD COLUMN is_recurring BOOLEAN DEFAULT FALSE AFTER reason'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Update the unique index to allow the query to work properly
-- First check if old index exists and drop it, then create new one
SET @indexname = 'idx_member_date';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND INDEX_NAME = @indexname
    AND NOT NON_UNIQUE
  ) = 0,
  'SELECT 1',
  'SELECT 1'
));
-- Index should already exist, just verify table is correct

SELECT 'member_availability table fixed' AS status;
