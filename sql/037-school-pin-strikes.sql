SET NAMES utf8mb4;
-- ============================================================
-- 037 — School ERP: 4 ankon ka PIN, par galat koshish ki hadd
--
-- PIN ab 4 se 8 ank ka ho sakta hai. Chhota PIN bhi surakshit
-- rahe, isliye "strikes" — kul kitni baar galat PIN daala gaya.
-- 15 baar galat hone par wo login band ho jata hai, jab tak
-- Principal naya PIN na lagayein (Principal khud update-key se).
-- Sahi login par ginti phir 0. Do baar chalne par bhi safe.
-- ============================================================
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sch_users' AND COLUMN_NAME='strikes')=0,
  'ALTER TABLE sch_users ADD COLUMN strikes INT NOT NULL DEFAULT 0', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
