SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS mk_partner_applications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 kind ENUM('VENDOR','RIDER') NOT NULL,
 applicant_name VARCHAR(150) NOT NULL,
 mobile VARCHAR(10) NOT NULL,
 business_name VARCHAR(200) NULL,
 market ENUM('B2B','B2C','BOTH') NULL,
 shop_category VARCHAR(150) NULL,
 gstin VARCHAR(15) NULL,
 pan VARCHAR(10) NULL,
 vehicle_type VARCHAR(50) NULL,
 dl_number VARCHAR(25) NULL,
 rc_number VARCHAR(30) NULL,
 status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
 reviewed_by INT NULL,
 reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_queue(status,kind,created_at),
 KEY idx_mobile(mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keep existing roles; only extend the enum when partner roles are absent.
SET @q=IF((SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role') NOT LIKE '%''vendor''%', 'ALTER TABLE users MODIFY role ENUM(''admin'',''bpo'',''delivery'',''designer'',''vendor'',''rider'') NOT NULL DEFAULT ''bpo''','SELECT 1');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @q=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mk_partner_applications' AND COLUMN_NAME='login_user_id')=0,'ALTER TABLE mk_partner_applications ADD login_user_id INT NULL, ADD UNIQUE KEY partner_login(login_user_id), ADD FOREIGN KEY(login_user_id) REFERENCES users(id)','SELECT 1');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS mk_partner_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL,
 kind ENUM('GST','PAN','DL','RC') NOT NULL,
 storage_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 mime VARCHAR(40) NOT NULL,
 size_bytes INT UNSIGNED NOT NULL,
 UNIQUE KEY(application_id,kind),
 UNIQUE KEY(storage_key),
 FOREIGN KEY(application_id) REFERENCES mk_partner_applications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
