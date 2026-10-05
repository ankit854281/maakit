-- ============================================================
-- 004 — "designer" naam ka naya role
--
-- Ankit ki baat: sab kuchh admin ke login me mat daaliye. Jiska
-- jo kaam hai, use utna hi dikhe. Designer ka kaam hai ऑफ़र/बैनर,
-- सामान की फ़ोटो aur sewa ke dibbon ki photo — order, hisaab,
-- rate aur team usko nahi dikhega.
--
-- Ye file dobara chalane par bhi kuchh nahi bigadti.
-- ============================================================

-- role ki soochi me 'designer' jodiye (agar pehle se na ho)
SET @q := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'users'
       AND COLUMN_NAME  = 'role'
       AND COLUMN_TYPE LIKE '%designer%') = 0,
  "ALTER TABLE users MODIFY COLUMN role ENUM('admin','bpo','delivery','designer') NOT NULL DEFAULT 'bpo'",
  'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
