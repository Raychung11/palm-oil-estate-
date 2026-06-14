-- =============================================================
-- Estate BOS — Phase 8: Mill Delivery Management schema
-- Run AFTER database/schema_phase7.sql
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS mills (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mill_name VARCHAR(150) NOT NULL,
  location VARCHAR(255) NULL,
  contact VARCHAR(150) NULL,
  default_price_per_tonne DECIMAL(12,2) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: pending | reconciled
CREATE TABLE IF NOT EXISTS mill_deliveries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  delivery_date DATE NOT NULL,
  mill_id INT NULL,
  vehicle_asset_id INT NULL,
  driver_worker_id INT NULL,
  time_out TIME NULL,
  time_in TIME NULL,
  gross_weight_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
  tare_weight_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
  net_weight_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
  price_per_tonne DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  oer DECIMAL(6,2) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'pending',
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_md_date (delivery_date),
  KEY idx_md_status (status),
  CONSTRAINT fk_md_mill   FOREIGN KEY (mill_id)          REFERENCES mills(id)   ON DELETE SET NULL,
  CONSTRAINT fk_md_asset  FOREIGN KEY (vehicle_asset_id) REFERENCES assets(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Harvest records consolidated into a delivery trip.
CREATE TABLE IF NOT EXISTS mill_delivery_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mill_delivery_id INT NOT NULL,
  harvest_record_id INT NOT NULL,
  ffb_weight_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mdi_delivery (mill_delivery_id),
  KEY idx_mdi_harvest (harvest_record_id),
  CONSTRAINT fk_mdi_delivery FOREIGN KEY (mill_delivery_id)  REFERENCES mill_deliveries(id) ON DELETE CASCADE,
  CONSTRAINT fk_mdi_harvest  FOREIGN KEY (harvest_record_id) REFERENCES harvest_records(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS weighbridge_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mill_delivery_id INT NOT NULL,
  ticket_no VARCHAR(100) NULL,
  file_path VARCHAR(255) NULL,
  gross_weight_kg DECIMAL(12,2) NULL,
  tare_weight_kg DECIMAL(12,2) NULL,
  net_weight_kg DECIMAL(12,2) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_wt_delivery (mill_delivery_id),
  CONSTRAINT fk_wt_delivery FOREIGN KEY (mill_delivery_id) REFERENCES mill_deliveries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
