SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS auth_failures (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 scope VARCHAR(16) NOT NULL,
 identity_hash CHAR(64) NOT NULL,
 ip_hash CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX(scope,identity_hash,created_at),
 INDEX(scope,ip_hash,created_at), INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
