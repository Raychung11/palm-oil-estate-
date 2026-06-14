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
