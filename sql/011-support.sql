SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS support_tickets (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 customer_id INT NOT NULL,
 reference_no VARCHAR(20) NOT NULL,
 kind ENUM('delivery','wrong_item','cancel','return','other') NOT NULL,
 message VARCHAR(1000) NOT NULL,
 status ENUM('new','reviewing','resolved','closed') NOT NULL DEFAULT 'new',
 reply VARCHAR(1000) NOT NULL DEFAULT '',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX(customer_id), INDEX(status), INDEX(reference_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
