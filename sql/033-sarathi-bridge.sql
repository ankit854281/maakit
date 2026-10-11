-- ============================================================
-- 033: Maakit ka order → Smart Sarathi ka kaam (bridge)
--
-- Order Smart Sarathi ko bhejte hi wahan ka kaam number yahan
-- likha jata hai, aur wahan ki halat (rider mila / uthaya /
-- pahuncha) yahan utar aati hai. Do baar chalne par bhi theek.
-- ============================================================
SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='sarathi_job')=0,
  'ALTER TABLE orders ADD COLUMN sarathi_job VARCHAR(20) DEFAULT NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='sarathi_status')=0,
  'ALTER TABLE orders ADD COLUMN sarathi_status VARCHAR(20) DEFAULT NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='sarathi_checked')=0,
  'ALTER TABLE orders ADD COLUMN sarathi_checked DATETIME DEFAULT NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
