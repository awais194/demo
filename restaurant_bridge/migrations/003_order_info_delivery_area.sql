-- Delivery area of a Website / WhatsApp Invoice: its delivery charge comes from the area set in the ERP (Restaurant > Delivery Areas)
-- Safe to run again: every change checks first whether it is already there (works on MySQL and MariaDB).

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sl_sales_order_info' AND COLUMN_NAME = 'delivery_area_id') = 0,
    'ALTER TABLE sl_sales_order_info ADD COLUMN delivery_area_id INT NULL AFTER order_type', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sl_sales_order_info' AND COLUMN_NAME = 'delivery_area') = 0,
    'ALTER TABLE sl_sales_order_info ADD COLUMN delivery_area VARCHAR(120) NULL AFTER delivery_area_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Website Invoices made before this change: the area the customer picked on the website (only rows without an area yet)
UPDATE sl_sales_order_info i JOIN rs_orders o ON o.erp_sales_id = i.sales_id
LEFT JOIN rs_delivery_areas a ON a.branch_id = o.branch_id AND CONVERT(a.name USING utf8mb4) COLLATE utf8mb4_unicode_ci = o.delivery_area
SET i.delivery_area = o.delivery_area, i.delivery_area_id = a.id
WHERE o.delivery_area IS NOT NULL AND i.delivery_area IS NULL;
