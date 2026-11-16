-- Website discount per branch (ERP: Restaurant > Website On/Off > Website Discount > Branches): only ticked branches give it
-- Safe to run again: the column is only added when it is not there yet (works on MySQL and MariaDB).

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rs_branches' AND COLUMN_NAME = 'discount_enabled') = 0,
    'ALTER TABLE rs_branches ADD COLUMN discount_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER show_on_website', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
