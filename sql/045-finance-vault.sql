-- Private MAAKIT Finance Vault; apply manually after backup.
CREATE TABLE IF NOT EXISTS finance_entries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 voucher_no VARCHAR(40) NOT NULL UNIQUE,
 expense_date DATE NOT NULL,
 vendor VARCHAR(160) NOT NULL,
 description VARCHAR(500) NOT NULL,
 category VARCHAR(60) NOT NULL,
 allocation ENUM('maakit','smart_sarathi','shared','unallocated') NOT NULL DEFAULT 'unallocated',
 amount_paise BIGINT UNSIGNED NOT NULL,
 payment_status ENUM('receipt_found','bank_verified','refunded') NOT NULL DEFAULT 'receipt_found',
 source_reference VARCHAR(180) DEFAULT NULL,
 original_receipt_location VARCHAR(255) DEFAULT NULL,
 created_by INT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_finance_date (expense_date),
 INDEX idx_finance_allocation (allocation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS finance_founder_work (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 work_date DATE NOT NULL,
 description VARCHAR(500) NOT NULL,
 hours DECIMAL(7,2) NOT NULL,
 estimated_rate_paise BIGINT UNSIGNED NOT NULL,
 evidence_reference VARCHAR(255) DEFAULT NULL,
 created_by INT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT chk_founder_hours CHECK (hours > 0 AND hours <= 24)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Founder time is a managerial valuation, NOT a tax invoice, cash outlay or booked salary.
