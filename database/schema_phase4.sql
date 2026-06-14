-- =============================================================
-- Estate BOS — Phase 4: Worker Management schema
-- Run AFTER database/schema_phase3.sql (which created `workers`)
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- Worker documents (passport/IC, permit, contract, etc.)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS worker_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  worker_id INT NOT NULL,
  doc_type VARCHAR(100) NOT NULL,
  doc_number VARCHAR(100) NULL,
  issue_date DATE NULL,
  expiry_date DATE NULL,
  file_path VARCHAR(255) NULL,
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_wd_worker (worker_id),
  KEY idx_wd_expiry (expiry_date),
  CONSTRAINT fk_wd_worker FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Daily attendance
-- status: present | absent | leave | mc | half_day
-- method: manual | gps | qr
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS worker_attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  worker_id INT NOT NULL,
  attendance_date DATE NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'present',
  check_in_time TIME NULL,
  check_out_time TIME NULL,
  check_in_method VARCHAR(50) NULL,
  gps_lat DECIMAL(10,7) NULL,
  gps_lng DECIMAL(10,7) NULL,
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_attendance (worker_id, attendance_date),
  KEY idx_att_date (attendance_date),
  CONSTRAINT fk_att_worker FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Worker assignments (links to blocks/tasks — used by later phases)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS worker_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  worker_id INT NOT NULL,
  assignment_date DATE NOT NULL,
  block_id INT NULL,
  task_type VARCHAR(100) NULL,
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_wa_worker (worker_id),
  KEY idx_wa_date (assignment_date),
  CONSTRAINT fk_wa_worker FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Worker productivity (general productivity log)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS worker_productivity (
  id INT AUTO_INCREMENT PRIMARY KEY,
  worker_id INT NOT NULL,
  record_date DATE NOT NULL,
  category VARCHAR(100) NULL,
  productivity_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  unit VARCHAR(50) NULL,
  source VARCHAR(100) NULL,
  reference_id INT NULL,
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_wp_worker (worker_id),
  KEY idx_wp_date (record_date),
  CONSTRAINT fk_wp_worker FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Worker warnings / disciplinary records
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS worker_warnings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  worker_id INT NOT NULL,
  warning_date DATE NOT NULL,
  warning_type VARCHAR(100) NULL,
  description TEXT NULL,
  file_path VARCHAR(255) NULL,
  issued_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ww_worker (worker_id),
  CONSTRAINT fk_ww_worker FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
