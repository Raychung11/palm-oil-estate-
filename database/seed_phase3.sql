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
