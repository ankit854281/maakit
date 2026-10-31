SET NAMES utf8mb4;
-- Coaching Saathi: Game Zone (school wala engine, school/game_lib.php) — 31 Oct 2026.
-- Ek row = ek round (5 sawaal). items JSON: [{src:'math'|'word', ref:seed, servedAt, choice, correct, review}]
-- Sahi jawab kabhi phone par pehle nahi jaata: har baar seed se server par dobara banta hai.
CREATE TABLE IF NOT EXISTS cs_game_round (
 id INT AUTO_INCREMENT PRIMARY KEY,
 coaching_id INT NOT NULL,
 student_id INT NOT NULL,
 d DATE NOT NULL,
 n TINYINT NOT NULL,
 items TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY day_u (student_id, d, n),
 KEY c_i (coaching_id, d)
);
