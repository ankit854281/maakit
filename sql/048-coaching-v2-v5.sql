SET NAMES utf8mb4;
-- ============================================================
-- Coaching Saathi V2–V5 (31 Oct 2026, owner: "V1 se V5 sab abhi")
-- V2: Maakit admin panel, logo, notice, homework, timetable, notes
-- V3: test ke marks, teacher ka quiz
-- V4: mock test (negative marking, rank), doubt box
-- V5: teacher haaziri + salary slip, risk score, sabko WhatsApp
-- Sirf cs_ tables. Har table me coaching_id. Is file ko kabhi
-- badaliye mat (updater dobara chala dega) — naya badlav = nayi file.
-- ============================================================

-- Har coaching ka extra: logo, band/chalu (Maakit admin)
CREATE TABLE IF NOT EXISTS cs_coaching_meta (
 coaching_id INT PRIMARY KEY,
 logo VARCHAR(64) NULL,
 blocked TINYINT NOT NULL DEFAULT 0,
 admin_note VARCHAR(255) NOT NULL DEFAULT '',
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Suchna: batch_id NULL = poori coaching
CREATE TABLE IF NOT EXISTS cs_notice (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 batch_id INT NULL,
 title VARCHAR(120) NOT NULL,
 body TEXT NULL,
 by_user INT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY c_i (coaching_id, created_at)
);

CREATE TABLE IF NOT EXISTS cs_homework (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 batch_id INT NOT NULL,
 d DATE NOT NULL,
 subject VARCHAR(60) NOT NULL DEFAULT '',
 body TEXT NOT NULL,
 by_user INT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY cb_i (coaching_id, batch_id, d)
);

-- Hafte ka timetable: day 1 = Somvar ... 7 = Ravivar
CREATE TABLE IF NOT EXISTS cs_timetable (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 batch_id INT NOT NULL,
 day TINYINT NOT NULL,
 t_from VARCHAR(5) NOT NULL,
 t_to VARCHAR(5) NOT NULL,
 subject VARCHAR(60) NOT NULL,
 teacher_id INT NULL,
 KEY cb_i (coaching_id, batch_id, day)
);

-- Notes / study material: likha hua, link, ya file (PDF / photo)
CREATE TABLE IF NOT EXISTS cs_note (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 batch_id INT NOT NULL,
 title VARCHAR(120) NOT NULL,
 body TEXT NULL,
 link VARCHAR(500) NOT NULL DEFAULT '',
 file VARCHAR(64) NULL,
 fname VARCHAR(120) NOT NULL DEFAULT '',
 mime VARCHAR(40) NOT NULL DEFAULT '',
 by_user INT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY cb_i (coaching_id, batch_id)
);

-- Kagaz wala test: teacher marks bharta hai
CREATE TABLE IF NOT EXISTS cs_test (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 batch_id INT NOT NULL,
 naam VARCHAR(120) NOT NULL,
 subject VARCHAR(60) NOT NULL DEFAULT '',
 d DATE NOT NULL,
 max_marks INT NOT NULL,
 by_user INT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY cb_i (coaching_id, batch_id, d)
);
CREATE TABLE IF NOT EXISTS cs_mark (
 test_id INT NOT NULL,
 student_id INT NOT NULL,
 coaching_id INT NOT NULL,
 marks DECIMAL(7,2) NULL,
 absent TINYINT NOT NULL DEFAULT 0,
 PRIMARY KEY (test_id, student_id),
 KEY cs_i (coaching_id, student_id)
);

-- Online quiz / mock test (teacher banata hai)
-- kind: quiz = har sawaal ka apna samay; mock = poore test ka samay + negative marking
CREATE TABLE IF NOT EXISTS cs_quiz (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 batch_id INT NOT NULL,
 kind VARCHAR(5) NOT NULL DEFAULT 'quiz',
 title VARCHAR(120) NOT NULL,
 secs_per_q INT NOT NULL DEFAULT 30,
 total_min INT NOT NULL DEFAULT 0,
 neg DECIMAL(4,2) NOT NULL DEFAULT 0,
 opens_at DATETIME NULL,
 closes_at DATETIME NULL,
 published TINYINT NOT NULL DEFAULT 0,
 by_user INT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY cb_i (coaching_id, batch_id)
);
CREATE TABLE IF NOT EXISTS cs_quiz_q (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 quiz_id INT NOT NULL,
 n INT NOT NULL,
 section VARCHAR(40) NOT NULL DEFAULT '',
 q TEXT NOT NULL,
 a VARCHAR(300) NOT NULL,
 b VARCHAR(300) NOT NULL,
 c VARCHAR(300) NOT NULL DEFAULT '',
 d VARCHAR(300) NOT NULL DEFAULT '',
 ans CHAR(1) NOT NULL,
 KEY q_i (coaching_id, quiz_id, n)
);
-- Ek bachcha, ek quiz = ek koshish. answers = {q_id: {"c":"a","t":served_at}}
CREATE TABLE IF NOT EXISTS cs_quiz_att (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 quiz_id INT NOT NULL,
 student_id INT NOT NULL,
 started_at DATETIME NOT NULL,
 finished_at DATETIME NULL,
 answers TEXT NULL,
 right_n INT NOT NULL DEFAULT 0,
 wrong_n INT NOT NULL DEFAULT 0,
 score DECIMAL(8,2) NOT NULL DEFAULT 0,
 UNIQUE KEY qs_u (quiz_id, student_id),
 KEY c_i (coaching_id, student_id)
);

-- Doubt box: bachcha poochhe, teacher jawab de
CREATE TABLE IF NOT EXISTS cs_doubt (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 batch_id INT NOT NULL,
 student_id INT NOT NULL,
 q TEXT NOT NULL,
 answer TEXT NULL,
 answered_by INT NULL,
 answered_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY cb_i (coaching_id, batch_id, answered_at)
);

-- Teacher ki haaziri: P / A / H (aadha din)
CREATE TABLE IF NOT EXISTS cs_staff_att (
 coaching_id INT NOT NULL,
 user_id INT NOT NULL,
 d DATE NOT NULL,
 status CHAR(1) NOT NULL,
 PRIMARY KEY (coaching_id, user_id, d)
);
CREATE TABLE IF NOT EXISTS cs_salary_rate (
 coaching_id INT NOT NULL,
 user_id INT NOT NULL,
 monthly INT NOT NULL DEFAULT 0,
 paid_leave DECIMAL(4,1) NOT NULL DEFAULT 1,
 PRIMARY KEY (coaching_id, user_id)
);
-- Paid slip ke number jam jaate hain (badalte nahi)
CREATE TABLE IF NOT EXISTS cs_salary_slip (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 user_id INT NOT NULL,
 month CHAR(7) NOT NULL,
 monthly INT NOT NULL,
 work_days INT NOT NULL,
 absent DECIMAL(5,1) NOT NULL,
 cut INT NOT NULL,
 advance INT NOT NULL DEFAULT 0,
 bonus INT NOT NULL DEFAULT 0,
 net INT NOT NULL,
 paid_on DATE NOT NULL,
 by_user INT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY um_u (coaching_id, user_id, month)
);
