SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS mk_school_otp_sessions (
    token_hash CHAR(64) NOT NULL PRIMARY KEY,
    phone_hash CHAR(64) NOT NULL,
    CONSTRAINT mk_school_otp_session_fk FOREIGN KEY (token_hash)
        REFERENCES sch_sessions(token_hash) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
