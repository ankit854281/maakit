-- ============================================================
-- 031 — सामान की कई फ़ोटो (स्लाइड)
--
-- Har saaman (catalog_items) ki 7 tak photo. Pehli photo home
-- page ke card par dikhti hai, baaki product page par slide hoti
-- hain. Admin > सामान की फ़ोटो se lagti hain.
--
-- Do baar chal jaye to bhi kuchh nahi tootta.
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS catalog_photos (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  cat_id     INT NOT NULL,
  file       VARCHAR(80) NOT NULL,
  sort_no    INT NOT NULL DEFAULT 0,
  added_by   INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cp_cat (cat_id, sort_no, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
