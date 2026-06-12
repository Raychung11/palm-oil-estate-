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
