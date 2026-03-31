-- Add API key column for Chrome extension cookie sync
SET @dbname = DATABASE();
SET @tablename = 'songselect_config';
SET @columnname = 'api_key';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  'ALTER TABLE songselect_config ADD COLUMN api_key VARCHAR(64) NULL AFTER ccli_license_number'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
