-- Finance audit history. Apply after sql/045-finance-vault.sql on staging.
CREATE TABLE IF NOT EXISTS finance_audit_log (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 entry_id BIGINT UNSIGNED NOT NULL,
 action ENUM('create','verify','refund','correct') NOT NULL,
 actor_user_id INT NOT NULL,
 event_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reason VARCHAR(500) NOT NULL,
 previous_status VARCHAR(32) DEFAULT NULL,
 new_status VARCHAR(32) DEFAULT NULL,
 evidence_reference VARCHAR(180) DEFAULT NULL,
 INDEX idx_finance_audit_entry (entry_id, event_at),
 CONSTRAINT fk_finance_audit_entry FOREIGN KEY (entry_id) REFERENCES finance_entries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Application must record changes transactionally; table alone is not an immutable audit guarantee.
