SET NAMES utf8mb4;
SET @q=IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND INDEX_NAME='idx_driver_workload')=0,'ALTER TABLE orders ADD INDEX idx_driver_workload(delivery_user,status)','SELECT 1');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @q=IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND INDEX_NAME='idx_dispatch_queue')=0,'ALTER TABLE orders ADD INDEX idx_dispatch_queue(status,delivery_user,id)','SELECT 1');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;
