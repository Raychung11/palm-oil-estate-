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
    'Inventory' => [
        'inventory.view'    => 'View inventory',
        'inventory.approve' => 'Approve inventory movements',
    ],
    'Reports' => [
        'report.view'   => 'View reports',
        'report.export' => 'Export reports',
    ],
];
