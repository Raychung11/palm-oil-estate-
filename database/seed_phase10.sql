-- =============================================================
-- Estate BOS — Phase 10 seed: AI assistant permissions + example questions
-- Run AFTER database/seed.sql
-- =============================================================

INSERT INTO permissions (permission_key, permission_group, description) VALUES
  ('ai.use',    'ai', 'Ask the AI estate assistant'),
  ('ai.manage', 'ai', 'Generate AI insights / summaries')
ON DUPLICATE KEY UPDATE permission_group = VALUES(permission_group);

-- Keep Super Admin fully granted.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.role_slug = 'super_admin';

-- Estate Manager & Assistant Manager: full AI access.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('ai.use', 'ai.manage')
WHERE r.role_slug IN ('estate_manager', 'asst_manager');

-- Supervisor & Finance Viewer: ask questions.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key = 'ai.use'
WHERE r.role_slug IN ('supervisor', 'finance_viewer');

-- Example questions surfaced as suggestion chips.
INSERT INTO ai_queries (question, intent_key, category, sort_order, status) VALUES
  ('Which block has the lowest yield this month?',          'lowest_yield',      'harvest',    1, 'active'),
  ('Which block has the highest yield this month?',         'highest_yield',     'harvest',    2, 'active'),
  ('Which worker is most productive?',                      'productive_worker', 'workers',    3, 'active'),
  ('How much fertilizer was used in a block?',              'fertilizer_block',  'fertilizer', 4, 'active'),
  ('Which vehicle has abnormal fuel usage?',               'abnormal_fuel',     'fuel',       5, 'active'),
  ('Which blocks have not been harvested recently?',        'not_harvested',     'harvest',    6, 'active'),
  ('What is the estimated harvest this month?',             'estimated_harvest', 'harvest',    7, 'active'),
  ('What is the total FFB this month?',                     'total_ffb',         'harvest',    8, 'active'),
  ('How many harvest records are pending approval?',        'pending_approvals', 'harvest',    9, 'active'),
  ('Which items are low in stock?',                         'low_stock',         'inventory', 10, 'active');
