-- ============================================================
--  सारथी — डिलीवरी वाले का अपना हिस्सा
--
--  Ye migration teen cheez jodti hai:
--
--   1. Pick OTP aur Drop OTP — dukaan se saaman uthate waqt
--      aur graahak ko dete waqt ka sabooti. Dukaan isi ke bina
--      bharosa nahi karti ("saaman uthaya tha ya nahi?").
--   2. Delivery ki photo (POD) — "mila hi nahi" ka jawab.
--   3. Ek chakkar me kai drop (trip) — ek rider ek baar nikle
--      aur 5-6 jagah nipta de. Isi se per-order kharch girta hai.
--
--  Aur khata — rider ko kitna bana, kitna diya, kitna baaki.
--  Dhyaan rahe: Maakit kisi ka paisa apne paas NAHI rakhta.
--  Ye khata sirf GINTI rakhta hai. Paisa aap apne khate se
--  seedhe rider ke khate me bhejte hain. Wallet nahi hai, aur
--  jaan-boojh kar nahi hai — doosre ka paisa apne paas rakhne
--  ke liye RBI ka licence chahiye hota hai.
--
--  Ye file do baar chalane par bhi kuch nahi todegi.
-- ============================================================

SET NAMES utf8mb4;

-- ---------- 1. एक चक्कर (trip) = एक बार निकलना, कई जगह ----------
CREATE TABLE IF NOT EXISTS sarathi_trips (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  rider_user  INT NOT NULL,
  status      ENUM('open','closed') NOT NULL DEFAULT 'open',
  started_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at   DATETIME DEFAULT NULL,
  note        VARCHAR(200) DEFAULT NULL,
  INDEX (rider_user, status),
  INDEX (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- 2. खाता — सिर्फ़ वो पैसा जो आपने राइडर को दिया ----------
-- Kamai alag se gini jaati hai (poori hui delivery × uska rate).
-- Yahan sirf diya hua paisa likha jaata hai. Baaki = kamai − diya.
CREATE TABLE IF NOT EXISTS sarathi_khata (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  rider_user  INT NOT NULL,
  amount      INT NOT NULL,
  paid_on     DATE NOT NULL,
  how         VARCHAR(30) DEFAULT NULL,      -- nagad / UPI / bank
  note        VARCHAR(200) DEFAULT NULL,
  created_by  INT DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (rider_user, paid_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- 2क. समस्या — "ये काम नहीं हो पा रहा" ----------
-- Raste me sabse zyada yahi hota hai: graahak ghar par nahi,
-- dukaan band, pata galat, gaadi kharab. Pehle rider ke paas
-- batane ka koi rasta hi nahi tha — wo phone karta ya atak jata.
-- Ab wo yahan likh deta hai aur Maakit ko turant dikh jata hai.
CREATE TABLE IF NOT EXISTS sarathi_samasya (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  order_id    INT NOT NULL,
  rider_user  INT NOT NULL,
  kism        VARCHAR(30) NOT NULL,
  note        VARCHAR(300) DEFAULT NULL,
  nipta       TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (order_id), INDEX (nipta, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- 3. orders me naye khaane ----------
-- Har ALTER pehle poochhta hai ki khaana pehle se hai kya.
-- Isliye ye file jitni baar chalao, kuch nahi bigadta.

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
                  AND COLUMN_NAME = 'pick_otp') = 0,
  'ALTER TABLE orders ADD COLUMN pick_otp VARCHAR(6) DEFAULT NULL',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Drop OTP ka naya khaana JAAN-BOOJH KAR nahi banaya gaya.
-- orders.code pehle se graahak ka delivery code hai — wo order.php
-- par graahak ko dikhta hai aur delivery/index.php usi se delivery
-- pakki karta hai. Doosra OTP banane se do code ho jate, aur ek din
-- dono alag ho jate. Jo pehle se hai, usi ka istemaal.

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
                  AND COLUMN_NAME = 'picked_at') = 0,
  'ALTER TABLE orders ADD COLUMN picked_at DATETIME DEFAULT NULL',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
                  AND COLUMN_NAME = 'delivered_at') = 0,
  'ALTER TABLE orders ADD COLUMN delivered_at DATETIME DEFAULT NULL',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
                  AND COLUMN_NAME = 'pod_photo') = 0,
  'ALTER TABLE orders ADD COLUMN pod_photo VARCHAR(120) DEFAULT NULL',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
                  AND COLUMN_NAME = 'trip_id') = 0,
  'ALTER TABLE orders ADD COLUMN trip_id INT DEFAULT NULL',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
                  AND COLUMN_NAME = 'trip_seq') = 0,
  'ALTER TABLE orders ADD COLUMN trip_seq INT NOT NULL DEFAULT 0',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
                  AND INDEX_NAME = 'idx_orders_trip') = 0,
  'ALTER TABLE orders ADD INDEX idx_orders_trip (trip_id, trip_seq)',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------- 4. हर राइडर का अपना रेट (एक डिलीवरी का कितना) ----------
-- 0 ka matlab "abhi tay nahi hua" — khate me saaf likha dikhega,
-- kyunki 0 ko "muft kaam" samajh lena sabse badi galti hogi.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
                  AND COLUMN_NAME = 'rider_rate') = 0,
  'ALTER TABLE users ADD COLUMN rider_rate INT NOT NULL DEFAULT 0',
  'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------- 5. दूरी के हिसाब से किराया — हर शहर में चलने वाला ----------
-- Google ka bill nahi. Seedha slab. Naya shahar = sirf ye badlo.
INSERT IGNORE INTO settings (k, v) VALUES
  ('fare_slab',       '0-3:35,3-7:55,7-15:85,15-30:140'),
  ('rider_rate_def',  '0'),
  -- Ek hi chakkar me doosra-teesra drop rider ko kam mehnat ka padta
  -- hai, isliye uska rate alag. Isi se ek order ka kharch girta hai.
  ('multidrop_rate',  '10');
