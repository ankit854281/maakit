SET NAMES utf8mb4;

-- ============================================================
--  MAAKIT — BRAND aur SEEKHA HUA DAAM  (v7)
--
--  Soch: Maakit stock nahi rakhta, isliye daam "tay" nahi kar
--  sakta. Par har order ka asli bill aata hai. To daam hath se
--  likhne ke bajaye, website har bill se KHUD SEEKHEGI.
--
--  BPO order band karte waqt saaman ka asli daam likh deta hai,
--  aur agli baar customer ko dikhta hai "pichhli baar itna laga".
--
--  Ye file do baar chalne par bhi kuch nahi bigadti.
-- ============================================================


-- ---------- 1. Brand ki tay list ----------
-- Khulla likhne ka khana jaan-bujhkar nahi diya. Warna ek hi
-- brand teen tarah se likha jayega — "आशीर्वाद", "Aashirvaad",
-- "ashirwad" — aur chhantna kaam karna band kar dega.
CREATE TABLE IF NOT EXISTS brands (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  name     VARCHAR(60) NOT NULL,              -- आशीर्वाद
  name_en  VARCHAR(60) DEFAULT NULL,          -- Aashirvaad
  kind     ENUM('company','local') NOT NULL DEFAULT 'company',
  sort_no  INT NOT NULL DEFAULT 0,
  active   TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_brand (name),
  INDEX (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------- 2. Kaun sa brand kis saaman me milta hai ----------
CREATE TABLE IF NOT EXISTS item_brands (
  item_id  INT NOT NULL,
  brand_id INT NOT NULL,
  sort_no  INT NOT NULL DEFAULT 0,
  PRIMARY KEY (item_id, brand_id),
  INDEX (brand_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------- 3. Asli bill se seekha hua daam ----------
-- Har line ek asli kharid hai. Kuch mitaya nahi jata, isliye
-- daam ka poora itihaas bana rehta hai.
CREATE TABLE IF NOT EXISTS item_prices (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  item_id   INT NOT NULL,
  brand_id  INT DEFAULT NULL,                 -- NULL = brand pata nahi / koi bhi
  market    VARCHAR(20) DEFAULT NULL,         -- kapsethi / chauri / kachhawa
  shop      VARCHAR(120) DEFAULT NULL,        -- kaun si dukaan
  price     INT NOT NULL,                     -- ek naap ka daam (₹)
  qty       INT NOT NULL DEFAULT 1,           -- kitne liye the
  order_id  INT DEFAULT NULL,                 -- kis order se aaya
  by_user   INT DEFAULT NULL,                 -- kisne likha
  note      VARCHAR(120) DEFAULT NULL,
  ok        TINYINT(1) NOT NULL DEFAULT 1,    -- admin galat maan kar 0 kar sakta hai
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (item_id, created_at),
  INDEX (item_id, brand_id),
  INDEX (order_id),
  INDEX (ok)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------- 4. orders me nishaan — daam likha ja chuka? ----------
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='priced_at')=0,'ALTER TABLE orders ADD COLUMN priced_at DATETIME NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;


-- ---------- 5. items me "khana" alag pehchanne ke liye ----------
-- grp pehle se hai, par bade hisse (raashan / khana) ke liye alag khana
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='items' AND COLUMN_NAME='section')=0,'ALTER TABLE items ADD COLUMN section VARCHAR(12) NOT NULL DEFAULT ''saaman''','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='items' AND INDEX_NAME='idx_section')=0,'ALTER TABLE items ADD INDEX idx_section (section)','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- bana khana aur mithai alag hisse me
UPDATE items SET section='khana' WHERE grp IN ('khana','mithai','peene');


-- ---------- 7. Nikala hua daam (taaki har baar hisaab na lagana pade) ----------
-- item_prices me har asli kharid padi rehti hai (itihaas).
-- Yahan uska nichod rehta hai, jo customer ko dikhta hai.
-- brand_id = 0 ka matlab "sab brand milakar".
CREATE TABLE IF NOT EXISTS item_daam (
  item_id   INT NOT NULL,
  brand_id  INT NOT NULL DEFAULT 0,
  price     INT NOT NULL,                  -- beech ka daam
  low       INT NOT NULL,
  high      INT NOT NULL,
  n         INT NOT NULL DEFAULT 0,        -- kitni kharid se nikla
  last_at   DATETIME DEFAULT NULL,         -- sabse nayi kharid kab thi
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (item_id, brand_id),
  INDEX (last_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------- 6. Shuruati brand ----------
-- Jo gaav ki dukaan par sach me milte hain. Aur jodna ho to
-- Admin -> ब्रांड se jodiye.
INSERT IGNORE INTO brands (name, name_en, kind, sort_no) VALUES
('लोकल / खुला',   'Local / loose', 'local',   1),
('आशीर्वाद',      'Aashirvaad',    'company', 10),
('पतंजलि',        'Patanjali',     'company', 11),
('फॉर्च्यून',      'Fortune',       'company', 12),
('टाटा',          'Tata',          'company', 13),
('अमूल',          'Amul',          'company', 14),
('पारले',         'Parle',         'company', 15),
('ब्रिटानिया',     'Britannia',     'company', 16),
('सनराइज़',        'Sunrise',       'company', 17),
('एवरेस्ट',        'Everest',       'company', 18),
('एमडीएच',        'MDH',           'company', 19),
('धारा',          'Dhara',         'company', 20),
('सफ़ोला',         'Saffola',       'company', 21),
('मदर डेयरी',     'Mother Dairy',  'company', 22),
('नेस्ले',         'Nestle',        'company', 23),
('सर्फ़ एक्सेल',    'Surf Excel',    'company', 24),
('रिन',           'Rin',           'company', 25),
('लाइफ़बॉय',       'Lifebuoy',      'company', 26),
('कोलगेट',        'Colgate',       'company', 27),
('डाबर',          'Dabur',         'company', 28);
