SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS sr_passwords (
 account_id BIGINT UNSIGNED PRIMARY KEY,
 pass_hash VARCHAR(255) NOT NULL,
 FOREIGN KEY(account_id) REFERENCES sr_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS sr_device_logins (
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 session_version INT NOT NULL,
 expires_at DATETIME NOT NULL,
 KEY(account_id), KEY(expires_at),
 FOREIGN KEY(account_id) REFERENCES sr_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
