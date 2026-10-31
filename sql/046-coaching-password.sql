SET NAMES utf8mb4;
-- Coaching Saathi: mobile + password login (owner ki maang, 31 Oct 2026). OTP ki zaroorat nahi.
-- Ek mobile = ek password, chahe wo kitni bhi coaching me owner / teacher / parent ho.
-- temp=1: kisi owner ne pehla password diya hai — pehli login par naya rakhna zaroori.
-- 5 galat password = 15 minute ke liye band.
CREATE TABLE IF NOT EXISTS cs_account (
 mobile VARCHAR(10) PRIMARY KEY,
 pass_hash VARCHAR(255) NOT NULL,
 temp TINYINT NOT NULL DEFAULT 0,
 strikes INT NOT NULL DEFAULT 0,
 locked_until DATETIME NULL,
 set_by INT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
-- IP par roka (wahi table jo Smart Sarathi ka OTP use karta hai — same banawat)
CREATE TABLE IF NOT EXISTS sms_rate_limits (bucket VARCHAR(64) PRIMARY KEY, hits INT NOT NULL DEFAULT 0, expires BIGINT NOT NULL);
