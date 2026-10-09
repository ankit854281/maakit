SET NAMES utf8mb4;
-- Preserve all original rows. Staff may have no phone; never invent a number or merge accounts by phone.
SET @q=IF((SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mk_users' AND COLUMN_NAME='phone_e164')='NO','ALTER TABLE mk_users MODIFY phone_e164 VARCHAR(16) NULL','SELECT 1');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS mk_legacy_identities (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 source ENUM('CUSTOMER','STAFF','SHOP') NOT NULL,
 legacy_id BIGINT UNSIGNED NOT NULL,
 user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 role_code VARCHAR(32) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY(source,legacy_id),
 UNIQUE KEY(user_id),
 KEY legacy_user(user_id), FOREIGN KEY(user_id) REFERENCES mk_users(id),
 CHECK(id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mk_session_origins (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 session_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 identity_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 credential_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 php_session_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 shop_token_id BIGINT UNSIGNED NULL,
 UNIQUE KEY(session_id),
 KEY origin_session(session_id), FOREIGN KEY(session_id) REFERENCES mk_sessions(id),
 KEY origin_identity(identity_id), FOREIGN KEY(identity_id) REFERENCES mk_legacy_identities(id),
 CHECK(id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mk_dispatch_config (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 store_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 enabled BOOLEAN NOT NULL DEFAULT FALSE,
 UNIQUE KEY(store_id), KEY config_store(store_id), FOREIGN KEY(store_id) REFERENCES mk_stores(id),
 CHECK(id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mk_rider_duty (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 rider_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 available_until DATETIME(6) NOT NULL,
 UNIQUE KEY(rider_id), KEY duty_rider(rider_id), FOREIGN KEY(rider_id) REFERENCES mk_riders(id),
 CHECK(id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mk_rider_zones (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 rider_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 store_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 fulfillment_type ENUM('HYPERLOCAL','COURIER') NOT NULL,
 active BOOLEAN NOT NULL DEFAULT FALSE,
 UNIQUE KEY(rider_id,store_id,fulfillment_type),
 KEY zones_rider(rider_id), FOREIGN KEY(rider_id) REFERENCES mk_riders(id),
 KEY zones_store(store_id), FOREIGN KEY(store_id) REFERENCES mk_stores(id),
 CHECK(id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mk_dispatch_jobs (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 level TINYINT UNSIGNED NOT NULL DEFAULT 1,
 state ENUM('READY','OFFERED','PROVIDER_WAIT','ASSIGNED','MANUAL_REQUIRED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'READY',
 next_action_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 reason_code VARCHAR(64) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY(order_id), KEY job_order(order_id), FOREIGN KEY(order_id) REFERENCES mk_orders(id),
 KEY job_due(state,next_action_at), CHECK(level BETWEEN 1 AND 4),
 CHECK(id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mk_rider_slots (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 rider_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 state ENUM('OFFERED','ASSIGNED') NOT NULL,
 UNIQUE KEY(rider_id), UNIQUE KEY(order_id),
 KEY slot_rider(rider_id), FOREIGN KEY(rider_id) REFERENCES mk_riders(id),
 KEY slot_order(order_id), FOREIGN KEY(order_id) REFERENCES mk_orders(id),
 CHECK(id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mk_provider_requests (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 job_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 provider ENUM('THREE_PL','INDIA_POST') NOT NULL,
 state ENUM('NEW','SENDING','UNKNOWN','BOOKED','DECLINED','DISABLED') NOT NULL DEFAULT 'NEW',
 tracking_reference VARCHAR(150) NULL,
 lease_until DATETIME(6) NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 UNIQUE KEY(job_id,provider),
 KEY request_job(job_id), FOREIGN KEY(job_id) REFERENCES mk_dispatch_jobs(id),
 KEY request_order(order_id), FOREIGN KEY(order_id) REFERENCES mk_orders(id),
 CHECK(id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mk_rfq_requests (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 rfq_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 buyer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 idempotency_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 UNIQUE KEY(buyer_id,idempotency_key), UNIQUE KEY(rfq_id),
 KEY request_rfq(rfq_id), FOREIGN KEY(rfq_id) REFERENCES mk_b2b_rfqs(id),
 KEY request_buyer(buyer_id), FOREIGN KEY(buyer_id) REFERENCES mk_users(id),
 CHECK(id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO mk_roles(id,code) VALUES('10000000-0000-4000-8000-000000000006','BPO'),('10000000-0000-4000-8000-000000000007','DESIGNER');
INSERT IGNORE INTO mk_permissions(id,code) VALUES('20000000-0000-4000-8000-000000000007','DISPATCH_OWN');
INSERT IGNORE INTO mk_role_permissions(id,role_id,permission_id) VALUES('30000000-0000-4000-8000-000000000001','10000000-0000-4000-8000-000000000002','20000000-0000-4000-8000-000000000007');
-- Explicit local 3PL contracts may preserve HYPERLOCAL; postal shipping still requires COURIER.
SELECT GROUP_CONCAT(CONCAT(IF(VERSION() LIKE '%MariaDB%','DROP CONSTRAINT ','DROP CHECK '),'`',tc.CONSTRAINT_NAME,'`') SEPARATOR ', ') INTO @drop_mode
 FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME
 WHERE tc.TABLE_SCHEMA=DATABASE() AND tc.TABLE_NAME='mk_shipments' AND tc.CONSTRAINT_TYPE='CHECK' AND cc.CHECK_CLAUSE LIKE '%THREE_PL%' AND cc.CHECK_CLAUSE LIKE '%fulfillment_type%';
SET @q=IF(@drop_mode IS NULL,'SELECT 1',CONCAT('ALTER TABLE mk_shipments ',@drop_mode));
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @q=IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mk_shipments' AND CONSTRAINT_NAME='mk_shipments_carrier_mode')=0,"ALTER TABLE mk_shipments ADD CONSTRAINT mk_shipments_carrier_mode CHECK(provider<>'INDIA_POST' OR fulfillment_type='COURIER')",'SELECT 1');
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS mk_carrier_parcels (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 weight_g INT UNSIGNED NOT NULL,
 length_mm INT UNSIGNED NOT NULL,
 width_mm INT UNSIGNED NOT NULL,
 height_mm INT UNSIGNED NOT NULL,
 verified_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 UNIQUE KEY(order_id), KEY parcel_order(order_id), FOREIGN KEY(order_id) REFERENCES mk_orders(id),
 KEY parcel_verifier(verified_by), FOREIGN KEY(verified_by) REFERENCES mk_users(id),
 CHECK(weight_g BETWEEN 1 AND 100000), CHECK(length_mm BETWEEN 1 AND 3000), CHECK(width_mm BETWEEN 1 AND 3000), CHECK(height_mm BETWEEN 1 AND 3000),
 CHECK(id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
