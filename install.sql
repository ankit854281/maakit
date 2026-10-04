SET NAMES utf8mb4;

-- ============================================================
-- Maakit — database taiyar karne wala code
-- phpMyAdmin me apna database chunkar, SQL tab me ye poora
-- code paste karke "Go" dabaiye. Ek hi baar chalana hai.
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  username VARCHAR(40) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','bpo','delivery') NOT NULL DEFAULT 'bpo',
  mobile VARCHAR(15) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS villages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE,
  rate_kapsethi INT NOT NULL DEFAULT 0,
  rate_chauri INT NOT NULL DEFAULT 0,
  rate_kachhawa INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(20) NOT NULL,
  code VARCHAR(6) NOT NULL,
  source ENUM('website','call','whatsapp') NOT NULL DEFAULT 'website',
  customer_name VARCHAR(80) NOT NULL,
  mobile VARCHAR(15) NOT NULL,
  village VARCHAR(60) NOT NULL,
  landmark VARCHAR(120) DEFAULT NULL,
  items TEXT NOT NULL,
  shop VARCHAR(120) DEFAULT NULL,
  market VARCHAR(20) DEFAULT 'kapsethi',
  sector VARCHAR(30) DEFAULT NULL,
  weight_extra VARCHAR(10) DEFAULT '0',
  size_extra VARCHAR(10) DEFAULT '0',
  first_order TINYINT(1) NOT NULL DEFAULT 0,
  delivery_charge INT DEFAULT NULL,
  goods_amount INT DEFAULT NULL,
  payment VARCHAR(40) DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Naya',
  delivery_user INT DEFAULT NULL,
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (status), INDEX (created_at), INDEX (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS businesses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  owner VARCHAR(80) DEFAULT NULL,
  category VARCHAR(30) NOT NULL,
  work VARCHAR(255) DEFAULT NULL,
  mobile VARCHAR(15) NOT NULL,
  village VARCHAR(80) DEFAULT NULL,
  address VARCHAR(200) DEFAULT NULL,
  about TEXT,
  photo VARCHAR(120) DEFAULT NULL,
  password VARCHAR(255) DEFAULT NULL,
  token_enabled TINYINT(1) NOT NULL DEFAULT 0,
  queue_open TINYINT(1) NOT NULL DEFAULT 0,
  now_serving INT NOT NULL DEFAULT 0,
  last_token INT NOT NULL DEFAULT 0,
  avg_minutes INT NOT NULL DEFAULT 10,
  queue_updated DATETIME DEFAULT NULL,
  status ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (category), INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  business_id INT NOT NULL,
  name VARCHAR(80) NOT NULL,
  village VARCHAR(80) DEFAULT NULL,
  rating TINYINT NOT NULL DEFAULT 5,
  comment VARCHAR(300) DEFAULT NULL,
  status ENUM('pending','approved') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (business_id), INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  business_id INT NOT NULL,
  token_no INT NOT NULL,
  name VARCHAR(80) NOT NULL,
  mobile VARCHAR(15) NOT NULL,
  status ENUM('waiting','done','missed') NOT NULL DEFAULT 'waiting',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (business_id), INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Shuruati jaankari ----------

-- Admin login: username = admin, password = maakit123
-- (login karte hi password badal lijiye)
INSERT IGNORE INTO users (name, username, password, role, mobile) VALUES
('Ankit (Admin)', 'admin', '$2y$10$wcBuVjGlNHqomXHGBkPIWOdTdey6/28DNFGefyza6uzRnbVRxp5VC', 'admin', '8429393903');

INSERT IGNORE INTO villages (name, rate_kapsethi, rate_chauri, rate_kachhawa) VALUES
('बरवा', 50, 50, 75),
('भगवानपुर', 50, 50, 75),
('सुरहन', 50, 40, 85),
('सवारपुर', 60, 40, 85),
('टिकैतपुर', 50, 40, 85),
('नहवानीपुर', 40, 60, 75),
('डबेथुआ', 40, 50, 85),
('झौआ', 50, 60, 60),
('पिलखनी', 60, 60, 75),
('गजेपुर', 50, 75, 85),
('कंधिया', 50, 30, 85),
('अमवा', 50, 40, 85),
('गोविंदपुर', 60, 85, 85),
('लठिया', 0, 0, 0);
