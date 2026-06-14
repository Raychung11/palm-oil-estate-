-- =============================================================
-- Estate BOS — Phase 6: Fertilizer & Chemical Management schema
-- Run AFTER database/schema_phase5.sql
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================ FERTILIZER ============================

CREATE TABLE IF NOT EXISTS fertilizer_products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_code VARCHAR(50) NULL,
  product_name VARCHAR(150) NOT NULL,
  fertilizer_type VARCHAR(100) NULL,
  unit VARCHAR(50) NOT NULL DEFAULT 'kg',
  cost_per_unit DECIMAL(12,2) NOT NULL DEFAULT 0,
  current_stock DECIMAL(14,2) NOT NULL DEFAULT 0,
  minimum_stock DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- movement_type: in | out | adjustment
CREATE TABLE IF NOT EXISTS fertilizer_stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  movement_date DATE NOT NULL,
  movement_type VARCHAR(50) NOT NULL,
  quantity DECIMAL(14,2) NOT NULL,
  unit_cost DECIMAL(12,2) NULL,
  reference_type VARCHAR(100) NULL,
  reference_id INT NULL,
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fsm_product (product_id),
  KEY idx_fsm_date (movement_date),
  CONSTRAINT fk_fsm_product FOREIGN KEY (product_id) REFERENCES fertilizer_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fertilizer_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_date DATE NOT NULL,
  product_id INT NOT NULL,
  estate_id INT NULL,
  division_id INT NULL,
  block_id INT NULL,
  worker_id INT NULL,
  quantity DECIMAL(14,2) NOT NULL DEFAULT 0,
  unit VARCHAR(50) NULL,
  cost_per_unit DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fa_date (application_date),
  KEY idx_fa_block (block_id),
  CONSTRAINT fk_fa_product FOREIGN KEY (product_id) REFERENCES fertilizer_products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: planned | done | cancelled
CREATE TABLE IF NOT EXISTS fertilizer_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  block_id INT NULL,
  scheduled_date DATE NOT NULL,
  planned_quantity DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(50) NOT NULL DEFAULT 'planned',
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fsch_date (scheduled_date),
  CONSTRAINT fk_fsch_product FOREIGN KEY (product_id) REFERENCES fertilizer_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================ CHEMICAL =============================

CREATE TABLE IF NOT EXISTS chemical_products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_code VARCHAR(50) NULL,
  product_name VARCHAR(150) NOT NULL,
  chemical_type VARCHAR(100) NULL,
  active_ingredient VARCHAR(150) NULL,
  unit VARCHAR(50) NOT NULL DEFAULT 'litre',
  cost_per_unit DECIMAL(12,2) NOT NULL DEFAULT 0,
  current_stock DECIMAL(14,2) NOT NULL DEFAULT 0,
  minimum_stock DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chemical_stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  movement_date DATE NOT NULL,
  movement_type VARCHAR(50) NOT NULL,
  quantity DECIMAL(14,2) NOT NULL,
  unit_cost DECIMAL(12,2) NULL,
  reference_type VARCHAR(100) NULL,
  reference_id INT NULL,
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_csm_product (product_id),
  KEY idx_csm_date (movement_date),
  CONSTRAINT fk_csm_product FOREIGN KEY (product_id) REFERENCES chemical_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spraying_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  spray_date DATE NOT NULL,
  product_id INT NOT NULL,
  estate_id INT NULL,
  division_id INT NULL,
  block_id INT NULL,
  quantity DECIMAL(14,2) NOT NULL DEFAULT 0,
  unit VARCHAR(50) NULL,
  cost_per_unit DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  ppe_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  weather VARCHAR(100) NULL,
  safety_remarks VARCHAR(255) NULL,
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_spray_date (spray_date),
  KEY idx_spray_block (block_id),
  CONSTRAINT fk_spray_product FOREIGN KEY (product_id) REFERENCES chemical_products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spraying_record_workers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  spraying_record_id INT NOT NULL,
  worker_id INT NOT NULL,
  role_in_task VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_srw_record (spraying_record_id),
  KEY idx_srw_worker (worker_id),
  CONSTRAINT fk_srw_record FOREIGN KEY (spraying_record_id) REFERENCES spraying_records(id) ON DELETE CASCADE,
  CONSTRAINT fk_srw_worker FOREIGN KEY (worker_id) REFERENCES workers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
