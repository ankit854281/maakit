SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS service_area_meta (
 village_id INT PRIMARY KEY,
 city VARCHAR(80) NOT NULL DEFAULT '',
 state VARCHAR(80) NOT NULL DEFAULT '',
 pincode CHAR(6) NOT NULL DEFAULT '',
 delivery_on TINYINT(1) NOT NULL DEFAULT 0,
 booking_on TINYINT(1) NOT NULL DEFAULT 0,
 base_fee INT DEFAULT NULL,
 first_free TINYINT(1) NOT NULL DEFAULT 0,
 INDEX(city), INDEX(pincode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS service_area_shops (
 village_id INT NOT NULL,
 business_id INT NOT NULL,
 PRIMARY KEY(village_id,business_id), INDEX(business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Preserve exact existing shop/locality associations once. No guessed distances.
-- The marker prevents a later deployment from undoing an admin's removal.
INSERT IGNORE INTO service_area_shops(village_id,business_id)
 SELECT v.id,b.id FROM villages v JOIN businesses b ON b.village=v.name
 WHERE NOT EXISTS (SELECT 1 FROM settings WHERE k='coverage_backfill_done');
INSERT IGNORE INTO settings(k,v) VALUES ('coverage_backfill_done','1');
