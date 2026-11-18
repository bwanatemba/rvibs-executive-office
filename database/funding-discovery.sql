-- Apply once to an existing installation. Fresh installations use schema.sql.
ALTER TABLE funding_opportunities
  ADD COLUMN category VARCHAR(100) DEFAULT NULL,
  ADD COLUMN applicant_institution ENUM('RVIBS','ATS','joint') DEFAULT NULL,
  ADD COLUMN eligibility_summary TEXT,
  ADD COLUMN discovery_evidence TEXT,
  ADD COLUMN discovery_key CHAR(64) DEFAULT NULL,
  ADD UNIQUE KEY uq_funding_discovery_key (discovery_key),
  MODIFY type ENUM('grant','equipment_donation','staff_exchange','student_exchange','partnership','service_contract','matching_grant','challenge_prize','scholarship_sponsorship','impact_investment','concessional_loan','public_private_partnership','in_kind_support') DEFAULT 'grant';

CREATE TABLE IF NOT EXISTS funding_discovery_runs (
  run_date DATE PRIMARY KEY,
  imported_count INT UNSIGNED NOT NULL,
  completed_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
