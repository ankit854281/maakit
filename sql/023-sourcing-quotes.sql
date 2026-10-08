SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS order_quotes (
 order_id INT PRIMARY KEY,
 revision INT NOT NULL DEFAULT 0,
 state VARCHAR(16) NOT NULL DEFAULT 'draft',
 goods_amount INT NULL,
 delivery_charge INT NULL,
 shop_details VARCHAR(240) NOT NULL DEFAULT '',
 delivery_time VARCHAR(160) NOT NULL DEFAULT '',
 details TEXT NULL,
 updated_at DATETIME NULL,
 accepted_at DATETIME NULL,
 CONSTRAINT fk_order_quote_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
