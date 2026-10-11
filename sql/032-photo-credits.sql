-- ============================================================
-- 032: Auto photos — फ़ोटो कहाँ से आई (credit) + "इसकी फ़ोटो नहीं मिली"
--
-- Admin > Auto photos मुफ़्त और क़ानूनी फ़ोटो लाइब्रेरी (Wikimedia
-- Commons, Open Food Facts) से फ़ोटो लाता है। इनकी शर्त है कि फ़ोटो
-- खींचने वाले का नाम और लाइसेंस दिखाया जाए — वही यहाँ रखा जाता है
-- और /photo-credits.php व सामान के पन्ने पर दिखता है।
--
-- दो बार चलने पर भी कुछ नहीं टूटता।
-- ============================================================

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='catalog_photos' AND COLUMN_NAME='source')=0,
  'ALTER TABLE catalog_photos ADD COLUMN source VARCHAR(20) DEFAULT NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='catalog_photos' AND COLUMN_NAME='credit')=0,
  'ALTER TABLE catalog_photos ADD COLUMN credit VARCHAR(160) DEFAULT NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='catalog_photos' AND COLUMN_NAME='license')=0,
  'ALTER TABLE catalog_photos ADD COLUMN license VARCHAR(40) DEFAULT NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='catalog_photos' AND COLUMN_NAME='source_url')=0,
  'ALTER TABLE catalog_photos ADD COLUMN source_url VARCHAR(400) DEFAULT NULL', 'DO 0');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- जिन सामान की अच्छी फ़ोटो नहीं मिली — Auto photos उन्हें दोबारा नहीं दिखाता
CREATE TABLE IF NOT EXISTS catalog_photo_skip (
  cat_id     INT NOT NULL PRIMARY KEY,
  skipped_by INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
