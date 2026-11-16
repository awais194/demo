-- Website discount, order type on invoices, delivery charges kept for the rider.
-- Safe to run again: every change checks first whether it is already there (works on MySQL and MariaDB).

-- Website discount (ERP: Restaurant > Website On/Off): percent off every website order, between two dates (empty = no limit)
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rs_website_settings' AND COLUMN_NAME = 'discount_percent') = 0,
    'ALTER TABLE rs_website_settings ADD COLUMN discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rs_website_settings' AND COLUMN_NAME = 'discount_from') = 0,
    'ALTER TABLE rs_website_settings ADD COLUMN discount_from DATE NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rs_website_settings' AND COLUMN_NAME = 'discount_to') = 0,
    'ALTER TABLE rs_website_settings ADD COLUMN discount_to DATE NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- The discount an order got when it was placed (kept when the order is edited in the ERP)
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rs_orders' AND COLUMN_NAME = 'discount_percent') = 0,
    'ALTER TABLE rs_orders ADD COLUMN discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER subtotal', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rs_orders' AND COLUMN_NAME = 'discount_amount') = 0,
    'ALTER TABLE rs_orders ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER discount_percent', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Delivery or pickup of a Website (WIV) / WhatsApp (WHIV) Invoice, and the delivery charges the rider keeps.
-- Delivery charges are not part of the invoice (not posted to Sales). Own table like sl_sales_dine_in:
-- the ERP saves sl_sales by column position (DatabaseInsertUpdateHelper), so sl_sales itself can't take new columns.
CREATE TABLE IF NOT EXISTS sl_sales_order_info (
    sales_id INT NOT NULL PRIMARY KEY,
    order_type ENUM('delivery','pickup') NOT NULL DEFAULT 'pickup',
    delivery_charges DECIMAL(10,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Website Invoices made before this change
INSERT IGNORE INTO sl_sales_order_info (sales_id, order_type, delivery_charges)
SELECT erp_sales_id, order_type, delivery_fee FROM rs_orders WHERE erp_sales_id IS NOT NULL;
