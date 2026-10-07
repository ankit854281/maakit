SET NAMES utf8mb4;
SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='support_resolution' AND COLUMN_NAME='return_stage')=0,
 'ALTER TABLE support_resolution ADD return_stage ENUM(''not_required'',''requested'',''scheduled'',''collected'',''received'') NOT NULL DEFAULT ''not_required''', 'SELECT 1');
PREPARE stmt FROM @q;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @q = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='support_resolution' AND COLUMN_NAME='return_date')=0,
 'ALTER TABLE support_resolution ADD return_date DATE NULL', 'SELECT 1');
PREPARE stmt FROM @q;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
