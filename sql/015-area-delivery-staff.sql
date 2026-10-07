SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS service_area_drivers (
 village_id INT NOT NULL,
 user_id INT NOT NULL,
 PRIMARY KEY(village_id,user_id), INDEX(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
