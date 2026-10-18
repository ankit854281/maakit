SET NAMES utf8mb4;
-- Maakit Jyotish: AI Jyotishi ke roz ke sawaal ginne ke liye (IP ka hash, naam/number nahi).
CREATE TABLE IF NOT EXISTS jy_ai_use (
 bucket CHAR(64) PRIMARY KEY,
 day DATE NOT NULL,
 hits INT NOT NULL DEFAULT 0,
 KEY day_idx (day)
);
