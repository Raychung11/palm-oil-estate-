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
