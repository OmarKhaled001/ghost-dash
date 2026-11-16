<?php

declare(strict_types=1);

return [

    'navigation' => [
        'access' => 'Access control',
        'people' => 'People',
        'system' => 'System',
    ],

    'auth' => [
        'deactivated' => [
            'title' => 'Your account has been deactivated',
            'body' => 'You have been signed out. Contact an administrator if you think this is a mistake.',
        ],
    ],

    'fields' => [
        'name' => 'Name',
        'email' => 'Email address',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'avatar' => 'Avatar',
        'roles' => 'Roles',
        'is_active' => 'Active',
        'email_verified' => 'Email verified',
        'app_authentication' => 'Authenticator app',
        'email_authentication' => 'Email codes',
        'last_login_at' => 'Last sign-in',
        'last_login_ip' => 'Last sign-in IP',
        'created_at' => 'Created',
        'guard_name' => 'Guard',
        'avatar_path' => 'Avatar',
        'email_verified_at' => 'Email verified at',
        'app_name' => 'Application name',
        'logo_path' => 'Logo',
        'favicon_path' => 'Favicon',
        'default_locale' => 'Default language',
        'timezone' => 'Time zone',
        'contact_email' => 'Contact email',
    ],

    'admins' => [
        'label' => 'admin',
        'plural' => 'Admins',
        'never_signed_in' => 'Never',
        'sections' => [
            'account' => 'Account',
            'access' => 'Access',
            'activity' => 'Sign-in activity',
        ],
        'hints' => [
            'password_unchanged' => 'Leave empty to keep the current password.',
            'roles' => 'An admin without a role cannot sign in.',
            'is_active' => 'Inactive admins cannot sign in and are signed out immediately.',
        ],
        'actions' => [
            'activate' => 'Activate',
            'deactivate' => 'Deactivate',
            'deactivate_confirmation' => 'This admin will be signed out and will not be able to sign in again until reactivated.',
        ],
        'notifications' => [
            'activated' => 'Admin activated',
            'deactivated' => 'Admin deactivated',
        ],
    ],

    'users' => [
        'label' => 'user',
        'plural' => 'Users',
        'hints' => [
            'email_verified' => 'Turn off to require the user to confirm their address again.',
        ],
    ],

    'guard_rails' => [
        'title' => 'That change is not allowed',
        'cannot_delete_yourself' => 'You cannot delete your own account.',
        'cannot_deactivate_yourself' => 'You cannot deactivate your own account.',
        'last_super_admin' => 'This is the last active super admin. Make someone else a super admin first.',
        'super_admin_only' => 'Only a super admin can create, edit or assign super admins.',
        'super_admin_role_is_fixed' => 'The super admin role always holds every permission and cannot be changed or deleted.',
        'role_in_use' => 'This role is still assigned to admins. Move them to another role first.',
        'cannot_grant_permissions_you_lack' => 'You cannot grant permissions that you do not hold yourself.',
        'higher_authority' => 'This admin holds permissions that you do not, so you cannot change their account.',
        'unknown_role' => 'One of the selected roles does not exist.',
        'unknown_permission' => 'One of the selected permissions does not exist.',
    ],

    // Labels for the custom permissions on the role form.
    'permissions' => [
        'view_telescope' => 'Open Telescope',
        'view_activity_log' => 'View the activity log',
        'manage_settings' => 'Manage settings',
        'manage_backups' => 'Manage backups',
        'view_health' => 'View health checks',
    ],

    'activity' => [
        'label' => 'activity',
        'plural' => 'Activity log',
        'timeline' => 'Activity',
        'system' => 'System',
        'columns' => [
            'when' => 'When',
            'log' => 'Log',
            'event' => 'Event',
            'subject' => 'Record',
            'causer' => 'Done by',
            'property' => 'Detail',
            'value' => 'Value',
        ],
        'filters' => [
            'causer_type' => 'Done by (type)',
            'subject_type' => 'Record type',
            'from' => 'From',
            'until' => 'Until',
        ],
        'sections' => [
            'changes' => 'Changes',
            'details' => 'Details',
        ],
        'diff' => [
            'attribute' => 'Field',
            'old' => 'Before',
            'new' => 'After',
        ],
        'values' => [
            'yes' => 'Yes',
            'no' => 'No',
        ],
        'logs' => [
            'admins' => 'Admins',
            'users' => 'Users',
            'roles' => 'Roles',
            'authentication' => 'Authentication',
            'settings' => 'Settings',
            'backups' => 'Backups',
        ],
        'events' => [
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'roles_synced' => 'Roles changed',
            'permissions_synced' => 'Permissions changed',
            'registered' => 'Registered',
            'login' => 'Signed in',
            'logout' => 'Signed out',
            'failed_login' => 'Failed sign-in',
            'lockout' => 'Locked out',
            'password_reset' => 'Password reset',
            'email_verified' => 'Email verified',
            'mfa_enabled' => 'Multi-factor enabled',
            'mfa_disabled' => 'Multi-factor disabled',
            'requested' => 'Requested',
            'downloaded' => 'Downloaded',
        ],
        'properties' => [
            'guard' => 'Guard',
            'ip' => 'IP address',
            'user_agent' => 'Browser',
            'email' => 'Email address',
            'provider' => 'Method',
            'attached' => 'Added',
            'detached' => 'Removed',
            'file' => 'File',
            'only_database' => 'Database only',
            'group' => 'Settings tab',
        ],
    ],

    'settings' => [
        'title' => 'Settings',
        'save' => 'Save changes',
        'saved' => 'Saved',
    ],

    'backups' => [
        'title' => 'Backups',
        'columns' => [
            'name' => 'File',
            'size' => 'Size',
            'created_at' => 'Created',
        ],
        'empty' => [
            'heading' => 'No backups yet',
            'description' => 'Backups run every night. Archives on the ":disk" disk appear here.',
        ],
        'actions' => [
            'create' => 'Back up now',
            'create_database_only' => 'Database only',
            'create_confirmation' => 'The backup runs in the background and appears in this list when it is finished. A queue worker must be running.',
            'download' => 'Download',
            'delete' => 'Delete',
        ],
        'notifications' => [
            'requested' => [
                'title' => 'Backup started',
                'body' => 'It will appear in the list when it is finished.',
            ],
            'deleted' => 'Backup deleted',
        ],
    ],

    'health' => [
        'title' => 'Health',
        'last_ran' => 'Last checked :time.',
        'never_ran' => 'The checks have not run yet. They run every minute once the scheduler is set up, or use the button above.',
        'actions' => [
            'refresh' => 'Check now',
        ],
        'notifications' => [
            'refreshed' => 'Health checks finished',
        ],
        'statuses' => [
            'ok' => 'OK',
            'warning' => 'Warning',
            'failed' => 'Failed',
            'crashed' => 'Crashed',
            'skipped' => 'Skipped',
        ],
        'checks' => [
            'database' => 'Database connection',
            'database_connection_count' => 'Database connections',
            'cache' => 'Cache',
            'queue' => 'Queue worker',
            'schedule' => 'Scheduler',
            'disk_space' => 'Disk space',
            'debug_mode' => 'Debug mode',
            'environment' => 'Environment',
            'optimized_app' => 'Optimised application',
        ],
    ],

    'widgets' => [
        'stats' => [
            'users_today' => ':count registered today',
            'admins_active' => ':count active',
        ],
    ],

];
