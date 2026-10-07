SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS support_resolution (
 ticket_id BIGINT PRIMARY KEY,
 decision ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 refund_amount INT NULL,
 refund_reference VARCHAR(100) NOT NULL DEFAULT '',
 updated_by INT NOT NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
