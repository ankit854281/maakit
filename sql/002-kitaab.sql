SET NAMES utf8mb4;

-- ============================================================
--  MAAKIT — PURANI KITAAB WALA HISSA  (v6)
--
--  Kahan chalaiye:
--  phpMyAdmin -> baayen apna database (maakit.in) dabaiye
--  -> upar Import -> Choose File -> ye file -> neeche Go
--
--  * Purane database par chalaiye, kuch mitega nahi.
--  * Do baar chala diya to bhi koi nuksan nahi.
-- ============================================================

CREATE TABLE IF NOT EXISTS books (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  kind          ENUM('bech','muft','badal') NOT NULL DEFAULT 'bech',  -- bechna / muft dena / badalna
  title         VARCHAR(160) NOT NULL,
  author        VARCHAR(120) DEFAULT NULL,
  grp           VARCHAR(20)  NOT NULL DEFAULT 'anya',  -- school / college / compete / novel / dharm / bachche / anya
  class_sub     VARCHAR(80)  DEFAULT NULL,             -- "Class 10 — विज्ञान"
  lang          VARCHAR(10)  NOT NULL DEFAULT 'hi',    -- hi / en / other
  halat         ENUM('naya','theek','purana') NOT NULL DEFAULT 'theek',
  price         INT DEFAULT NULL,                      -- sirf bechna me
  want          VARCHAR(160) DEFAULT NULL,             -- sirf badalna me — kiske badle chahiye
  note          VARCHAR(400) DEFAULT NULL,
  photo         VARCHAR(120) DEFAULT NULL,
  photo2        VARCHAR(120) DEFAULT NULL,

  seller_name   VARCHAR(80) NOT NULL,
  seller_mobile VARCHAR(15) NOT NULL,
  village       VARCHAR(80) DEFAULT NULL,
  landmark      VARCHAR(120) DEFAULT NULL,
  customer_id   INT DEFAULT NULL,
  manage_code   VARCHAR(10) NOT NULL,                  -- apni kitaab hataane ke liye

  status        ENUM('live','sold','hidden') NOT NULL DEFAULT 'live',
  views         INT NOT NULL DEFAULT 0,
  asks          INT NOT NULL DEFAULT 0,                -- kitni baar mangwai gayi
  reports       INT NOT NULL DEFAULT 0,
  report_note   VARCHAR(300) DEFAULT NULL,

  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (status), INDEX (kind), INDEX (grp), INDEX (village),
  INDEX (seller_mobile), INDEX (created_at), INDEX (reports)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- orders me kitaab ki pehchaan (kaun si kitaab ka order hai)
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='book_id')=0,'ALTER TABLE orders ADD COLUMN book_id INT NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- agar koi purana adhoora books table ho to naye khane jod do
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='books' AND COLUMN_NAME='photo2')=0,'ALTER TABLE books ADD COLUMN photo2 VARCHAR(120) NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='books' AND COLUMN_NAME='asks')=0,'ALTER TABLE books ADD COLUMN asks INT NOT NULL DEFAULT 0','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='books' AND COLUMN_NAME='report_note')=0,'ALTER TABLE books ADD COLUMN report_note VARCHAR(300) NULL','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- kitaab ki delivery ka shuruati charge (admin -> समय/छुट्टी me badal sakte hain)
INSERT IGNORE INTO settings (k, v) VALUES ('book_charge_from', '30');
