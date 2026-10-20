-- ============================================================
--  किसने किया — काम पर नाम
--
--  Ankit ne kaha: "jo staff work karenge unka update ho, verify ho".
--
--  Verify ka matlab ye NAHI hai ki staff aakar bataye "maine aaj
--  paanch dukaan jodi". Wo to koi bhi keh sakta hai.
--
--  Verify ka matlab ye hai ki SYSTEM KHUD GINE. Dukaan sach me
--  judi ya nahi -- ye database me likha hai, kisi ke kehne par
--  nahi. Isliye gin kar dikhane ke liye bas itna chahiye ki har
--  kaam par ye likha ho ki KISNE kiya.
--
--  Abhi ye likha hi nahi jata tha:
--    - order par "call se aaya" likha tha, par kis staff ne daala
--      ye nahi
--    - dukaan par koi nishan hi nahi tha
--
--  Ye file wahi do khaane jodti hai. Purane record me ye khali
--  rahenge -- wo theek hai, unke liye koi jhootha naam nahi
--  bharna chahiye.
--
--  Ye file jitni baar chalao, kuch nahi bigadta.
-- ============================================================

-- ---- order kis staff ne daala ----
-- users.id -> BPO ya admin jisne bpo/new.php se order banaya.
-- Graahak ne khud website se daala to ye khali rehta hai (aur
-- rehna bhi chahiye -- wo kisi staff ka kaam nahi hai).
SET @q := (SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'orders'
              AND column_name = 'staff_user');
SET @s := IF(@q = 0,
  'ALTER TABLE orders ADD COLUMN staff_user INT DEFAULT NULL, ADD INDEX idx_orders_staff (staff_user, created_at)',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---- dukaan kisne jodi ----
-- users.id -> field executive ya admin. Dukaandaar ne khud
-- register-business.php se bheja to khali -- wo bhi sach hai.
SET @q := (SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'businesses'
              AND column_name = 'added_by');
SET @s := IF(@q = 0,
  'ALTER TABLE businesses ADD COLUMN added_by INT DEFAULT NULL, ADD INDEX idx_biz_added (added_by, created_at)',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---- dukaan ko kisne manzoori di ----
-- Ye alag cheez hai. Jodna field ka kaam hai, manzoori maalik ka.
-- Dono ek hi aadmi nahi hone chahiye -- warna jodne wala khud hi
-- apni jodi hui dukaan pass kar dega.
SET @q := (SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'businesses'
              AND column_name = 'approved_by');
SET @s := IF(@q = 0,
  'ALTER TABLE businesses ADD COLUMN approved_by INT DEFAULT NULL, ADD COLUMN approved_at DATETIME DEFAULT NULL',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
