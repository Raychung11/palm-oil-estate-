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
