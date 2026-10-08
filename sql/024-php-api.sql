-- Maakit PHP/PDO API schema. MySQL 8.0.16+ / MariaDB 10.6+, InnoDB.
-- ADDITIVE mk_ tables preserve the existing live PHP/MySQL tables and data.
-- Each id is a PHP-generated RFC UUID v4; all foreign keys have explicit indexes.
-- DDL commits implicitly. Run through the backed-up updater; never inside checkout.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS mk_users (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 phone_e164 VARCHAR(16) NOT NULL,
 display_name VARCHAR(150) NOT NULL,
 kyc_status ENUM('PENDING','VERIFIED','REJECTED') NOT NULL DEFAULT 'PENDING',
 status ENUM('ACTIVE','SUSPENDED','DELETED') NOT NULL DEFAULT 'ACTIVE',
 session_version INT UNSIGNED NOT NULL DEFAULT 0,
 rto_risk_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 UNIQUE KEY (phone_e164),
 CHECK (phone_e164 REGEXP '^[+][1-9][0-9]{7,14}$'),
 CHECK (rto_risk_score<=100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_roles (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 code VARCHAR(32) NOT NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 UNIQUE KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_permissions (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 code VARCHAR(64) NOT NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 UNIQUE KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_user_roles (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 role_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY user_roles_fk0 (user_id),
 FOREIGN KEY (user_id) REFERENCES mk_users (id),
 KEY user_roles_fk1 (role_id),
 FOREIGN KEY (role_id) REFERENCES mk_roles (id),
 UNIQUE KEY (user_id,role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_role_permissions (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 role_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 permission_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY role_permissions_fk0 (role_id),
 FOREIGN KEY (role_id) REFERENCES mk_roles (id),
 KEY role_permissions_fk1 (permission_id),
 FOREIGN KEY (permission_id) REFERENCES mk_permissions (id),
 UNIQUE KEY (role_id,permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_user_permissions (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 permission_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY user_permissions_fk0 (user_id),
 FOREIGN KEY (user_id) REFERENCES mk_users (id),
 KEY user_permissions_fk1 (permission_id),
 FOREIGN KEY (permission_id) REFERENCES mk_permissions (id),
 UNIQUE KEY (user_id,permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_sessions (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 refresh_hash BINARY(32) NOT NULL,
 session_version INT UNSIGNED NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 revoked_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY sessions_fk0 (user_id),
 FOREIGN KEY (user_id) REFERENCES mk_users (id),
 UNIQUE KEY (refresh_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_vendors (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 owner_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 legal_name VARCHAR(200) NOT NULL,
 status ENUM('ACTIVE','SUSPENDED','PENDING') NOT NULL DEFAULT 'PENDING',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY vendors_fk0 (owner_user_id),
 FOREIGN KEY (owner_user_id) REFERENCES mk_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_vendor_members (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 vendor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 active BOOLEAN NOT NULL DEFAULT TRUE,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY vendor_members_fk0 (vendor_id),
 FOREIGN KEY (vendor_id) REFERENCES mk_vendors (id),
 KEY vendor_members_fk1 (user_id),
 FOREIGN KEY (user_id) REFERENCES mk_users (id),
 UNIQUE KEY (vendor_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_stores (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 vendor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 name VARCHAR(200) NOT NULL,
 address_json JSON NOT NULL,
 latitude DECIMAL(9,6) NULL,
 longitude DECIMAL(9,6) NULL,
 radius_m INT UNSIGNED NOT NULL DEFAULT 5000,
 minimum_order_minor BIGINT UNSIGNED NOT NULL DEFAULT 15000,
 cod_risk_threshold TINYINT UNSIGNED NOT NULL DEFAULT 70,
 supports_hyperlocal BOOLEAN NOT NULL DEFAULT TRUE,
 supports_courier BOOLEAN NOT NULL DEFAULT FALSE,
 own_dispatch BOOLEAN NOT NULL DEFAULT FALSE,
 active BOOLEAN NOT NULL DEFAULT FALSE,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY stores_fk0 (vendor_id),
 FOREIGN KEY (vendor_id) REFERENCES mk_vendors (id),
 UNIQUE KEY (id,vendor_id),
 CHECK (radius_m BETWEEN 1 AND 100000),
 CHECK (cod_risk_threshold<=100),
 CHECK (latitude BETWEEN -90 AND 90),
 CHECK (longitude BETWEEN -180 AND 180),
 CHECK ((latitude IS NULL)=(longitude IS NULL)),
 CHECK (NOT supports_hyperlocal OR latitude IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_store_zones (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 store_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 fulfillment_type ENUM('HYPERLOCAL','COURIER') NOT NULL,
 pincode CHAR(6) NOT NULL,
 active BOOLEAN NOT NULL DEFAULT TRUE,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY store_zones_fk0 (store_id),
 FOREIGN KEY (store_id) REFERENCES mk_stores (id),
 UNIQUE KEY (store_id,fulfillment_type,pincode),
 CHECK (pincode REGEXP '^[1-9][0-9]{5}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_vendor_tax_profiles (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 vendor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 gstin VARCHAR(15) NULL,
 registration_type ENUM('REGULAR','COMPOSITION','UNREGISTERED') NOT NULL,
 state_code CHAR(2) NOT NULL,
 verified_at DATETIME(6) NULL,
 valid_from DATETIME(6) NOT NULL,
 valid_until DATETIME(6) NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY vendor_tax_profiles_fk0 (vendor_id),
 FOREIGN KEY (vendor_id) REFERENCES mk_vendors (id),
 UNIQUE KEY (vendor_id,valid_from),
 CHECK (valid_until IS NULL OR valid_until>valid_from),
 CHECK (gstin IS NULL OR gstin REGEXP '^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_commission_policies (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 store_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 rate DECIMAL(9,8) NOT NULL,
 valid_from DATETIME(6) NOT NULL,
 valid_until DATETIME(6) NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY commission_policies_fk0 (store_id),
 FOREIGN KEY (store_id) REFERENCES mk_stores (id),
 UNIQUE KEY (store_id,valid_from),
 CHECK (rate BETWEEN 0 AND 1),
 CHECK (valid_until IS NULL OR valid_until>valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_tax_rules (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 code VARCHAR(64) NOT NULL,
 rate DECIMAL(9,8) NOT NULL,
 valid_from DATETIME(6) NOT NULL,
 valid_until DATETIME(6) NULL,
 approved_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 source_reference VARCHAR(255) NOT NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY tax_rules_fk0 (approved_by),
 FOREIGN KEY (approved_by) REFERENCES mk_users (id),
 UNIQUE KEY (code,valid_from),
 CHECK (rate BETWEEN 0 AND 1),
 CHECK (valid_until IS NULL OR valid_until>valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_addresses (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 recipient_name VARCHAR(150) NOT NULL,
 phone_e164 VARCHAR(16) NOT NULL,
 lines_json JSON NOT NULL,
 pincode CHAR(6) NOT NULL,
 state_code CHAR(2) NOT NULL,
 latitude DECIMAL(9,6) NULL,
 longitude DECIMAL(9,6) NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY addresses_fk0 (user_id),
 FOREIGN KEY (user_id) REFERENCES mk_users (id),
 UNIQUE KEY (id,user_id),
 CHECK (latitude BETWEEN -90 AND 90),
 CHECK (longitude BETWEEN -180 AND 180),
 CHECK ((latitude IS NULL)=(longitude IS NULL)),
 CHECK (phone_e164 REGEXP '^[+][1-9][0-9]{7,14}$'),
 CHECK (pincode REGEXP '^[1-9][0-9]{5}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_categories (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 parent_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 name VARCHAR(150) NOT NULL,
 slug VARCHAR(150) NOT NULL,
 category_type ENUM('LOCAL_SHOPPING','HOME_SERVICES','B2B') NOT NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY categories_fk0 (parent_id),
 FOREIGN KEY (parent_id) REFERENCES mk_categories (id),
 UNIQUE KEY (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_products (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 category_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 name VARCHAR(200) NOT NULL,
 brand VARCHAR(150) NULL,
 description TEXT NULL,
 hsn_sac VARCHAR(12) NULL,
 active BOOLEAN NOT NULL DEFAULT TRUE,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY products_fk0 (category_id),
 FOREIGN KEY (category_id) REFERENCES mk_categories (id),
 FULLTEXT KEY product_search (name,brand,description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_offers (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 product_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 store_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 is_b2b BOOLEAN NOT NULL DEFAULT FALSE,
 supports_hyperlocal BOOLEAN NOT NULL DEFAULT TRUE,
 supports_courier BOOLEAN NOT NULL DEFAULT FALSE,
 inventory_policy ENUM('TRACKED','ON_REQUEST') NOT NULL DEFAULT 'ON_REQUEST',
 active BOOLEAN NOT NULL DEFAULT FALSE,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY offers_fk0 (product_id),
 FOREIGN KEY (product_id) REFERENCES mk_products (id),
 KEY offers_fk1 (store_id),
 FOREIGN KEY (store_id) REFERENCES mk_stores (id),
 UNIQUE KEY (id,store_id),
 UNIQUE KEY (product_id,store_id,is_b2b)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_variants (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 offer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 sku VARCHAR(100) NOT NULL,
 pack_label VARCHAR(100) NOT NULL,
 unit_price_minor BIGINT UNSIGNED NULL,
 attributes_json JSON NOT NULL,
 price_includes_tax BOOLEAN NOT NULL DEFAULT TRUE,
 tax_rule_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 active BOOLEAN NOT NULL DEFAULT TRUE,
 version BIGINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY variants_fk0 (offer_id),
 FOREIGN KEY (offer_id) REFERENCES mk_offers (id),
 KEY variants_fk1 (tax_rule_id),
 FOREIGN KEY (tax_rule_id) REFERENCES mk_tax_rules (id),
 UNIQUE KEY (offer_id,sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_inventory (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 variant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 on_hand INT UNSIGNED NOT NULL DEFAULT 0,
 reserved INT UNSIGNED NOT NULL DEFAULT 0,
 version BIGINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY inventory_fk0 (variant_id),
 FOREIGN KEY (variant_id) REFERENCES mk_variants (id),
 UNIQUE KEY (variant_id),
 CHECK (reserved<=on_hand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_carts (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 customer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 store_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 fulfillment_type ENUM('HYPERLOCAL','COURIER') NOT NULL,
 state ENUM('ACTIVE','CHECKED_OUT','ABANDONED') NOT NULL DEFAULT 'ACTIVE',
 version BIGINT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY carts_fk0 (customer_id),
 FOREIGN KEY (customer_id) REFERENCES mk_users (id),
 KEY carts_fk1 (store_id),
 FOREIGN KEY (store_id) REFERENCES mk_stores (id),
 UNIQUE KEY (id,store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_cart_items (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 cart_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 store_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 variant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 quantity INT UNSIGNED NOT NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY cart_items_fk0 (cart_id,store_id),
 FOREIGN KEY (cart_id,store_id) REFERENCES mk_carts (id,store_id),
 KEY cart_items_fk1 (store_id),
 FOREIGN KEY (store_id) REFERENCES mk_stores (id),
 KEY cart_items_fk2 (variant_id),
 FOREIGN KEY (variant_id) REFERENCES mk_variants (id),
 UNIQUE KEY (cart_id,variant_id),
 CHECK (quantity BETWEEN 1 AND 1000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_delivery_quotes (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 cart_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 address_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 fulfillment_type ENUM('HYPERLOCAL','COURIER') NOT NULL,
 delivery_minor BIGINT UNSIGNED NOT NULL,
 service_fee_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 consumed_at DATETIME(6) NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY delivery_quotes_fk0 (cart_id),
 FOREIGN KEY (cart_id) REFERENCES mk_carts (id),
 KEY delivery_quotes_fk1 (address_id),
 FOREIGN KEY (address_id) REFERENCES mk_addresses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_orders (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 customer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 store_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 vendor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 cart_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 address_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 fulfillment_type ENUM('HYPERLOCAL','COURIER') NOT NULL,
 status ENUM('AWAITING_VENDOR','CONFIRMED','PACKING','READY','DISPATCHED','DELIVERED','CANCELLED','RTO') NOT NULL DEFAULT 'AWAITING_VENDOR',
 payment_method ENUM('COD','DIRECT_TO_VENDOR') NOT NULL,
 payment_recipient ENUM('VENDOR') NOT NULL DEFAULT 'VENDOR',
 currency CHAR(3) NOT NULL DEFAULT 'INR',
 subtotal_minor BIGINT UNSIGNED NOT NULL,
 delivery_minor BIGINT UNSIGNED NOT NULL,
 service_fee_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 total_minor BIGINT UNSIGNED NOT NULL,
 risk_score_snapshot TINYINT UNSIGNED NOT NULL,
 idempotency_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 address_snapshot JSON NOT NULL,
 tax_snapshot JSON NOT NULL,
 commission_snapshot JSON NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 delivered_at DATETIME(6) NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY orders_fk0 (customer_id),
 FOREIGN KEY (customer_id) REFERENCES mk_users (id),
 KEY orders_fk1 (store_id,vendor_id),
 FOREIGN KEY (store_id,vendor_id) REFERENCES mk_stores (id,vendor_id),
 KEY orders_fk2 (vendor_id),
 FOREIGN KEY (vendor_id) REFERENCES mk_vendors (id),
 KEY orders_fk3 (cart_id),
 FOREIGN KEY (cart_id) REFERENCES mk_carts (id),
 KEY orders_fk4 (address_id,customer_id),
 FOREIGN KEY (address_id,customer_id) REFERENCES mk_addresses (id,user_id),
 UNIQUE KEY (cart_id),
 UNIQUE KEY (customer_id,idempotency_key),
 UNIQUE KEY (id,fulfillment_type),
 CHECK (risk_score_snapshot<=100),
 CHECK (currency='INR'),
 CHECK (total_minor=subtotal_minor+delivery_minor+service_fee_minor),
 CHECK (status<>'DELIVERED' OR delivered_at IS NOT NULL),
 KEY dispatch_queue (status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_order_items (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 variant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 quantity INT UNSIGNED NOT NULL,
 unit_price_minor BIGINT UNSIGNED NOT NULL,
 line_total_minor BIGINT UNSIGNED NOT NULL,
 inventory_policy ENUM('TRACKED','ON_REQUEST') NOT NULL,
 product_snapshot JSON NOT NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY order_items_fk0 (order_id),
 FOREIGN KEY (order_id) REFERENCES mk_orders (id),
 KEY order_items_fk1 (variant_id),
 FOREIGN KEY (variant_id) REFERENCES mk_variants (id),
 UNIQUE KEY (order_id,variant_id),
 CHECK (quantity BETWEEN 1 AND 1000),
 CHECK (line_total_minor=quantity*unit_price_minor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_inventory_reservations (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 order_item_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 inventory_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 quantity INT UNSIGNED NOT NULL,
 status ENUM('HELD','CONSUMED','RELEASED') NOT NULL DEFAULT 'HELD',
 expires_at DATETIME(6) NOT NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY inventory_reservations_fk0 (order_item_id),
 FOREIGN KEY (order_item_id) REFERENCES mk_order_items (id),
 KEY inventory_reservations_fk1 (inventory_id),
 FOREIGN KEY (inventory_id) REFERENCES mk_inventory (id),
 UNIQUE KEY (order_item_id),
 CHECK (quantity>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_inventory_movements (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 inventory_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 reservation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 actor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 kind ENUM('RESERVE','RELEASE','SALE','RETURN','ADJUSTMENT','RECEIPT') NOT NULL,
 on_hand_delta INT NOT NULL,
 reserved_delta INT NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY inventory_movements_fk0 (inventory_id),
 FOREIGN KEY (inventory_id) REFERENCES mk_inventory (id),
 KEY inventory_movements_fk1 (reservation_id),
 FOREIGN KEY (reservation_id) REFERENCES mk_inventory_reservations (id),
 KEY inventory_movements_fk2 (actor_id),
 FOREIGN KEY (actor_id) REFERENCES mk_users (id),
 UNIQUE KEY (reservation_id,kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_order_events (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 status VARCHAR(32) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY order_events_fk0 (order_id),
 FOREIGN KEY (order_id) REFERENCES mk_orders (id),
 KEY order_events_fk1 (actor_id),
 FOREIGN KEY (actor_id) REFERENCES mk_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_delivery_otps (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 otp_hmac BINARY(32) NOT NULL,
 key_version INT UNSIGNED NOT NULL,
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 expires_at DATETIME(6) NOT NULL,
 verified_at DATETIME(6) NULL,
 verified_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY delivery_otps_fk0 (order_id),
 FOREIGN KEY (order_id) REFERENCES mk_orders (id),
 KEY delivery_otps_fk1 (verified_by),
 FOREIGN KEY (verified_by) REFERENCES mk_users (id),
 UNIQUE KEY (order_id),
 CHECK (attempts<=5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_riders (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 vendor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 kyc_status ENUM('PENDING','VERIFIED','REJECTED') NOT NULL DEFAULT 'PENDING',
 active BOOLEAN NOT NULL DEFAULT FALSE,
 vehicle_type VARCHAR(32) NOT NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY riders_fk0 (user_id),
 FOREIGN KEY (user_id) REFERENCES mk_users (id),
 KEY riders_fk1 (vendor_id),
 FOREIGN KEY (vendor_id) REFERENCES mk_vendors (id),
 UNIQUE KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_shipments (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 rider_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 fulfillment_type ENUM('HYPERLOCAL','COURIER') NOT NULL,
 provider ENUM('MERCHANT_STAFF','LOCAL_POOL','THREE_PL','INDIA_POST') NOT NULL,
 provider_reference VARCHAR(150) NULL,
 accepted_at DATETIME(6) NULL,
 dispatched_at DATETIME(6) NULL,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY shipments_fk0 (order_id,fulfillment_type),
 FOREIGN KEY (order_id,fulfillment_type) REFERENCES mk_orders (id,fulfillment_type),
 KEY shipments_fk1 (rider_id),
 FOREIGN KEY (rider_id) REFERENCES mk_riders (id),
 UNIQUE KEY (order_id),
 UNIQUE KEY (provider,provider_reference),
 CHECK (provider NOT IN ('MERCHANT_STAFF','LOCAL_POOL') OR rider_id IS NOT NULL),
 CHECK (provider NOT IN ('THREE_PL','INDIA_POST') OR fulfillment_type='COURIER')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_dispatch_attempts (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 rider_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 level TINYINT UNSIGNED NOT NULL,
 status ENUM('OFFERED','ACCEPTED','REJECTED','EXPIRED') NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY dispatch_attempts_fk0 (order_id),
 FOREIGN KEY (order_id) REFERENCES mk_orders (id),
 KEY dispatch_attempts_fk1 (rider_id),
 FOREIGN KEY (rider_id) REFERENCES mk_riders (id),
 CHECK (level BETWEEN 1 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_outbox (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 event_type VARCHAR(64) NOT NULL,
 payload_json JSON NOT NULL,
 dedupe_key VARCHAR(150) NOT NULL,
 available_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 published_at DATETIME(6) NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY outbox_fk0 (order_id),
 FOREIGN KEY (order_id) REFERENCES mk_orders (id),
 UNIQUE KEY (dedupe_key),
 KEY outbox_due (published_at,available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_audit_events (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 action VARCHAR(64) NOT NULL,
 resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 details_json JSON NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY audit_events_fk0 (actor_id),
 FOREIGN KEY (actor_id) REFERENCES mk_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_b2b_rfqs (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 buyer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 offer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 quantity INT UNSIGNED NOT NULL,
 message TEXT NULL,
 status ENUM('OPEN','QUOTED','CLOSED') NOT NULL DEFAULT 'OPEN',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY b2b_rfqs_fk0 (buyer_id),
 FOREIGN KEY (buyer_id) REFERENCES mk_users (id),
 KEY b2b_rfqs_fk1 (offer_id),
 FOREIGN KEY (offer_id) REFERENCES mk_offers (id),
 CHECK (quantity>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_services (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 store_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 category_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 name VARCHAR(200) NOT NULL,
 duration_minutes INT UNSIGNED NOT NULL,
 price_minor BIGINT UNSIGNED NULL,
 quote_required BOOLEAN NOT NULL DEFAULT TRUE,
 active BOOLEAN NOT NULL DEFAULT FALSE,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY services_fk0 (store_id),
 FOREIGN KEY (store_id) REFERENCES mk_stores (id),
 KEY services_fk1 (category_id),
 FOREIGN KEY (category_id) REFERENCES mk_categories (id),
 CHECK (duration_minutes>0),
 CHECK (quote_required OR price_minor IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_service_bookings (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 customer_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 service_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 address_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 starts_at DATETIME(6) NOT NULL,
 ends_at DATETIME(6) NOT NULL,
 status ENUM('REQUESTED','CONFIRMED','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'REQUESTED',
 idempotency_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY service_bookings_fk0 (customer_id),
 FOREIGN KEY (customer_id) REFERENCES mk_users (id),
 KEY service_bookings_fk1 (service_id),
 FOREIGN KEY (service_id) REFERENCES mk_services (id),
 KEY service_bookings_fk2 (address_id,customer_id),
 FOREIGN KEY (address_id,customer_id) REFERENCES mk_addresses (id,user_id),
 UNIQUE KEY (customer_id,idempotency_key),
 CHECK (ends_at>starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_ledger_accounts (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 code VARCHAR(100) NOT NULL,
 vendor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 currency CHAR(3) NOT NULL DEFAULT 'INR',
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY ledger_accounts_fk0 (vendor_id),
 FOREIGN KEY (vendor_id) REFERENCES mk_vendors (id),
 UNIQUE KEY (code),
 CHECK (currency='INR')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_ledger_journals (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 state ENUM('DRAFT','POSTED') NOT NULL DEFAULT 'DRAFT',
 idempotency_key VARCHAR(150) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY ledger_journals_fk0 (order_id),
 FOREIGN KEY (order_id) REFERENCES mk_orders (id),
 UNIQUE KEY (idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_ledger_entries (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 journal_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 debit_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 credit_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY ledger_entries_fk0 (journal_id),
 FOREIGN KEY (journal_id) REFERENCES mk_ledger_journals (id),
 KEY ledger_entries_fk1 (account_id),
 FOREIGN KEY (account_id) REFERENCES mk_ledger_accounts (id),
 CHECK ((debit_minor>0 AND credit_minor=0) OR (credit_minor>0 AND debit_minor=0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_payout_accounts (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 vendor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 kyc_verified_at DATETIME(6) NULL,
 provider VARCHAR(32) NOT NULL,
 beneficiary_ciphertext BLOB NOT NULL,
 active BOOLEAN NOT NULL DEFAULT FALSE,
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 UNIQUE KEY (id,vendor_id),
 KEY payout_accounts_fk0 (vendor_id),
 FOREIGN KEY (vendor_id) REFERENCES mk_vendors (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mk_payout_batches (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 vendor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 account_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 state ENUM('DRAFT','QUEUED','PROCESSING','SUCCEEDED','FAILED') NOT NULL DEFAULT 'DRAFT',
 idempotency_key VARCHAR(150) NOT NULL,
 gross_minor BIGINT UNSIGNED NOT NULL,
 commission_minor BIGINT UNSIGNED NOT NULL,
 commission_gst_minor BIGINT UNSIGNED NOT NULL,
 tcs_minor BIGINT UNSIGNED NOT NULL,
 tds_minor BIGINT UNSIGNED NOT NULL,
 net_minor BIGINT UNSIGNED NOT NULL,
 tax_snapshot JSON NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY (id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY payout_batches_fk0 (vendor_id),
 FOREIGN KEY (vendor_id) REFERENCES mk_vendors (id),
 KEY payout_batches_fk1 (account_id,vendor_id),
 FOREIGN KEY (account_id,vendor_id) REFERENCES mk_payout_accounts (id,vendor_id),
 UNIQUE KEY (idempotency_key),
 CHECK (gross_minor=commission_minor+commission_gst_minor+tcs_minor+tds_minor+net_minor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO mk_roles(id,code) VALUES('10000000-0000-4000-8000-000000000001','ADMIN');
INSERT IGNORE INTO mk_roles(id,code) VALUES('10000000-0000-4000-8000-000000000002','VENDOR');
INSERT IGNORE INTO mk_roles(id,code) VALUES('10000000-0000-4000-8000-000000000003','RIDER');
INSERT IGNORE INTO mk_roles(id,code) VALUES('10000000-0000-4000-8000-000000000004','CUSTOMER');
INSERT IGNORE INTO mk_roles(id,code) VALUES('10000000-0000-4000-8000-000000000005','B2B_BUYER');
INSERT IGNORE INTO mk_permissions(id,code) VALUES('20000000-0000-4000-8000-000000000001','DISPATCH_WRITE');
INSERT IGNORE INTO mk_permissions(id,code) VALUES('20000000-0000-4000-8000-000000000002','FINANCE_READ');
INSERT IGNORE INTO mk_permissions(id,code) VALUES('20000000-0000-4000-8000-000000000003','FINANCE_WRITE');
INSERT IGNORE INTO mk_permissions(id,code) VALUES('20000000-0000-4000-8000-000000000004','CATALOG_WRITE');
INSERT IGNORE INTO mk_permissions(id,code) VALUES('20000000-0000-4000-8000-000000000005','ORDER_READ_ALL');
INSERT IGNORE INTO mk_permissions(id,code) VALUES('20000000-0000-4000-8000-000000000006','RBAC_WRITE');
-- ADMIN has no automatic permission grants. RIDER's dispatch access must remain assignment-scoped.

-- Trusted tariffs: no customer endpoint can modify them; courier zones need an agreed carrier tariff.
CREATE TABLE IF NOT EXISTS mk_delivery_policies (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 store_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 fulfillment_type ENUM('HYPERLOCAL','COURIER') NOT NULL,
 delivery_minor BIGINT UNSIGNED NOT NULL,
 service_fee_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
 active BOOLEAN NOT NULL DEFAULT FALSE,
 PRIMARY KEY(id),
 CHECK (id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 KEY delivery_policies_store(store_id),
 FOREIGN KEY(store_id) REFERENCES mk_stores(id),
 UNIQUE KEY(store_id,fulfillment_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
