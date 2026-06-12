-- =============================================================
-- Estate BOS — Phase 7 seed: inventory & asset/fuel permissions
-- Run AFTER database/seed.sql
-- inventory.* already exist in the base seed; this adds asset.* and grants.
-- =============================================================

INSERT INTO permissions (permission_key, permission_group, description) VALUES
  ('asset.view',   'asset', 'View assets & fuel'),
  ('asset.manage', 'asset', 'Manage assets, maintenance & fuel')
ON DUPLICATE KEY UPDATE permission_group = VALUES(permission_group);

-- Keep Super Admin fully granted.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.role_slug = 'super_admin';

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
