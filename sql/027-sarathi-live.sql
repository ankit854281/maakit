SET NAMES utf8mb4;
-- Additive, repeatable Sarathi integration. No existing customer goods-payment flow changes.
CREATE TABLE IF NOT EXISTS mk_partner_locations (
 rider_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 latitude DECIMAL(10,7) NOT NULL,
 longitude DECIMAL(10,7) NOT NULL,
 accuracy_m DECIMAL(9,2) NOT NULL,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(rider_id) REFERENCES mk_riders(id),
 CHECK(latitude BETWEEN -90 AND 90), CHECK(longitude BETWEEN -180 AND 180), CHECK(accuracy_m>=0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS mk_order_emergency_contacts (
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 slot TINYINT UNSIGNED NOT NULL,
 name VARCHAR(80) NOT NULL,
 phone VARCHAR(16) NOT NULL,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(order_id,slot), FOREIGN KEY(order_id) REFERENCES mk_orders(id), CHECK(slot BETWEEN 1 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS mk_delivery_fee_payments (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 customer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 amount_minor BIGINT UNSIGNED NOT NULL,
 provider_order_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
 provider_payment_id VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
 state ENUM('CREATING','CREATED','RECONCILE','CAPTURED') NOT NULL DEFAULT 'CREATING',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 captured_at DATETIME(6) NULL,
 UNIQUE KEY(order_id), UNIQUE KEY(provider_order_id), UNIQUE KEY(provider_payment_id),
 FOREIGN KEY(order_id) REFERENCES mk_orders(id), FOREIGN KEY(customer_id) REFERENCES mk_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
