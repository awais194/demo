-- Website discount on deals or not (ERP: Restaurant > Website On/Off > Website Discount > Apply on deals)
-- Safe to run again: every change checks first whether it is already there (works on MySQL and MariaDB).

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rs_website_settings' AND COLUMN_NAME = 'discount_on_deals') = 0,
    'ALTER TABLE rs_website_settings ADD COLUMN discount_on_deals TINYINT(1) NOT NULL DEFAULT 1 AFTER discount_to', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Whether the order's discount covered its deals, kept for edits in the ERP (orders before this: deals were discounted)
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rs_orders' AND COLUMN_NAME = 'discount_on_deals') = 0,
    'ALTER TABLE rs_orders ADD COLUMN discount_on_deals TINYINT(1) NOT NULL DEFAULT 1 AFTER discount_amount', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
