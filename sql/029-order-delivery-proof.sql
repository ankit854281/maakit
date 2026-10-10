SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS mk_order_safety (
 order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 pickup_hash VARCHAR(255) NOT NULL, drop_hash VARCHAR(255) NOT NULL,
 pickup_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, drop_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 expires_at DATETIME(6) NOT NULL, proof_mime VARCHAR(32) NULL, proof_sha CHAR(64) NULL, proof_blob MEDIUMBLOB NULL,
 FOREIGN KEY(order_id) REFERENCES mk_orders(id), CHECK(pickup_attempts<=5), CHECK(drop_attempts<=5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
