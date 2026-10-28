-- RVIBS Executive Office & Funding Management Platform
-- Database Schema
-- For MySQL 5.7+ / MariaDB 10.3+

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "";

-- -----------------------------------------------------
-- Database creation (uncomment if creating fresh DB)
-- -----------------------------------------------------
-- CREATE DATABASE IF NOT EXISTS rvibs_exec_office CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE rvibs_exec_office;

-- -----------------------------------------------------
-- Table: roles
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `role_key` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `can_manage_users` TINYINT(1) DEFAULT 0,
  `can_view_all` TINYINT(1) DEFAULT 0,
  `can_approve` TINYINT(1) DEFAULT 0,
  `can_manage_finance` TINYINT(1) DEFAULT 0,
  `can_manage_funding` TINYINT(1) DEFAULT 0,
  `can_coordinate` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 99
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: users
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `role_id` INT,
  `avatar_url` VARCHAR(500) DEFAULT NULL,
  `status` ENUM('active','inactive','pending') DEFAULT 'pending',
  `google_id` VARCHAR(255) DEFAULT NULL,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: outcomes (90-day outcomes)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `outcomes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(500) NOT NULL,
  `description` TEXT,
  `owner_id` INT,
  `measure_of_success` TEXT,
  `due_date` DATE,
  `progress` INT DEFAULT 0,
  `status` ENUM('on_track','at_risk','off_track','complete') DEFAULT 'on_track',
  `pillar` VARCHAR(100) DEFAULT NULL,
  `quarter_label` VARCHAR(50) DEFAULT NULL,
  `created_by` INT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: commitments
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `commitments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(500) NOT NULL,
  `description` TEXT,
  `owner_id` INT,
  `due_date` DATE,
  `original_due_date` DATE,
  `status` ENUM('open','in_progress','complete','blocked','overdue') DEFAULT 'open',
  `blocker` TEXT,
  `evidence` TEXT,
  `evidence_file` VARCHAR(500) DEFAULT NULL,
  `priority` ENUM('low','medium','high','critical') DEFAULT 'medium',
  `linked_outcome_id` INT DEFAULT NULL,
  `created_by` INT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`linked_outcome_id`) REFERENCES `outcomes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: commitment_deadline_changes (audit trail)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `commitment_deadline_changes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `commitment_id` INT NOT NULL,
  `old_deadline` DATE,
  `new_deadline` DATE,
  `reason` TEXT NOT NULL,
  `changed_by` INT,
  `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`commitment_id`) REFERENCES `commitments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: ideas (new ideas pipeline, separate from approved assignments)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ideas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(500) NOT NULL,
  `description` TEXT,
  `proposed_by` INT,
  `status` ENUM('new','under_review','approved','declined','converted') DEFAULT 'new',
  `capacity_note` TEXT,
  `resource_required` TEXT,
  `decision_note` TEXT,
  `decided_by` INT,
  `decided_at` DATETIME DEFAULT NULL,
  `converted_commitment_id` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`proposed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`decided_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: decisions (delegated authority)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `decisions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(500) NOT NULL,
  `description` TEXT,
  `owner_id` INT,
  `authority_level` VARCHAR(100) DEFAULT NULL,
  `delegation_limit` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','approved','declined','escalated') DEFAULT 'pending',
  `decided_by` INT,
  `decided_at` DATETIME DEFAULT NULL,
  `decision_notes` TEXT,
  `requires_director_approval` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`decided_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: escalations (material exceptions)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `escalations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(500) NOT NULL,
  `description` TEXT,
  `raised_by` INT,
  `raised_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `priority` ENUM('medium','high','critical') DEFAULT 'high',
  `status` ENUM('open','resolved') DEFAULT 'open',
  `resolution` TEXT,
  `resolved_by` INT,
  `resolved_at` DATETIME DEFAULT NULL,
  `linked_item_type` VARCHAR(50) DEFAULT NULL,
  `linked_item_id` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`raised_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: weekly_reviews
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `weekly_reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `week_of` DATE NOT NULL,
  `summary` TEXT,
  `prepared_by` INT,
  `status` ENUM('draft','published') DEFAULT 'draft',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`prepared_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: weekly_review_items
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `weekly_review_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `review_id` INT NOT NULL,
  `item_type` ENUM('outcome','commitment','idea','escalation','funding') DEFAULT 'commitment',
  `item_ref_id` INT DEFAULT NULL,
  `discussion` TEXT,
  `action` TEXT,
  `owner_id` INT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`review_id`) REFERENCES `weekly_reviews`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: daily_briefs
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `daily_briefs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `brief_date` DATE NOT NULL,
  `summary` TEXT,
  `prepared_by` INT,
  `status` ENUM('draft','published') DEFAULT 'draft',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`prepared_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: daily_brief_items
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `daily_brief_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `brief_id` INT NOT NULL,
  `item_type` ENUM('commitment','escalation','funding','decision','communication') DEFAULT 'commitment',
  `item_ref_id` INT DEFAULT NULL,
  `note` TEXT,
  `priority` ENUM('low','medium','high','critical') DEFAULT 'medium',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`brief_id`) REFERENCES `daily_briefs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: funding_readiness (institutional readiness)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `funding_readiness` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `institution` VARCHAR(255) NOT NULL,
  `legal_route` VARCHAR(255) DEFAULT NULL,
  `supporting_evidence` TEXT,
  `status` ENUM('ready','in_progress','not_ready') DEFAULT 'in_progress',
  `notes` TEXT,
  `updated_by` INT,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: funding_opportunities
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `funding_opportunities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(500) NOT NULL,
  `funder` VARCHAR(255) DEFAULT NULL,
  `url` VARCHAR(500) DEFAULT NULL,
  `source` VARCHAR(255) DEFAULT NULL,
  `amount` DECIMAL(14,2) DEFAULT NULL,
  `currency` VARCHAR(10) DEFAULT 'KES',
  `deadline` DATE DEFAULT NULL,
  `description` TEXT,
  `type` ENUM('grant','equipment_donation','staff_exchange','student_exchange','partnership') DEFAULT 'grant',
  `status` ENUM('discovered','verified','ineligible','eligible','pursuing','preparing','submitted','monitoring','awarded','declined','closed') DEFAULT 'discovered',
  `discovered_by` INT,
  `discovered_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `verified_by` INT,
  `verified_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`discovered_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: funding_eligibility_checks
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `funding_eligibility_checks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `opportunity_id` INT NOT NULL,
  `check_type` ENUM('eligibility','strategic_fit','readiness','evidence_confidence') NOT NULL,
  `result` ENUM('pass','fail','conditional') DEFAULT 'conditional',
  `notes` TEXT,
  `checked_by` INT,
  `checked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`opportunity_id`) REFERENCES `funding_opportunities`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`checked_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: funding_preparations
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `funding_preparations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `opportunity_id` INT NOT NULL,
  `owner_id` INT,
  `resources_allocated` TEXT,
  `capacity_note` TEXT,
  `next_action` VARCHAR(500),
  `next_action_date` DATE,
  `status` ENUM('not_started','in_progress','ready','blocked') DEFAULT 'not_started',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`opportunity_id`) REFERENCES `funding_opportunities`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: funding_applications
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `funding_applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `opportunity_id` INT NOT NULL,
  `title` VARCHAR(500),
  `technical_review_status` ENUM('pending','in_review','approved','changes_required') DEFAULT 'pending',
  `technical_review_by` INT,
  `technical_review_at` DATETIME DEFAULT NULL,
  `technical_review_notes` TEXT,
  `financial_review_status` ENUM('pending','in_review','approved','changes_required') DEFAULT 'pending',
  `financial_review_by` INT,
  `financial_review_at` DATETIME DEFAULT NULL,
  `financial_review_notes` TEXT,
  `approval_status` ENUM('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` INT,
  `approved_at` DATETIME DEFAULT NULL,
  `submission_package` TEXT,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`opportunity_id`) REFERENCES `funding_opportunities`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`technical_review_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`financial_review_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: funding_submissions
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `funding_submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `submitted_by` INT,
  `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `receipt_reference` VARCHAR(255),
  `receipt_date` DATE,
  `follow_up_date` DATE,
  `follow_up_notes` TEXT,
  `outcome` ENUM('pending','awarded','declined','withdrawn') DEFAULT 'pending',
  `outcome_date` DATE,
  `outcome_notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`application_id`) REFERENCES `funding_applications`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`submitted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: funding_awards
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `funding_awards` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `submission_id` INT NOT NULL,
  `opportunity_id` INT,
  `amount` DECIMAL(14,2),
  `currency` VARCHAR(10) DEFAULT 'KES',
  `conditions` TEXT,
  `accepted` TINYINT(1) DEFAULT 0,
  `accepted_by` INT,
  `accepted_at` DATETIME DEFAULT NULL,
  `financial_obligations` TEXT,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`submission_id`) REFERENCES `funding_submissions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`opportunity_id`) REFERENCES `funding_opportunities`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`accepted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: funding_disbursements (cash and in-kind recorded separately)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `funding_disbursements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `award_id` INT NOT NULL,
  `type` ENUM('cash','in_kind') NOT NULL DEFAULT 'cash',
  `description` VARCHAR(500),
  `amount` DECIMAL(14,2) DEFAULT NULL,
  `value` DECIMAL(14,2) DEFAULT NULL,
  `date_received` DATE,
  `recipient_costs` TEXT,
  `notes` TEXT,
  `validated_by` INT,
  `validated_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`award_id`) REFERENCES `funding_awards`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`validated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: funding_milestones
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `funding_milestones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `award_id` INT NOT NULL,
  `title` VARCHAR(500) NOT NULL,
  `description` TEXT,
  `due_date` DATE,
  `status` ENUM('pending','in_progress','complete','overdue') DEFAULT 'pending',
  `completed_at` DATETIME DEFAULT NULL,
  `reporting_required` TINYINT(1) DEFAULT 0,
  `report_submitted` TINYINT(1) DEFAULT 0,
  `notes` TEXT,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`award_id`) REFERENCES `funding_awards`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: funding_closeouts
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `funding_closeouts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `award_id` INT NOT NULL,
  `reconciliation_status` ENUM('pending','in_progress','complete') DEFAULT 'pending',
  `final_report_status` ENUM('pending','draft','submitted','accepted') DEFAULT 'pending',
  `final_report_date` DATE,
  `closeout_date` DATE,
  `notes` TEXT,
  `completed_by` INT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`award_id`) REFERENCES `funding_awards`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: partners
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `partners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `type` ENUM('grant_body','donor','partner','institution') DEFAULT 'partner',
  `contact_person` VARCHAR(255),
  `email` VARCHAR(255),
  `phone` VARCHAR(100),
  `relationship_summary` TEXT,
  `stewardship_notes` TEXT,
  `renewal_opportunities` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: communications (Executive Communications Agent)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `communications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `source` ENUM('email','whatsapp') NOT NULL DEFAULT 'email',
  `subject` VARCHAR(500),
  `from_address` VARCHAR(255),
  `from_name` VARCHAR(255),
  `body` TEXT,
  `date` DATETIME,
  `status` ENUM('unread','read','summarized','drafted','actioned') DEFAULT 'unread',
  `ai_summary` TEXT,
  `ai_draft_reply` TEXT,
  `linked_item_type` VARCHAR(50) DEFAULT NULL,
  `linked_item_id` INT DEFAULT NULL,
  `thread_id` VARCHAR(255) DEFAULT NULL,
  `is_pending` TINYINT(1) DEFAULT 0,
  `follow_up_date` DATE,
  `processed_by` INT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: audit_log
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `user_name` VARCHAR(255),
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50),
  `entity_id` INT,
  `old_values` TEXT,
  `new_values` TEXT,
  `ip_address` VARCHAR(45),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: settings
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- SEED DATA
-- =====================================================

-- Roles
INSERT INTO `roles` (`name`, `role_key`, `description`, `can_manage_users`, `can_view_all`, `can_approve`, `can_manage_finance`, `can_manage_funding`, `can_coordinate`, `sort_order`) VALUES
('System Administrator', 'system_admin', 'Full system access, manages users and roles', 1, 1, 0, 0, 1, 0, 1),
('Chairman & CEO / Director', 'chairman_ceo', 'Sets priorities, approves reserved decisions, resolves exceptions', 0, 1, 1, 0, 1, 0, 2),
('Executive Coordinator', 'executive_coordinator', 'Coordinates records, follow-ups, review packs and daily brief', 0, 1, 0, 0, 0, 1, 3),
('RVIBS Principal', 'rvibs_principal', 'Owns institutional outcomes and delivery commitments', 0, 0, 0, 0, 1, 0, 4),
('ATS Lead', 'ats_lead', 'Leads ATS and ATS Kindergarten operations and funding', 0, 0, 0, 0, 1, 0, 5),
('Finance', 'finance', 'Validates budgets, award conditions, receipts and financial reporting', 0, 0, 0, 1, 1, 0, 6);

-- System admin user (support@rvibs.ac.ke)
INSERT INTO `users` (`name`, `email`, `role_id`, `status`, `google_id`) VALUES
('System Administrator', 'support@rvibs.ac.ke', 1, 'active', NULL);

-- Director accounts (director@rvibs.ac.ke and gitau.john@rvibs.ac.ke)
INSERT INTO `users` (`name`, `email`, `role_id`, `status`, `google_id`) VALUES
('Chairman & CEO', 'director@rvibs.ac.ke', 2, 'active', NULL),
('John Gitau', 'gitau.john@rvibs.ac.ke', 2, 'active', NULL);

-- Default settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('google_client_id', ''),
('google_client_secret', ''),
('app_url', ''),
('allowed_domain', 'rvibs.ac.ke'),
('director_email', 'director@rvibs.ac.ke'),
('coordinator_email', ''),
('current_quarter', 'Q1 2026-2027'),
('org_name', 'RVIBS & ATS Executive Office'),
('whatsapp_enabled', '0'),
('whatsapp_number', '');
