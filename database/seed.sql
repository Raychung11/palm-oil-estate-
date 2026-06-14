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
