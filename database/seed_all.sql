-- =============================================================
-- Estate BOS — COMBINED SEED (all phases, in order)
-- Import this AFTER install_all.sql.
-- Safe to re-run (ON DUPLICATE KEY UPDATE / INSERT IGNORE).
-- Default login: admin@estate-bos.local / Admin@123
-- =============================================================


-- >>>>>>>>>> seed.sql >>>>>>>>>>

-- =============================================================
-- Estate BOS — Seed data for Phase 1 foundation
-- Run AFTER schema.sql
--
-- Default Super Admin login:
--   email    : admin@estate-bos.local
--   password : Admin@123
-- (Change the password immediately after first login.)
-- =============================================================

-- -------------------------------------------------------------
-- Roles (from blueprint Section 3 — System Roles)
-- -------------------------------------------------------------
INSERT INTO roles (role_name, role_slug, description) VALUES
  ('Super Admin',            'super_admin',     'Full system access'),
  ('Estate Manager',         'estate_manager',  'Estate-wide management access'),
  ('Assistant Manager',      'asst_manager',    'Assists estate manager'),
  ('Supervisor',             'supervisor',      'Field operation supervisor'),
  ('Storekeeper',            'storekeeper',     'Inventory and store control'),
  ('HR/Admin',               'hr_admin',        'Worker and HR administration'),
  ('Finance Viewer',         'finance_viewer',  'Read-only financial dashboards'),
  ('Mechanic',               'mechanic',        'Vehicle and machinery upkeep'),
  ('Worker Mobile User',     'worker_mobile',   'Field mobile data entry'),
  ('Auditor / Compliance',   'auditor',         'Read-only compliance access')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name);

-- -------------------------------------------------------------
-- Permissions (mirror of config/permissions.php)
-- -------------------------------------------------------------
INSERT INTO permissions (permission_key, permission_group, description) VALUES
  ('user.view',        'users',     'View users'),
  ('user.create',      'users',     'Create users'),
  ('user.edit',        'users',     'Edit users'),
  ('user.manage',      'users',     'Activate/deactivate users'),
  ('role.view',        'roles',     'View roles'),
  ('role.manage',      'roles',     'Manage roles and permissions'),
  ('estate.view',      'estate',    'View estate structure'),
  ('estate.manage',    'estate',    'Manage estate structure'),
  ('harvest.view',     'harvest',   'View harvest records'),
  ('harvest.create',   'harvest',   'Create harvest records'),
  ('harvest.approve',  'harvest',   'Approve harvest records'),
  ('worker.view',      'workers',   'View workers'),
  ('worker.manage',    'workers',   'Manage workers'),
  ('inventory.view',   'inventory', 'View inventory'),
  ('inventory.approve','inventory', 'Approve inventory movements'),
  ('report.view',      'reports',   'View reports'),
  ('report.export',    'reports',   'Export reports')
ON DUPLICATE KEY UPDATE permission_group = VALUES(permission_group);

-- -------------------------------------------------------------
-- Grant every permission to Super Admin
-- -------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_slug = 'super_admin';

-- -------------------------------------------------------------
-- Default Super Admin user
-- password_hash below is for 'Admin@123' (bcrypt)
-- -------------------------------------------------------------
INSERT INTO users (name, email, password_hash, role_id, status)
SELECT 'Super Admin',
       'admin@estate-bos.local',
       '$2y$12$emCgcuCEfTrGnUvaCO75weOikZmrhhhoCnNbajjp66.VwXiw9A2FK',
       r.id,
       'active'
FROM roles r
WHERE r.role_slug = 'super_admin'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@estate-bos.local');

-- >>>>>>>>>> seed_phase2.sql >>>>>>>>>>

-- =============================================================
-- Estate BOS — Phase 2 seed: grant estate permissions to manager roles
-- Run AFTER database/seed.sql (and schema_phase2.sql)
-- Super Admin already has every permission via the base seed.
-- =============================================================

-- Estate Manager & Assistant Manager: full estate master access.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('estate.view', 'estate.manage')
WHERE r.role_slug IN ('estate_manager', 'asst_manager');

-- Supervisor & Auditor: read-only estate access.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key = 'estate.view'
WHERE r.role_slug IN ('supervisor', 'auditor');

-- >>>>>>>>>> seed_phase3.sql >>>>>>>>>>

-- =============================================================
-- Estate BOS — Phase 3 seed: grant harvest permissions to roles
-- Run AFTER database/seed.sql
-- Super Admin already has every permission via the base seed.
-- =============================================================

-- Estate Manager, Assistant Manager, Supervisor: full harvest workflow
-- (view, create and approve).
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('harvest.view', 'harvest.create', 'harvest.approve')
WHERE r.role_slug IN ('estate_manager', 'asst_manager', 'supervisor');

-- Worker Mobile User: field data entry (view + create, no approval).
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('harvest.view', 'harvest.create')
WHERE r.role_slug = 'worker_mobile';

-- Finance Viewer & Auditor: read-only.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key = 'harvest.view'
WHERE r.role_slug IN ('finance_viewer', 'auditor');

-- >>>>>>>>>> seed_phase4.sql >>>>>>>>>>

-- =============================================================
-- Estate BOS — Phase 4 seed: grant worker permissions to roles
-- Run AFTER database/seed.sql
-- Super Admin already has every permission via the base seed.
-- =============================================================

-- Estate Manager, Assistant Manager, HR/Admin: full worker management.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('worker.view', 'worker.manage')
WHERE r.role_slug IN ('estate_manager', 'asst_manager', 'hr_admin');

-- Supervisor: manage attendance and worker records.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('worker.view', 'worker.manage')
WHERE r.role_slug = 'supervisor';

-- Finance Viewer & Auditor: read-only worker access.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key = 'worker.view'
WHERE r.role_slug IN ('finance_viewer', 'auditor');

-- >>>>>>>>>> seed_phase5.sql >>>>>>>>>>

-- =============================================================
-- Estate BOS — Phase 5 seed: field-task permissions
-- Run AFTER database/seed.sql
-- Inserts the new task.* permissions, grants Super Admin everything,
-- then grants sensible defaults to the other roles.
-- =============================================================

-- New permissions for the field-task module.
INSERT INTO permissions (permission_key, permission_group, description) VALUES
  ('task.view',    'tasks', 'View field tasks'),
  ('task.create',  'tasks', 'Create / edit field tasks'),
  ('task.manage',  'tasks', 'Start / complete tasks'),
  ('task.approve', 'tasks', 'Approve / reject tasks')
ON DUPLICATE KEY UPDATE permission_group = VALUES(permission_group);

-- Grant the new permissions to Super Admin.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('task.view', 'task.create', 'task.manage', 'task.approve')
WHERE r.role_slug = 'super_admin';

-- Estate Manager & Assistant Manager: full task workflow.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('task.view', 'task.create', 'task.manage', 'task.approve')
WHERE r.role_slug IN ('estate_manager', 'asst_manager');

-- Supervisor: create, start and complete tasks (approval sits with managers).
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('task.view', 'task.create', 'task.manage')
WHERE r.role_slug = 'supervisor';

-- Worker Mobile User: view and update progress on assigned tasks.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('task.view', 'task.manage')
WHERE r.role_slug = 'worker_mobile';

-- Auditor: read-only.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key = 'task.view'
WHERE r.role_slug = 'auditor';

-- >>>>>>>>>> seed_phase6.sql >>>>>>>>>>

-- =============================================================
-- Estate BOS — Phase 6 seed: fertilizer & chemical permissions
-- Run AFTER database/seed.sql
-- =============================================================

INSERT INTO permissions (permission_key, permission_group, description) VALUES
  ('fertilizer.view',   'fertilizer', 'View fertilizer'),
  ('fertilizer.manage', 'fertilizer', 'Manage fertilizer stock & applications'),
  ('chemical.view',     'chemical',   'View chemicals'),
  ('chemical.manage',   'chemical',   'Manage chemical stock & spraying')
ON DUPLICATE KEY UPDATE permission_group = VALUES(permission_group);

-- Grant the new permissions to Super Admin.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('fertilizer.view', 'fertilizer.manage', 'chemical.view', 'chemical.manage')
WHERE r.role_slug = 'super_admin';

-- Estate Manager, Assistant Manager, Storekeeper: full management.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('fertilizer.view', 'fertilizer.manage', 'chemical.view', 'chemical.manage')
WHERE r.role_slug IN ('estate_manager', 'asst_manager', 'storekeeper');

-- Supervisor: record applications and spraying.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('fertilizer.view', 'fertilizer.manage', 'chemical.view', 'chemical.manage')
WHERE r.role_slug = 'supervisor';

-- Finance Viewer & Auditor: read-only.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('fertilizer.view', 'chemical.view')
WHERE r.role_slug IN ('finance_viewer', 'auditor');

-- >>>>>>>>>> seed_phase7.sql >>>>>>>>>>

-- =============================================================
-- Estate BOS — Phase 7 seed: inventory & asset/fuel permissions
-- Run AFTER database/seed.sql
-- inventory.* already exist in the base seed; this adds asset.* and grants.
-- =============================================================

INSERT INTO permissions (permission_key, permission_group, description) VALUES
  ('asset.view',   'asset', 'View assets & fuel'),
  ('asset.manage', 'asset', 'Manage assets, maintenance & fuel')
ON DUPLICATE KEY UPDATE permission_group = VALUES(permission_group);

-- Grant the new permissions to Super Admin.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('asset.view', 'asset.manage')
WHERE r.role_slug = 'super_admin';

-- Estate Manager & Assistant Manager: full inventory + asset/fuel.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('inventory.view', 'inventory.approve', 'asset.view', 'asset.manage')
WHERE r.role_slug IN ('estate_manager', 'asst_manager');

-- Storekeeper: full inventory, view assets/fuel.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('inventory.view', 'inventory.approve', 'asset.view')
WHERE r.role_slug = 'storekeeper';

-- Mechanic: manage assets & fuel, view inventory.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('asset.view', 'asset.manage', 'inventory.view')
WHERE r.role_slug = 'mechanic';

-- Finance Viewer & Auditor: read-only.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('inventory.view', 'asset.view')
WHERE r.role_slug IN ('finance_viewer', 'auditor');

-- >>>>>>>>>> seed_phase8.sql >>>>>>>>>>

-- =============================================================
-- Estate BOS — Phase 8 seed: mill-delivery permissions
-- Run AFTER database/seed.sql
-- =============================================================

INSERT INTO permissions (permission_key, permission_group, description) VALUES
  ('mill.view',   'mill', 'View mill deliveries'),
  ('mill.manage', 'mill', 'Manage mill deliveries')
ON DUPLICATE KEY UPDATE permission_group = VALUES(permission_group);

-- Grant the new permissions to Super Admin.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('mill.view', 'mill.manage')
WHERE r.role_slug = 'super_admin';

-- Estate Manager, Assistant Manager, Supervisor: full delivery management.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('mill.view', 'mill.manage')
WHERE r.role_slug IN ('estate_manager', 'asst_manager', 'supervisor');

-- Finance Viewer & Auditor: read-only.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key = 'mill.view'
WHERE r.role_slug IN ('finance_viewer', 'auditor');

-- >>>>>>>>>> seed_phase9.sql >>>>>>>>>>

-- =============================================================
-- Estate BOS — Phase 9 seed: costing & report permissions
-- Run AFTER database/seed.sql
-- report.view / report.export already exist in the base seed.
-- =============================================================

INSERT INTO permissions (permission_key, permission_group, description) VALUES
  ('costing.view',   'costing', 'View costing dashboard'),
  ('costing.manage', 'costing', 'Manage cost & revenue entries')
ON DUPLICATE KEY UPDATE permission_group = VALUES(permission_group);

-- Grant the new permissions to Super Admin.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('costing.view', 'costing.manage')
WHERE r.role_slug = 'super_admin';

-- Estate Manager & Assistant Manager: full costing + reports.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('costing.view', 'costing.manage', 'report.view', 'report.export')
WHERE r.role_slug IN ('estate_manager', 'asst_manager');

-- Finance Viewer: view costing + reports + export.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('costing.view', 'report.view', 'report.export')
WHERE r.role_slug = 'finance_viewer';

-- Auditor: read-only reports.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('report.view', 'costing.view')
WHERE r.role_slug = 'auditor';

-- >>>>>>>>>> seed_phase10.sql >>>>>>>>>>

-- =============================================================
-- Estate BOS — Phase 10 seed: AI assistant permissions + example questions
-- Run AFTER database/seed.sql
-- =============================================================

INSERT INTO permissions (permission_key, permission_group, description) VALUES
  ('ai.use',    'ai', 'Ask the AI estate assistant'),
  ('ai.manage', 'ai', 'Generate AI insights / summaries')
ON DUPLICATE KEY UPDATE permission_group = VALUES(permission_group);

-- Grant the new permissions to Super Admin.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key IN ('ai.use', 'ai.manage')
WHERE r.role_slug = 'super_admin';

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
