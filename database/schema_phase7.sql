-- =============================================================
-- Estate BOS — Phase 7: Inventory, Asset & Fuel schema
-- Run AFTER database/schema_phase6.sql
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================ INVENTORY ============================

CREATE TABLE IF NOT EXISTS inventory_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_code VARCHAR(50) NULL,
  item_name VARCHAR(150) NOT NULL,
  category VARCHAR(100) NULL,
  unit VARCHAR(50) NOT NULL DEFAULT 'unit',
  location VARCHAR(100) NULL,
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
CREATE TABLE IF NOT EXISTS inventory_stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  movement_date DATE NOT NULL,
  movement_type VARCHAR(50) NOT NULL,
  quantity DECIMAL(14,2) NOT NULL,
  unit_cost DECIMAL(12,2) NULL,
  reference_type VARCHAR(100) NULL,
  reference_id INT NULL,
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ism_item (item_id),
  KEY idx_ism_date (movement_date),
  CONSTRAINT fk_ism_item FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================ ASSETS ==============================

CREATE TABLE IF NOT EXISTS assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_code VARCHAR(50) NOT NULL UNIQUE,
  asset_name VARCHAR(150) NOT NULL,
  asset_type VARCHAR(100) NULL,
  registration_no VARCHAR(100) NULL,
  make_model VARCHAR(150) NULL,
  purchase_date DATE NULL,
  purchase_cost DECIMAL(14,2) NULL,
  road_tax_expiry DATE NULL,
  insurance_expiry DATE NULL,
  remarks VARCHAR(255) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- maintenance_type: service | repair | breakdown
CREATE TABLE IF NOT EXISTS asset_maintenance_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  service_date DATE NOT NULL,
  maintenance_type VARCHAR(50) NOT NULL DEFAULT 'service',
  description TEXT NULL,
  cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  odometer DECIMAL(12,2) NULL,
  next_service_date DATE NULL,
  file_path VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_aml_asset (asset_id),
  KEY idx_aml_next (next_service_date),
  CONSTRAINT fk_aml_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_usage_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  usage_date DATE NOT NULL,
  driver_worker_id INT NULL,
  block_id INT NULL,
  purpose VARCHAR(255) NULL,
  start_meter DECIMAL(12,2) NULL,
  end_meter DECIMAL(12,2) NULL,
  hours_used DECIMAL(10,2) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_aul_asset (asset_id),
  KEY idx_aul_date (usage_date),
  CONSTRAINT fk_aul_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_id INT NOT NULL,
  doc_type VARCHAR(100) NOT NULL,
  doc_number VARCHAR(100) NULL,
  expiry_date DATE NULL,
  file_path VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ad_asset (asset_id),
  CONSTRAINT fk_ad_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================== FUEL ==============================

CREATE TABLE IF NOT EXISTS fuel_purchases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  purchase_date DATE NOT NULL,
  supplier VARCHAR(150) NULL,
  quantity_litre DECIMAL(12,2) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fp_date (purchase_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fuel_issues (
  id INT AUTO_INCREMENT PRIMARY KEY,
  issue_date DATE NOT NULL,
  asset_id INT NULL,
  driver_worker_id INT NULL,
  block_id INT NULL,
  fuel_litre DECIMAL(12,2) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  mileage_reading DECIMAL(12,2) NULL,
  hour_meter DECIMAL(12,2) NULL,
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fi_date (issue_date),
  KEY idx_fi_asset (asset_id),
  CONSTRAINT fk_fi_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single running tank balance (row id = 1).
CREATE TABLE IF NOT EXISTS fuel_tank_balance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  current_litres DECIMAL(14,2) NOT NULL DEFAULT 0,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO fuel_tank_balance (id, current_litres, updated_at)
SELECT 1, 0, NOW() WHERE NOT EXISTS (SELECT 1 FROM fuel_tank_balance WHERE id = 1);

SET FOREIGN_KEY_CHECKS = 1;
