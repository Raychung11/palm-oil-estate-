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
