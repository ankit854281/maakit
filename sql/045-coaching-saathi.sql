SET NAMES utf8mb4;
-- ============================================================
-- Coaching Saathi (maakit.in/coaching) — V1
-- Alag product, "powered by Maakit". Sirf cs_ tables. Maakit ki
-- kisi table ko nahi chhoota.
-- Har table me coaching_id hai; har query inc.php ke helpers se
-- session wali coaching_id se hi chalti hai (request se kabhi nahi).
-- cs_ledger aur cs_receipt se koi row DELETE nahi hoti — galti par
-- ulti (correcting) entry banti hai.
-- ============================================================

CREATE TABLE IF NOT EXISTS cs_coaching (
 id INT AUTO_INCREMENT PRIMARY KEY,
 slug VARCHAR(40) NOT NULL,
 naam VARCHAR(120) NOT NULL,
 ctype VARCHAR(20) NOT NULL DEFAULT 'tuition',
 phone VARCHAR(10) NOT NULL,
 city VARCHAR(60) NOT NULL DEFAULT '',
 address VARCHAR(255) NOT NULL DEFAULT '',
 reg_no VARCHAR(60) NOT NULL DEFAULT '',
 color VARCHAR(7) NOT NULL DEFAULT '#0C831F',
 about TEXT NULL,
 timing VARCHAR(120) NOT NULL DEFAULT '',
 receipt_seq INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY slug_u (slug)
);

-- Ek mobile kai coaching me ho sakta hai (teacher do jagah padhaye):
-- isliye (coaching_id, mobile) ek row = ek coaching ka login.
CREATE TABLE IF NOT EXISTS cs_user (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 mobile VARCHAR(10) NOT NULL,
 naam VARCHAR(80) NOT NULL,
 role VARCHAR(10) NOT NULL DEFAULT 'teacher',
 active TINYINT NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY cm_u (coaching_id, mobile),
 KEY mobile_i (mobile)
);

CREATE TABLE IF NOT EXISTS cs_student (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 naam VARCHAR(80) NOT NULL,
 father VARCHAR(80) NOT NULL DEFAULT '',
 mobile VARCHAR(10) NOT NULL,
 cls VARCHAR(30) NOT NULL DEFAULT '',
 status VARCHAR(10) NOT NULL DEFAULT 'active',
 join_date DATE NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY c_i (coaching_id, status),
 KEY mobile_i (mobile)
);

CREATE TABLE IF NOT EXISTS cs_course (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 naam VARCHAR(80) NOT NULL,
 fee_type VARCHAR(12) NOT NULL DEFAULT 'monthly',
 amount INT NOT NULL DEFAULT 0,
 months INT NOT NULL DEFAULT 0,
 active TINYINT NOT NULL DEFAULT 1,
 KEY c_i (coaching_id)
);

CREATE TABLE IF NOT EXISTS cs_batch (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 course_id INT NOT NULL,
 naam VARCHAR(80) NOT NULL,
 timing VARCHAR(60) NOT NULL DEFAULT '',
 teacher_id INT NULL,
 seat_limit INT NOT NULL DEFAULT 0,
 active TINYINT NOT NULL DEFAULT 1,
 KEY c_i (coaching_id)
);

CREATE TABLE IF NOT EXISTS cs_enroll (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 student_id INT NOT NULL,
 batch_id INT NOT NULL,
 fee INT NOT NULL DEFAULT 0,
 fee_note VARCHAR(120) NOT NULL DEFAULT '',
 start_date DATE NOT NULL,
 status VARCHAR(10) NOT NULL DEFAULT 'active',
 KEY c_i (coaching_id, status),
 KEY s_i (student_id)
);

-- Khata: kind = due (fees judi) | pay (paisa mila) | waive (maaf) | fix (sudhaar, +/-)
-- Baaki = SUM(due) + SUM(fix) - SUM(pay) - SUM(waive). Minus = advance jama.
-- due_key "e<enroll>-<YYYY-MM>" se ek mahine ki fees do baar nahi judti.
CREATE TABLE IF NOT EXISTS cs_ledger (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 student_id INT NOT NULL,
 enroll_id INT NULL,
 kind VARCHAR(6) NOT NULL,
 month CHAR(7) NULL,
 amount INT NOT NULL,
 note VARCHAR(160) NOT NULL DEFAULT '',
 receipt_id INT NULL,
 due_key VARCHAR(40) NULL,
 by_user INT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY due_u (due_key),
 KEY cs_i (coaching_id, student_id)
);

CREATE TABLE IF NOT EXISTS cs_receipt (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 student_id INT NOT NULL,
 no INT NOT NULL,
 amount INT NOT NULL,
 mode VARCHAR(6) NOT NULL DEFAULT 'cash',
 paid_on DATE NOT NULL,
 token CHAR(24) NOT NULL,
 nonce CHAR(16) NULL,
 cancelled TINYINT NOT NULL DEFAULT 0,
 cancel_reason VARCHAR(160) NOT NULL DEFAULT '',
 by_user INT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY no_u (coaching_id, no),
 UNIQUE KEY token_u (token),
 UNIQUE KEY nonce_u (nonce)
);

CREATE TABLE IF NOT EXISTS cs_attendance (
 coaching_id INT NOT NULL,
 batch_id INT NOT NULL,
 student_id INT NOT NULL,
 d DATE NOT NULL,
 status CHAR(1) NOT NULL,
 by_user INT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (batch_id, student_id, d),
 KEY cd_i (coaching_id, d),
 KEY s_i (student_id, d)
);

CREATE TABLE IF NOT EXISTS cs_enquiry (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 naam VARCHAR(80) NOT NULL,
 mobile VARCHAR(10) NOT NULL,
 interest VARCHAR(120) NOT NULL DEFAULT '',
 follow_up DATE NULL,
 status VARCHAR(10) NOT NULL DEFAULT 'open',
 note VARCHAR(255) NOT NULL DEFAULT '',
 source VARCHAR(10) NOT NULL DEFAULT 'owner',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY c_i (coaching_id, status, follow_up)
);

-- Student app ka plan (₹54 / 6 mahine, ₹99 / 12 mahine). Paisa Maakit ki
-- apni service ka hai (coaching ki fees nahi) — Razorpay se, webhook se khulta.
CREATE TABLE IF NOT EXISTS cs_sub (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 student_id INT NOT NULL,
 plan VARCHAR(4) NOT NULL,
 amount INT NOT NULL,
 status VARCHAR(10) NOT NULL DEFAULT 'created',
 order_id VARCHAR(60) NOT NULL,
 payment_id VARCHAR(60) NULL,
 paid_by VARCHAR(10) NOT NULL DEFAULT 'parent',
 paid_on DATETIME NULL,
 valid_from DATE NULL,
 valid_till DATE NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY order_u (order_id),
 KEY s_i (student_id, status)
);

CREATE TABLE IF NOT EXISTS cs_session (
 token_hash CHAR(64) PRIMARY KEY,
 kind VARCHAR(8) NOT NULL,
 mobile VARCHAR(10) NOT NULL,
 coaching_id INT NULL,
 user_id INT NULL,
 expires DATETIME NOT NULL,
 KEY e_i (expires)
);
