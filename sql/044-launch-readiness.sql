SET NAMES utf8mb4;
-- Only repair the obsolete seed URL; preserve the owner's other offers.
UPDATE banners SET link='/sewa.php?s=safar' WHERE link='/book.php?s=gaadi';
CREATE TABLE IF NOT EXISTS order_sarathi_locations (
  order_id INT PRIMARY KEY,
  rider_name VARCHAR(120) NULL,
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL,
  captured_at BIGINT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
