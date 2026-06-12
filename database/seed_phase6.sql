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

-- Keep Super Admin fully granted.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.role_slug = 'super_admin';

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
