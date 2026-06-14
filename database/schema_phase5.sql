-- =============================================================
-- Estate BOS — Phase 5: Field Task Management schema
-- Run AFTER database/schema_phase4.sql
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- Field tasks
-- status: planned | in_progress | completed | approved | rejected
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS field_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_date DATE NOT NULL,
  estate_id INT NULL,
  division_id INT NULL,
  block_id INT NULL,
  task_type VARCHAR(100) NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  assigned_to INT NULL,
  priority VARCHAR(20) NOT NULL DEFAULT 'normal',
  status VARCHAR(50) NOT NULL DEFAULT 'planned',
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  remarks TEXT NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_task_date (task_date),
  KEY idx_task_status (status),
  KEY idx_task_block (block_id),
  CONSTRAINT fk_task_estate   FOREIGN KEY (estate_id)   REFERENCES estates(id)   ON DELETE SET NULL,
  CONSTRAINT fk_task_division FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE SET NULL,
  CONSTRAINT fk_task_block    FOREIGN KEY (block_id)    REFERENCES blocks(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Task team — workers assigned to a task
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS field_task_workers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  field_task_id INT NOT NULL,
  worker_id INT NOT NULL,
  role_in_task VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ftw_task (field_task_id),
  KEY idx_ftw_worker (worker_id),
  CONSTRAINT fk_ftw_task   FOREIGN KEY (field_task_id) REFERENCES field_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_ftw_worker FOREIGN KEY (worker_id)     REFERENCES workers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Task proof photos
-- phase: start | proof
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS field_task_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  field_task_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  phase VARCHAR(20) NOT NULL DEFAULT 'proof',
  caption VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ftp_task (field_task_id),
  CONSTRAINT fk_ftp_task FOREIGN KEY (field_task_id) REFERENCES field_tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Task status change log
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS field_task_status_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  field_task_id INT NOT NULL,
  from_status VARCHAR(50) NULL,
  to_status VARCHAR(50) NOT NULL,
  remarks VARCHAR(255) NULL,
  changed_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ftsl_task (field_task_id),
  CONSTRAINT fk_ftsl_task FOREIGN KEY (field_task_id) REFERENCES field_tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
