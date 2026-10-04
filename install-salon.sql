SET NAMES utf8mb4;

-- ============================================================
-- Maakit — सैलून (नाई / ब्यूटी पार्लर) वाला हिस्सा
-- phpMyAdmin → अपना database → SQL tab → यह पूरा code paste करके Go
-- (यह install.sql के बाद चलाना है, एक ही बार)
-- ============================================================

ALTER TABLE businesses
  ADD COLUMN salon_on TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN workers INT NOT NULL DEFAULT 1,
  ADD COLUMN mode ENUM('auto','busy','off') NOT NULL DEFAULT 'off',
  ADD COLUMN open_time TIME DEFAULT '08:00:00',
  ADD COLUMN close_time TIME DEFAULT '20:00:00',
  ADD COLUMN access_code VARCHAR(20) DEFAULT NULL,
  ADD COLUMN salon_updated DATETIME DEFAULT NULL;

CREATE TABLE IF NOT EXISTS services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  business_id INT NOT NULL,
  name VARCHAR(60) NOT NULL,
  price INT NOT NULL DEFAULT 0,
  minutes INT NOT NULL DEFAULT 15,
  active TINYINT(1) NOT NULL DEFAULT 1,
  INDEX (business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  business_id INT NOT NULL,
  ref VARCHAR(12) NOT NULL,
  customer_name VARCHAR(80) NOT NULL,
  mobile VARCHAR(15) NOT NULL,
  seats INT NOT NULL DEFAULT 1,
  service_text VARCHAR(255) DEFAULT NULL,
  price INT NOT NULL DEFAULT 0,
  minutes INT NOT NULL DEFAULT 15,
  status ENUM('requested','waiting','in_chair','done','no_show','cancelled') NOT NULL DEFAULT 'waiting',
  chair_no INT DEFAULT NULL,
  started_at DATETIME DEFAULT NULL,
  coming ENUM('unknown','yes','no') NOT NULL DEFAULT 'unknown',
  source ENUM('online','walkin') NOT NULL DEFAULT 'online',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (business_id), INDEX (status), INDEX (mobile), INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shop_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  business_id INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  expires DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS salon_customers (
  mobile VARCHAR(15) NOT NULL PRIMARY KEY,
  name VARCHAR(80) DEFAULT NULL,
  visits INT NOT NULL DEFAULT 0,
  no_shows INT NOT NULL DEFAULT 0,
  last_service VARCHAR(255) DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shop_login_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  business_id INT DEFAULT NULL,
  mobile VARCHAR(15) DEFAULT NULL,
  ok TINYINT(1) NOT NULL DEFAULT 0,
  ip VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (mobile), INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
