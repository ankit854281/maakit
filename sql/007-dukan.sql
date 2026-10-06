-- ============================================================
-- 007 — दुकान पैनल
--
-- दुकानदार अपना सामान, अपना दाम, अपना ऑर्डर और अपना हिसाब
-- ख़ुद चला सके — इसके लिए दो नई टेबल और कुछ नए कॉलम।
--
-- यह फ़ाइल दो बार चल जाए तो भी कुछ नहीं टूटेगा।
-- ============================================================
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1. दुकान का अपना सामान
-- ------------------------------------------------------------
-- item_id भरा हो तो यह Maakit की 165 वाली सूची से उठाया गया है
-- (दुकानदार को नाम टाइप नहीं करना पड़ा, सिर्फ़ दाम भरा)।
-- खाली हो तो दुकानदार ने अपने हाथ से जोड़ा है।
CREATE TABLE IF NOT EXISTS shop_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  business_id INT NOT NULL,
  item_id INT DEFAULT NULL,
  name VARCHAR(80) NOT NULL,
  unit VARCHAR(40) NOT NULL DEFAULT '1 पीस',
  price INT NOT NULL DEFAULT 0,
  mrp INT DEFAULT NULL,
  photo VARCHAR(120) DEFAULT NULL,
  stock ENUM('hai','khatam') NOT NULL DEFAULT 'hai',
  qty INT DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_no INT NOT NULL DEFAULT 0,
  sold INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_shop_item (business_id, name, unit),
  INDEX idx_si_biz (business_id),
  INDEX idx_si_stock (stock),
  INDEX idx_si_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. दुकान का खाता (बही)
-- ------------------------------------------------------------
-- amount रुपये में। जोड़ (+) = दुकान की कमाई,
-- घटाव (−) = दुकान से कटा (जैसे Maakit का commission)।
--
-- source = 'maakit'  → Maakit से आया ऑर्डर
-- source = 'dukaan'  → दुकान पर ही बिका (दुकानदार ने ख़ुद लिखा)
CREATE TABLE IF NOT EXISTS shop_ledger (
  id INT AUTO_INCREMENT PRIMARY KEY,
  business_id INT NOT NULL,
  order_id INT DEFAULT NULL,
  source ENUM('maakit','dukaan') NOT NULL DEFAULT 'maakit',
  kind ENUM('bikri','commission','jama','kharch') NOT NULL DEFAULT 'bikri',
  amount INT NOT NULL DEFAULT 0,
  paid_by ENUM('nagad','upi','baad') NOT NULL DEFAULT 'nagad',
  note VARCHAR(160) DEFAULT NULL,
  settled TINYINT(1) NOT NULL DEFAULT 0,
  settled_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sl_biz (business_id, created_at),
  INDEX idx_sl_settled (settled),
  INDEX idx_sl_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. businesses में नए कॉलम
-- ------------------------------------------------------------
-- shop_open      : अभी दुकान खुली है या नहीं
-- items_on       : इस दुकान का अपना सामान ग्राहक को दिखाना है या नहीं
-- upi_id         : दुकान का अपना UPI — पैसा सीधा दुकान के खाते में
-- upi_name       : QR पर जो नाम दिखेगा
-- commission_pct : Maakit का हिस्सा (0 = कुछ नहीं)
-- shop_updated   : दुकानदार ने आख़िरी बार कब कुछ बदला
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='businesses' AND COLUMN_NAME='shop_open')=0,
  'ALTER TABLE businesses ADD COLUMN shop_open TINYINT(1) NOT NULL DEFAULT 1','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='businesses' AND COLUMN_NAME='items_on')=0,
  'ALTER TABLE businesses ADD COLUMN items_on TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='businesses' AND COLUMN_NAME='upi_id')=0,
  'ALTER TABLE businesses ADD COLUMN upi_id VARCHAR(80) DEFAULT NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='businesses' AND COLUMN_NAME='upi_name')=0,
  'ALTER TABLE businesses ADD COLUMN upi_name VARCHAR(80) DEFAULT NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='businesses' AND COLUMN_NAME='commission_pct')=0,
  'ALTER TABLE businesses ADD COLUMN commission_pct DECIMAL(4,1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='businesses' AND COLUMN_NAME='shop_updated')=0,
  'ALTER TABLE businesses ADD COLUMN shop_updated DATETIME DEFAULT NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ------------------------------------------------------------
-- 4. orders में दुकान की पहचान
-- ------------------------------------------------------------
-- अब तक ऑर्डर में दुकान का नाम सिर्फ़ लिखा हुआ टेक्स्ट था।
-- business_id से ऑर्डर उस दुकान के पैनल और हिसाब से जुड़ जाता है।
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='business_id')=0,
  'ALTER TABLE orders ADD COLUMN business_id INT DEFAULT NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND INDEX_NAME='idx_o_biz')=0,
  'ALTER TABLE orders ADD INDEX idx_o_biz (business_id)','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- दुकानदार ने ऑर्डर पर क्या किया (मंज़ूर / तैयार / दे दिया)
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='shop_status')=0,
  "ALTER TABLE orders ADD COLUMN shop_status ENUM('naya','manzoor','taiyaar','diya','mana') NOT NULL DEFAULT 'naya'",'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='shop_seen_at')=0,
  'ALTER TABLE orders ADD COLUMN shop_seen_at DATETIME DEFAULT NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- ------------------------------------------------------------
-- 5. जिन दुकानों को पहले से मंज़ूरी मिली है, उन्हें कोड दे दीजिए
-- ------------------------------------------------------------
-- कोड के बिना दुकानदार लॉगिन नहीं कर पाएगा। जिनका कोड पहले से
-- है (नाई, पार्लर) उनका वैसा ही रहेगा।
UPDATE businesses
   SET access_code = UPPER(CONCAT(
         SUBSTRING('ABCDEFGHJKLMNPQRSTUVWXYZ', FLOOR(1+RAND()*24), 1),
         SUBSTRING('ABCDEFGHJKLMNPQRSTUVWXYZ', FLOOR(1+RAND()*24), 1),
         SUBSTRING('ABCDEFGHJKLMNPQRSTUVWXYZ', FLOOR(1+RAND()*24), 1),
         '-', LPAD(FLOOR(RAND()*10000), 4, '0')))
 WHERE status = 'approved' AND (access_code IS NULL OR access_code = '');
