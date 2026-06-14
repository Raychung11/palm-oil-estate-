-- =============================================================
-- Estate BOS — COMBINED SCHEMA (all phases, in order)
-- Import this ONCE into your database, then import seed_all.sql.
-- Generated from schema.sql + schema_phase2..10.sql
-- =============================================================


-- >>>>>>>>>> schema.sql >>>>>>>>>>

-- =============================================================
-- Estate BOS — Palm Oil Estate Business Operating System
-- Phase 1: System Foundation schema
-- Engine: MySQL / MariaDB (Hostinger shared hosting compatible)
-- Naming convention: snake_case
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- Roles
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(100) NOT NULL,
  role_slug VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Permissions
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  permission_key VARCHAR(100) NOT NULL UNIQUE,
  permission_group VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Role <-> Permission matrix
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS role_permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_role_permission (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Users
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(50) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_by INT NULL,
  updated_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- User activity / audit logs
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  module VARCHAR(100) NULL,
  description VARCHAR(255) NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_user (user_id),
  KEY idx_activity_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- >>>>>>>>>> schema_phase2.sql >>>>>>>>>>

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

-- >>>>>>>>>> schema_phase3.sql >>>>>>>>>>

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

-- >>>>>>>>>> schema_phase4.sql >>>>>>>>>>

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

-- >>>>>>>>>> schema_phase5.sql >>>>>>>>>>

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

-- >>>>>>>>>> schema_phase6.sql >>>>>>>>>>

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

-- >>>>>>>>>> schema_phase7.sql >>>>>>>>>>

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

-- >>>>>>>>>> schema_phase8.sql >>>>>>>>>>

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

-- >>>>>>>>>> schema_phase9.sql >>>>>>>>>>

-- =============================================================
-- Estate BOS — Phase 9: Estate Costing schema
-- Run AFTER database/schema_phase8.sql
--
-- Most costs are derived live from the operational modules (fertilizer,
-- chemical, fuel, maintenance, mill revenue). These tables capture the
-- manual entries (labour, overheads, ad-hoc revenue) and an optional
-- monthly summary snapshot that the cron job can populate.
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Manual cost entries (labour, overheads, misc) not captured elsewhere.
CREATE TABLE IF NOT EXISTS estate_cost_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entry_date DATE NOT NULL,
  category VARCHAR(100) NOT NULL,
  block_id INT NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ece_date (entry_date),
  KEY idx_ece_block (block_id),
  CONSTRAINT fk_ece_block FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manual revenue entries (adjustments / non-mill income).
CREATE TABLE IF NOT EXISTS estate_revenue_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entry_date DATE NOT NULL,
  source VARCHAR(100) NOT NULL,
  block_id INT NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ere_date (entry_date),
  CONSTRAINT fk_ere_block FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional monthly snapshot (populated by cron/monthly_summary.php).
CREATE TABLE IF NOT EXISTS monthly_estate_summary (
  id INT AUTO_INCREMENT PRIMARY KEY,
  summary_month CHAR(7) NOT NULL,
  ffb_tonnes DECIMAL(14,2) NOT NULL DEFAULT 0,
  revenue DECIMAL(16,2) NOT NULL DEFAULT 0,
  total_cost DECIMAL(16,2) NOT NULL DEFAULT 0,
  profit DECIMAL(16,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_summary_month (summary_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- >>>>>>>>>> schema_phase10.sql >>>>>>>>>>

-- =============================================================
-- Estate BOS — Phase 10: AI Estate Assistant schema
-- Run AFTER database/schema_phase9.sql
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Predefined / example questions surfaced as suggestion chips.
CREATE TABLE IF NOT EXISTS ai_queries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question VARCHAR(255) NOT NULL,
  intent_key VARCHAR(100) NULL,
  category VARCHAR(100) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log of questions asked.
CREATE TABLE IF NOT EXISTS ai_query_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  question VARCHAR(500) NOT NULL,
  intent_key VARCHAR(100) NULL,
  answered TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_aql_user (user_id),
  KEY idx_aql_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generated insights / management summaries.
CREATE TABLE IF NOT EXISTS ai_insights (
  id INT AUTO_INCREMENT PRIMARY KEY,
  insight_type VARCHAR(100) NOT NULL DEFAULT 'monthly_summary',
  period VARCHAR(50) NULL,
  title VARCHAR(255) NOT NULL,
  content MEDIUMTEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ai_type (insight_type),
  KEY idx_ai_period (period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
