-- rs_branches gets its own auto increment id (branch_id stays unique: one row per branch),
-- and every branch in the ERP's branch table has a row here (new branches start off the website).
-- Safe to run again: each step checks first whether it is already done (works on MySQL and MariaDB).

-- branch_id unique first, so it keeps rows one per branch once it is no longer the primary key
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rs_branches' AND INDEX_NAME = 'uq_branch') = 0,
    'ALTER TABLE rs_branches ADD UNIQUE KEY uq_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- id column: replaces branch_id as the primary key (skipped when rs_branches already has id)
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rs_branches' AND COLUMN_NAME = 'id') = 0,
    'ALTER TABLE rs_branches DROP PRIMARY KEY, ADD COLUMN id INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Branches added before this change
INSERT IGNORE INTO rs_branches (branch_id, show_on_website, sort_order)
SELECT b.id, 0, b.id FROM branch b;

-- Branches added from now on (Stock > Branch, imports, ...). restaurant/branches.php and branch/op_save_branch.php
-- add missing rows too, for servers where triggers can't be created.
DROP TRIGGER IF EXISTS trg_branch_rs_branches;
CREATE TRIGGER trg_branch_rs_branches AFTER INSERT ON branch FOR EACH ROW
    INSERT IGNORE INTO rs_branches (branch_id, show_on_website, sort_order) VALUES (NEW.id, 0, NEW.id);
