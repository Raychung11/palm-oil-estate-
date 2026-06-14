-- =============================================================
-- Estate BOS — Phase 3: Harvest & FFB Management schema
-- Run AFTER database/schema_phase2.sql
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- Workers (minimal master — full management arrives in Phase 4)
-- Needed now so harvest teams can be assigned.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS workers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  worker_code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(50) NULL,
  nationality VARCHAR(100) NULL,
  worker_type VARCHAR(100) NULL,
  ic_passport VARCHAR(100) NULL,
  permit_expiry DATE NULL,
  contract_expiry DATE NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Harvest records (one per block per harvest entry)
-- approval_status: pending | approved | rejected
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS harvest_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  harvest_date DATE NOT NULL,
  estate_id INT NOT NULL,
  division_id INT NOT NULL,
  block_id INT NOT NULL,
  supervisor_id INT NULL,
  bunches_count INT NOT NULL DEFAULT 0,
  ffb_weight_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
  loose_fruit_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
  rejected_bunches INT NOT NULL DEFAULT 0,
  collection_time TIME NULL,
  remarks TEXT NULL,
  approval_status VARCHAR(50) NOT NULL DEFAULT 'pending',
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_harvest_date (harvest_date),
  KEY idx_harvest_block (block_id),
  KEY idx_harvest_status (approval_status),
  CONSTRAINT fk_harvest_estate   FOREIGN KEY (estate_id)   REFERENCES estates(id),
  CONSTRAINT fk_harvest_division FOREIGN KEY (division_id) REFERENCES divisions(id),
  CONSTRAINT fk_harvest_block    FOREIGN KEY (block_id)    REFERENCES blocks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Harvest team — workers assigned to a harvest record
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS harvest_record_workers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  harvest_record_id INT NOT NULL,
  worker_id INT NOT NULL,
  role_in_task VARCHAR(100) NULL,
  productivity_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_hrw_record (harvest_record_id),
  KEY idx_hrw_worker (worker_id),
  CONSTRAINT fk_hrw_record FOREIGN KEY (harvest_record_id) REFERENCES harvest_records(id) ON DELETE CASCADE,
  CONSTRAINT fk_hrw_worker FOREIGN KEY (worker_id) REFERENCES workers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Harvest field photos
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS harvest_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  harvest_record_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  caption VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_hp_record (harvest_record_id),
  CONSTRAINT fk_hp_record FOREIGN KEY (harvest_record_id) REFERENCES harvest_records(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Harvest approval audit trail
-- action: approved | rejected
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS harvest_approvals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  harvest_record_id INT NOT NULL,
  action VARCHAR(50) NOT NULL,
  remarks VARCHAR(255) NULL,
  approved_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ha_record (harvest_record_id),
  CONSTRAINT fk_ha_record FOREIGN KEY (harvest_record_id) REFERENCES harvest_records(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
