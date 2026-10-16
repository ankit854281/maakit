SET NAMES utf8mb4;
-- ============================================================
-- 036 — School ERP (maakit.in/school)
--
-- SN Mishra Singapore International School ka academic ERP.
-- Maakit ki kisi bhi purani table ko haath nahi lagata — sab
-- tables "sch_" se shuru hoti hain. Do baar chalne par bhi safe.
--
--   sch_users     kaun login kar sakta hai (Principal, Chairman,
--                 har teacher) — PIN sirf hash me
--   sch_sessions  login ke baad ka token (hash me)
--   sch_records   school ka saara data: attendance, diary,
--                 syllabus, marks, timetable, students, notices
--   sch_log       har kaam ka hisaab — kisne, kya, kab
--   sch_login_fail galat PIN ki ginti (IP ke hisaab se)
-- ============================================================

CREATE TABLE IF NOT EXISTS sch_users (
  uid          VARCHAR(40)  NOT NULL PRIMARY KEY,
  role         VARCHAR(12)  NOT NULL,
  tid          VARCHAR(20)  NULL,
  pin_hash     VARCHAR(255) NULL,
  fails        INT          NOT NULL DEFAULT 0,
  locked_until DATETIME     NULL,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sch_sessions (
  token_hash CHAR(64)    NOT NULL PRIMARY KEY,
  uid        VARCHAR(40) NOT NULL,
  created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME    NOT NULL,
  last_seen  DATETIME    NULL,
  KEY sch_sess_uid (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sch_records (
  k          VARCHAR(20)  NOT NULL,
  r          VARCHAR(190) NOT NULL,
  d          MEDIUMTEXT   NULL,
  deleted    TINYINT(1)   NOT NULL DEFAULT 0,
  dkey       DATE         NULL,
  ver        BIGINT       NOT NULL,
  by_uid     VARCHAR(40)  NULL,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (k, r),
  KEY sch_rec_ver (ver),
  KEY sch_rec_dkey (dkey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sch_log (
  id      BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ts      BIGINT       NOT NULL,
  uname   VARCHAR(80)  NOT NULL,
  role    VARCHAR(12)  NOT NULL,
  k       VARCHAR(40)  NOT NULL,
  x       TEXT         NOT NULL,
  KEY sch_log_ts (ts),
  KEY sch_log_k (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sch_login_fail (
  id  BIGINT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip  VARCHAR(45) NOT NULL,
  at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY sch_fail_ip (ip, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
