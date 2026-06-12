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

-- Keep Super Admin fully granted (covers the new keys too).
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.role_slug = 'super_admin';

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
