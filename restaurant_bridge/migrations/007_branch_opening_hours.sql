-- Opening hours per branch (ERP: Restaurant > Website On/Off > Branches), e.g. one branch 8:00 PM to 2:00 AM,
-- another 6:00 PM to 2:00 AM. Empty = the website's opening and closing time.
-- Safe to run again: every column is only added when it is not there yet (works on MySQL and MariaDB).

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rs_branches' AND COLUMN_NAME = 'open_time') = 0,
    'ALTER TABLE rs_branches ADD COLUMN open_time TIME NULL AFTER website_open', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rs_branches' AND COLUMN_NAME = 'close_time') = 0,
    'ALTER TABLE rs_branches ADD COLUMN close_time TIME NULL AFTER open_time', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
