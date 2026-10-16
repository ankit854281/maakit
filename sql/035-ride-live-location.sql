-- 035: Current live Sarathi point for active ride/parcel tracking.
-- Only the latest coordinates are kept. Existing PHP installs also add these
-- columns idempotently through trip_location_upgrade().
SET NAMES utf8mb4;
SET @mk_035_rider_lat = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rides' AND COLUMN_NAME='rider_lat');
SET @mk_035_sql = IF(@mk_035_rider_lat=0, 'ALTER TABLE rides ADD COLUMN rider_lat DECIMAL(10,7) DEFAULT NULL', 'SELECT 1');
PREPARE mk_035_stmt FROM @mk_035_sql; EXECUTE mk_035_stmt; DEALLOCATE PREPARE mk_035_stmt;
SET @mk_035_rider_lng = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rides' AND COLUMN_NAME='rider_lng');
SET @mk_035_sql = IF(@mk_035_rider_lng=0, 'ALTER TABLE rides ADD COLUMN rider_lng DECIMAL(10,7) DEFAULT NULL', 'SELECT 1');
PREPARE mk_035_stmt FROM @mk_035_sql; EXECUTE mk_035_stmt; DEALLOCATE PREPARE mk_035_stmt;
SET @mk_035_rider_at = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rides' AND COLUMN_NAME='rider_location_at');
SET @mk_035_sql = IF(@mk_035_rider_at=0, 'ALTER TABLE rides ADD COLUMN rider_location_at BIGINT UNSIGNED DEFAULT NULL', 'SELECT 1');
PREPARE mk_035_stmt FROM @mk_035_sql; EXECUTE mk_035_stmt; DEALLOCATE PREPARE mk_035_stmt;
