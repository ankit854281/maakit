-- ============================================================
--  सारथी — अपना डेटाबेस
--
--  Ye Maakit ka database NAHI hai. Sarathi apna alag dhanda hai
--  aur uska apna hisaab hai. Yahan Maakit ki ek bhi table nahi
--  milegi — Maakit yahan sirf ek "client" hai, waise hi jaise
--  kal koi aur dukaan hogi.
--
--  Rishta sirf itna: client apni CHAABI se ek kaam (job) bhejta
--  hai. Sarathi use nipta kar status bata deta hai. Bas.
--
--  Paise ka niyam: Sarathi kisi ka paisa apne paas NAHI rakhta.
--  Na wallet, na gateway, na settlement. Khata sirf GINTI rakhta
--  hai — kiska kitna bana, kitna diya, kitna baaki. Doosre ka
--  paisa apne paas rakhne ke liye RBI ka licence chahiye hota
--  hai, aur wo hamare paas nahi hai.
--
--  Ye file jitni baar chalao, kuch nahi bigadta.
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
--  1. सारथी (डिलीवरी वाले)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS riders (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  mobile      VARCHAR(15)  NOT NULL,
  pass_hash   VARCHAR(255) NOT NULL,          -- login ka code
  vehicle     VARCHAR(30)  DEFAULT NULL,      -- साइकिल / बाइक / ऑटो
  rc_number   VARCHAR(30)  DEFAULT NULL,
  dl_number   VARCHAR(30)  DEFAULT NULL,
  -- ek delivery ka kitna. 0 ka matlab "abhi tay nahi" — ise
  -- "muft kaam" samajh lena sabse badi galti hogi, isliye panne
  -- par saaf laal chetavni dikhti hai.
  rate        INT NOT NULL DEFAULT 0,
  -- ek hi chakkar me agle drop ka rate (kam mehnat, kam rate).
  -- 0 rakha to har drop poore rate par ginti hoga.
  extra_rate  INT NOT NULL DEFAULT 0,
  active      TINYINT(1) NOT NULL DEFAULT 1,
  -- "main abhi kaam ke liye tayyar hoon" — iske baad tak
  ready_until DATETIME DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rider_mobile (mobile),
  INDEX (active, ready_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  2. ग्राहक कंपनियाँ — Maakit inme से सिर्फ़ एक है
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,          -- "Maakit", "अनिल मेडिकल"
  contact     VARCHAR(80)  DEFAULT NULL,
  mobile      VARCHAR(15)  DEFAULT NULL,
  -- is client se ek delivery ka kitna lete hain
  fee         INT NOT NULL DEFAULT 0,
  -- ek hi chakkar me agli delivery ka kitna (multi-stop sasta
  -- padta hai, isliye client ko bhi sasta dete hain)
  extra_fee   INT NOT NULL DEFAULT 0,
  active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  3. चाबियाँ — हर client की अपनी
--
--  Chaabi khud yahan NAHI rakhte, uska hash rakhte hain —
--  bilkul password ki tarah. Database kisi ke haath lag bhi
--  jaye to usse chaabi wapas nahi banai ja sakti.
--  prefix sirf pehchanne ke liye hai ("kaun si chaabi thi").
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS client_keys (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  client_id   INT NOT NULL,
  prefix      CHAR(8)      NOT NULL,
  key_hash    CHAR(64)     NOT NULL,          -- sha256
  note        VARCHAR(120) DEFAULT NULL,
  revoked     TINYINT(1) NOT NULL DEFAULT 0,
  last_used   DATETIME DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_prefix (prefix),
  INDEX (client_id, revoked),
  CONSTRAINT fk_key_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  4. चक्कर — एक बार निकलना, कई जगह निपटाना
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trips (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  rider_id    INT NOT NULL,
  status      ENUM('open','closed') NOT NULL DEFAULT 'open',
  started_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at   DATETIME DEFAULT NULL,
  INDEX (rider_id, status),
  CONSTRAINT fk_trip_rider FOREIGN KEY (rider_id) REFERENCES riders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  5. काम (job) — यही सारथी का असली धंधा है
--
--  client_ref: client ke apne yahan is kaam ka number. Maakit
--  ke liye wo uska order_no hoga. Ek hi client do baar wahi ref
--  na bhej de — isliye (client_id, client_ref) par ek hi hadd.
--  Isse net atakne par dobara bhejne se do kaam nahi bante.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jobs (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  client_id     INT NOT NULL,
  client_ref    VARCHAR(40) NOT NULL,
  job_no        VARCHAR(20) NOT NULL,

  -- kahan se uthana hai
  pick_name     VARCHAR(120) DEFAULT NULL,
  pick_mobile   VARCHAR(15)  DEFAULT NULL,
  pick_address  VARCHAR(250) DEFAULT NULL,

  -- kahan pahunchana hai
  drop_name     VARCHAR(80)  NOT NULL,
  drop_mobile   VARCHAR(15)  NOT NULL,
  drop_address  VARCHAR(250) NOT NULL,
  drop_village  VARCHAR(80)  DEFAULT NULL,

  items         TEXT DEFAULT NULL,
  note          TEXT DEFAULT NULL,

  -- sabooti ke do code. pick_otp dukaan/bhejne wala bataata hai,
  -- drop_code graahak bataata hai. Dono 4 ank ke — gaon me chaar
  -- ank bolkar batana aasaan hai.
  pick_otp      CHAR(4) NOT NULL,
  drop_code     CHAR(4) NOT NULL,
  pod_photo     VARCHAR(120) DEFAULT NULL,

  -- paisa: ye sirf Sarathi ki apni kamai hai (delivery ka).
  -- Saaman ka paisa Sarathi ko chhoota bhi nahi — wo graahak
  -- seedha dukaan ko deta hai.
  fee           INT NOT NULL DEFAULT 0,
  cod_amount    INT DEFAULT NULL,             -- rider ne nagad liya ho to ginti ke liye

  status        ENUM('new','assigned','picked','delivered','cancelled')
                NOT NULL DEFAULT 'new',
  rider_id      INT DEFAULT NULL,
  trip_id       INT DEFAULT NULL,
  trip_seq      INT NOT NULL DEFAULT 0,

  assigned_at   DATETIME DEFAULT NULL,
  picked_at     DATETIME DEFAULT NULL,
  delivered_at  DATETIME DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_client_ref (client_id, client_ref),
  UNIQUE KEY uq_job_no (job_no),
  INDEX (status, created_at),
  INDEX (rider_id, status),
  INDEX (trip_id, trip_seq),
  CONSTRAINT fk_job_client FOREIGN KEY (client_id) REFERENCES clients(id),
  CONSTRAINT fk_job_rider  FOREIGN KEY (rider_id)  REFERENCES riders(id),
  CONSTRAINT fk_job_trip   FOREIGN KEY (trip_id)   REFERENCES trips(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  6. खाता — सिर्फ़ वो पैसा जो आपने सारथी को दिया
--
--  Kamai alag se gini jaati hai (poore hue kaam x rate).
--  Yahan sirf diya hua paisa likha jaata hai.
--  Baaki = kamai - diya. Koi "balance" wala khana nahi hai,
--  aur jaan-boojh kar nahi hai.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS khata (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  rider_id    INT NOT NULL,
  amount      INT NOT NULL,
  paid_on     DATE NOT NULL,
  how         VARCHAR(20) DEFAULT NULL,       -- नगद / UPI / बैंक
  note        VARCHAR(200) DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (rider_id, paid_on),
  CONSTRAINT fk_khata_rider FOREIGN KEY (rider_id) REFERENCES riders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  7. समस्या — "ये काम नहीं हो पा रहा"
--
--  Raste me sabse zyada yahi hota hai: graahak ghar par nahi,
--  dukaan band, pata galat. Rider yahan likh deta hai; kaam
--  RADD nahi hota — radd karna maalik ka faisla hai.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS problems (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  job_id      INT NOT NULL,
  rider_id    INT NOT NULL,
  kind        VARCHAR(30)  NOT NULL,
  note        VARCHAR(300) DEFAULT NULL,
  settled     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (settled, created_at),
  INDEX (job_id),
  CONSTRAINT fk_prob_job   FOREIGN KEY (job_id)   REFERENCES jobs(id),
  CONSTRAINT fk_prob_rider FOREIGN KEY (rider_id) REFERENCES riders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  8. लॉगिन की कोशिशें — बार-बार कोड आज़माने से बचाव
--
--  Ginti database me rehti hai, session me nahi. Session me
--  rakhne par cookie mitate hi ginti shunya ho jaati hai, aur
--  phir koi hazaar code aazma sakta hai.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_tries (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  who         VARCHAR(64) NOT NULL,           -- mobile ka hash
  ip          VARCHAR(45) DEFAULT NULL,
  ok          TINYINT(1) NOT NULL DEFAULT 0,
  tried_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (who, tried_at),
  INDEX (ip, tried_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  9. सेटिंग
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(40) NOT NULL PRIMARY KEY,
  v VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (k, v) VALUES
  ('business_name', 'Sarathi Delivery'),
  ('phone',         '8429393903'),
  -- doori ke hisaab se kiraya. Naya shahar = sirf ye line badlo.
  -- Google ka koi bill nahi.
  ('fare_slab',     '0-3:35,3-7:55,7-15:85,15-30:140');
