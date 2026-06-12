<?php
/**
 * config/permissions.php
 * Master list of permission keys grouped by module.
 *
 * Keep this in sync with the `permissions` table (database/seed.sql).
 * The permission matrix UI reads from here so new keys appear automatically.
 */

return [
    'Users' => [
        'user.view'   => 'View users',
        'user.create' => 'Create users',
        'user.edit'   => 'Edit users',
        'user.manage' => 'Activate / deactivate users',
    ],
    'Roles' => [
        'role.view'   => 'View roles',
        'role.manage' => 'Manage roles and permissions',
    ],
    'Estate' => [
        'estate.view'   => 'View estate structure',
        'estate.manage' => 'Manage estate structure',
    ],
    'Harvest' => [
        'harvest.view'    => 'View harvest records',
        'harvest.create'  => 'Create harvest records',
        'harvest.approve' => 'Approve harvest records',
    ],
    'Workers' => [
        'worker.view'   => 'View workers',
        'worker.manage' => 'Manage workers',
    ],
    'Tasks' => [
        'task.view'    => 'View field tasks',
        'task.create'  => 'Create / edit field tasks',
        'task.manage'  => 'Start / complete tasks',
        'task.approve' => 'Approve / reject tasks',
    ],
    'Fertilizer' => [
        'fertilizer.view'   => 'View fertilizer',
        'fertilizer.manage' => 'Manage fertilizer stock & applications',
    ],
    'Chemical' => [
        'chemical.view'   => 'View chemicals',
        'chemical.manage' => 'Manage chemical stock & spraying',
    ],
    'Inventory' => [
        'inventory.view'    => 'View inventory',
        'inventory.approve' => 'Manage / approve inventory movements',
    ],
    'Assets & Fuel' => [
        'asset.view'   => 'View assets & fuel',
        'asset.manage' => 'Manage assets, maintenance & fuel',
    ],
    'Mill Delivery' => [
        'mill.view'   => 'View mill deliveries',
        'mill.manage' => 'Manage mill deliveries',
    ],
    'Reports' => [
        'report.view'   => 'View reports',
        'report.export' => 'Export reports',
    ],
    'Costing' => [
        'costing.view'   => 'View costing dashboard',
        'costing.manage' => 'Manage cost & revenue entries',
    ],
    'AI Assistant' => [
        'ai.use'    => 'Ask the AI estate assistant',
        'ai.manage' => 'Generate AI insights / summaries',
    ],
];
