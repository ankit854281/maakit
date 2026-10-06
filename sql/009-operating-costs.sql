SET NAMES utf8mb4;
-- Admin enters actual daily costs, including the day's share of monthly costs.
-- Absence of a row means costs are unknown, not zero.
CREATE TABLE IF NOT EXISTS operating_costs (
  cost_date DATE PRIMARY KEY,
  fuel INT UNSIGNED NOT NULL DEFAULT 0,
  staff INT UNSIGNED NOT NULL DEFAULT 0,
  other INT UNSIGNED NOT NULL DEFAULT 0,
  note VARCHAR(200) DEFAULT NULL,
  updated_by INT DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
