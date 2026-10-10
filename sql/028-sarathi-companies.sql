SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS mk_delivery_companies (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 owner_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 name VARCHAR(120) NOT NULL, phone VARCHAR(16) NOT NULL,
 state ENUM('PENDING','ACTIVE','SUSPENDED') NOT NULL DEFAULT 'PENDING',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(owner_id) REFERENCES mk_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mk_company_areas (
 company_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 store_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 base_minor INT UNSIGNED NOT NULL, drop_minor INT UNSIGNED NOT NULL,
 radius_m INT UNSIGNED NOT NULL, active BOOLEAN NOT NULL DEFAULT TRUE,
 PRIMARY KEY(company_id,store_id),
 FOREIGN KEY(company_id) REFERENCES mk_delivery_companies(id),
 FOREIGN KEY(store_id) REFERENCES mk_stores(id), CHECK(radius_m BETWEEN 100 AND 50000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mk_company_riders (
 company_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 rider_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 active BOOLEAN NOT NULL DEFAULT TRUE, PRIMARY KEY(company_id,rider_id),
 FOREIGN KEY(company_id) REFERENCES mk_delivery_companies(id),
 FOREIGN KEY(rider_id) REFERENCES mk_riders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mk_company_keys (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 company_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 expires_at DATETIME(6) NOT NULL, revoked_at DATETIME(6) NULL,
 FOREIGN KEY(company_id) REFERENCES mk_delivery_companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mk_company_deliveries (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 company_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 store_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 reference VARCHAR(80) NOT NULL, fingerprint CHAR(64) NOT NULL,
 fleet ENUM('SHARED','OWN') NOT NULL,
 state ENUM('REQUESTED','OFFERED','ASSIGNED','PICKED_UP','DELIVERED','CANCELLED') NOT NULL DEFAULT 'REQUESTED',
 rider_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 offered_rider_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 offer_expires_at DATETIME(6) NULL, last_rider_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 pickup_json JSON NOT NULL, emergency_json JSON NOT NULL,
 pickup_hash VARCHAR(255) NOT NULL, pickup_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 otp_expires_at DATETIME(6) NOT NULL, next_stop TINYINT UNSIGNED NOT NULL DEFAULT 1,
 fee_minor INT UNSIGNED NOT NULL, tracking_hash CHAR(64) NOT NULL UNIQUE,
 tracking_expires_at DATETIME(6) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), delivered_at DATETIME(6) NULL,
 UNIQUE KEY(company_id,reference), KEY(state,offer_expires_at), KEY(rider_id,state), KEY(offered_rider_id,state),
 FOREIGN KEY(company_id) REFERENCES mk_delivery_companies(id), FOREIGN KEY(store_id) REFERENCES mk_stores(id),
 FOREIGN KEY(rider_id) REFERENCES mk_riders(id), FOREIGN KEY(offered_rider_id) REFERENCES mk_riders(id),
 CHECK(pickup_attempts<=5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS mk_company_stops (
 delivery_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 sequence_no TINYINT UNSIGNED NOT NULL, address_json JSON NOT NULL,
 otp_hash VARCHAR(255) NOT NULL, otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 delivered_at DATETIME(6) NULL, proof_mime VARCHAR(32) NULL, proof_sha CHAR(64) NULL, proof_blob MEDIUMBLOB NULL,
 PRIMARY KEY(delivery_id,sequence_no), FOREIGN KEY(delivery_id) REFERENCES mk_company_deliveries(id),
 CHECK(sequence_no BETWEEN 1 AND 10), CHECK(otp_attempts<=5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
