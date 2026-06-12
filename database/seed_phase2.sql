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
