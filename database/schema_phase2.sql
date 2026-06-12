-- =============================================================
-- Estate BOS — Phase 2: Estate Master Setup schema
-- Structure: Estate -> Division -> Block -> Plot
-- Run AFTER database/schema.sql
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- Estates
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS estates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  estate_name VARCHAR(150) NOT NULL,
  estate_code VARCHAR(50) NULL,
  location VARCHAR(255) NULL,
  total_acreage DECIMAL(12,2) NULL,
  total_hectare DECIMAL(12,2) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Divisions
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS divisions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  estate_id INT NOT NULL,
  division_name VARCHAR(150) NOT NULL,
  division_code VARCHAR(50) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_division_estate (estate_id),
  CONSTRAINT fk_division_estate FOREIGN KEY (estate_id) REFERENCES estates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Blocks
-- status values: active | attention | critical | scheduled | inactive
-- (mirrors the GIS status colours in the blueprint)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blocks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  estate_id INT NOT NULL,
  division_id INT NOT NULL,
  block_code VARCHAR(50) NOT NULL,
  block_name VARCHAR(150) NULL,
  acreage DECIMAL(12,2) NULL,
  hectare DECIMAL(12,2) NULL,
  palm_count INT NULL,
  planting_year INT NULL,
  palm_age INT NULL,
  soil_type VARCHAR(100) NULL,
  terrain_type VARCHAR(100) NULL,
  gps_lat DECIMAL(10,7) NULL,
  gps_lng DECIMAL(10,7) NULL,
  block_map_image VARCHAR(255) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_block_estate (estate_id),
  KEY idx_block_division (division_id),
  UNIQUE KEY uq_block_code (estate_id, block_code),
  CONSTRAINT fk_block_estate FOREIGN KEY (estate_id) REFERENCES estates(id) ON DELETE CASCADE,
  CONSTRAINT fk_block_division FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Plots (sub-division of a block)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  block_id INT NOT NULL,
  plot_code VARCHAR(50) NOT NULL,
  plot_name VARCHAR(150) NULL,
  acreage DECIMAL(12,2) NULL,
  palm_count INT NULL,
  planting_year INT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  KEY idx_plot_block (block_id),
  CONSTRAINT fk_plot_block FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Block GPS boundary points (optional polygon for the map module)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS block_gps_points (
  id INT AUTO_INCREMENT PRIMARY KEY,
  block_id INT NOT NULL,
  point_order INT NOT NULL DEFAULT 0,
  gps_lat DECIMAL(10,7) NOT NULL,
  gps_lng DECIMAL(10,7) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_gps_block (block_id),
  CONSTRAINT fk_gps_block FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
