SET NAMES utf8mb4;
-- ============================================================
-- 038 — School ERP: sabka pehla PIN 1234 (owner, 16 Oct 2026)
--
-- Owner ne kaha: "sabka password 1234 kar do, log khud badal
-- lenge". Isliye:
--   - must_change: 1 = is aadmi ne abhi 1234 se login kiya hai,
--     apna PIN chunne tak ERP ka koi kaam nahi khulega.
--   - Teacher, Chairman aur Parent ke abhi tak lage PIN hata diye
--     jaate hain, taaki sab 1234 se shuru karein. Principal ka PIN
--     nahi chhoota.
--
-- DHYAN: is file ko kabhi BADALIYE MAT. Updater badli hui file
-- dobara chalata hai — aur tab sabke PIN phir se 1234 ho jayenge.
-- ============================================================
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sch_users' AND COLUMN_NAME='must_change')=0,
  'ALTER TABLE sch_users ADD COLUMN must_change TINYINT(1) NOT NULL DEFAULT 0', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE sch_users SET pin_hash = NULL, fails = 0, strikes = 0, locked_until = NULL, must_change = 0
 WHERE role IN ('teacher', 'chairman', 'parent');
DELETE FROM sch_sessions WHERE uid <> 'principal';
