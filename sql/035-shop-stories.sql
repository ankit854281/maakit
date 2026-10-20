-- ============================================================
--  दुकान की कहानी (story)
--
--  Gaon me khabar vigyapan se nahi, dekhne se phailti hai. Jo
--  dukaandaar roz apne WhatsApp Status par aaj ka maal daalta
--  hai, uska saamaan bikta hai. Par Status sirf uske apne
--  number walon tak jata hai.
--
--  Ye wahi cheez Maakit par le aata hai: ek photo, ek line,
--  24 ghante. Jo bhi Maakit khole, use dikhega -- aur neeche
--  seedha "मँगाइए" ka button hoga.
--
--  Teen baatein jaan-boojh kar aisi rakhi gayi hain:
--
--  1. EK PHOTO, EK LINE. Dukaandaar ke paas lamba likhne ka
--     waqt nahi hai aur na hi aadat. Jitna kam maangenge,
--     utna zyada log daalenge.
--
--  2. 24 GHANTE ME APNE AAP CHALI JATI HAI. "Aaj ka maal"
--     kal jhooth ban jata hai. Purani story dikhte rehna
--     bharose ko kaatta hai -- isliye wo khud hat jati hai.
--     Row rehti hai (hisaab ke liye), dikhna band ho jata hai.
--
--  3. MAALIK KABHI BHI CHHUPA SAKTA HAI (hidden=1). Dukaan
--     ki photo Maakit ke panne par dikhti hai, isliye uski
--     zimmedari bhi Maakit par aati hai.
--
--  Ye file jitni baar chalao, kuch nahi bigadta.
-- ============================================================

CREATE TABLE IF NOT EXISTS shop_stories (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  business_id  INT NOT NULL,
  photo        VARCHAR(120) DEFAULT NULL,   -- uploads/ me, chhoti ki hui
  -- Ek line. 140 isliye ki dukaandaar sochkar likhe, bhashan na de.
  baat         VARCHAR(140) DEFAULT NULL,
  -- Is story ko dekh kar kitne order aaye. Inaam ISI par dena
  -- chahiye -- story daalne par nahi (warna kachra bharega).
  orders       INT NOT NULL DEFAULT 0,
  views        INT NOT NULL DEFAULT 0,
  hidden       TINYINT(1) NOT NULL DEFAULT 0,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- Kab tak dikhegi. Default 24 ghante, par column me isliye hai
  -- ki tyohar par 48 ghante bhi kiya ja sake.
  dikhe_tak    DATETIME NOT NULL,
  INDEX (business_id, created_at),
  INDEX (hidden, dikhe_tak)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dukaan kitni story daal sakti hai ek din me. Bina hadd ke ek
-- dukaan poora panna bhar degi aur baaki ki koi nahi dikhegi.
INSERT IGNORE INTO settings (k, v) VALUES
  ('story_per_din',  '3'),
  ('story_ghante',  '24');
