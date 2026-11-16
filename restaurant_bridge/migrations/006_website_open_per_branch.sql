-- Website On/Off per branch (ERP: Restaurant > Website On/Off > Branches): an unticked branch is closed on the website
-- (customers see the closed popup and can't order) while the other branches stay open.
-- Safe to run again: the column is only added when it is not there yet (works on MySQL and MariaDB).

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rs_branches' AND COLUMN_NAME = 'website_open') = 0,
    'ALTER TABLE rs_branches ADD COLUMN website_open TINYINT(1) NOT NULL DEFAULT 1 AFTER show_on_website', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
