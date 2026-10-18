SET NAMES utf8mb4;
-- Maakit Jyotish: log kya dekh/pooch rahe hain (bina naam, bina poori janm-tareekh).
-- device = phone par bana random number, kisi insaan se juda nahi.
CREATE TABLE IF NOT EXISTS jy_events (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 created DATETIME NOT NULL,
 device CHAR(16) NOT NULL DEFAULT '',
 kind VARCHAR(16) NOT NULL,
 lang VARCHAR(12) NOT NULL DEFAULT '',
 city VARCHAR(40) NOT NULL DEFAULT '',
 rashi TINYINT NULL,
 lagna TINYINT NULL,
 nak TINYINT NULL,
 birth_year SMALLINT NULL,
 gender VARCHAR(8) NOT NULL DEFAULT '',
 score DECIMAL(4,1) NULL,
 cat VARCHAR(16) NOT NULL DEFAULT '',
 topic VARCHAR(16) NOT NULL DEFAULT '',
 q TEXT NULL,
 KEY created_idx (created),
 KEY kind_idx (kind, created),
 KEY device_idx (device)
);
