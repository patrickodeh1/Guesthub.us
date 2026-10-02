<?php

/*
|--------------------------------------------------------------------------
| Unified admin navigation (Guest Hub + Flip Status)
|--------------------------------------------------------------------------
| Same structure/keys as the previous file: sections > items > children.
| Housekeeper-only items are kept so cleaners' menus keep working.
|
| TEMPORARY block: the Settings item still has children so nothing becomes
| unreachable. Delete 'children' and 'collapsible' from it once the tabbed
| Settings page exists.
*/

$staff = ['admin', 'owner', 'company'];

return [
    'sections' => [
        [
            'label' => 'Main',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'icon' => 'dashboard',
                    'tour' => 'nav-dashboard',
                    'routes' => [
                        ['name' => 'dashboard', 'roles' => $staff],
                    ],
                    'active' => ['admin.dashboard', 'dashboard'],
                ],
                [
                    'label' => 'Calendar',
                    'icon' => 'calendar',
                    'tour' => 'nav-calendar',
                    'roles' => array_merge($staff, ['housekeeper']),
                    'routes' => [
                        ['name' => 'calendar.index', 'roles' => array_merge($staff, ['housekeeper'])],
                    ],
                    'active' => [
                        'calendar.*',
                        'manage.sessions.*',
                        'admin.properties.availability.*',
                    ],
                ],
                [
                    'label' => 'Guest Registrations',
                    'icon' => 'users',
                    'routes' => [
                        ['name' => 'admin.guests.index', 'roles' => $staff],
                    ],
                    'active' => ['admin.guests.*'],
                ],
                [
                    // Not in your written nav list, but your spec has a main
                    // Properties page. Remove this item if that was intentional.
                    'label' => 'Properties',
                    'icon' => 'properties',
                    'tour' => 'nav-properties',
                    'routes' => [
                        ['name' => 'admin.properties.index', 'roles' => $staff],
                    ],
                    'active' => [
                        'admin.properties.*',
                        'admin.instructions.*',
                        'admin.guest-guide.*',
                        'properties.*',
                    ],
                    // No property_children: properties are no longer expanded in the menu.
                ],
                [
                    'label' => 'Jobs',
                    'icon' => 'logs',
                    'tour' => 'nav-jobs',
                    'routes' => [
                        ['name' => 'manage.sessions.index', 'roles' => $staff],
                    ],
                    'active' => ['manage.sessions.*'],
                ],
                [
                    'label' => 'Communications',
                    'icon' => 'contact-guest-services',
                    'roles' => ['admin', 'owner', 'company', 'manager'],
                    'collapsible' => true,
                    'active' => ['admin.notifications.*', 'admin.notices.*'],
                    'children' => [
                        [
                            'label' => 'Notifications',
                            'routes' => [
                                ['name' => 'admin.notifications.index', 'roles' => ['admin', 'owner', 'company', 'manager']],
                            ],
                            'active' => ['admin.notifications.*'],
                        ],
                        [
                            'label' => 'Guest Notices',
                            'routes' => [
                                ['name' => 'admin.notices.index', 'roles' => ['admin', 'manager']],
                            ],
                            'active' => ['admin.notices.*'],
                        ],
                    ],
                ],
                [
                    'label' => 'Media',
                    'icon' => 'folder',
                    'roles' => $staff,
                    'collapsible' => true,
                    'active' => ['admin.media.*', 'admin.categories.*'],
                    'children' => [
                        [
                            'label' => 'Media Library',
                            'routes' => [
                                ['name' => 'admin.media.index', 'roles' => $staff],
                            ],
                            'active' => ['admin.media.*'],
                        ],
                        [
                            'label' => 'Categories',
                            'routes' => [
                                ['name' => 'admin.categories.index', 'roles' => $staff],
                            ],
                            'active' => ['admin.categories.*'],
                        ],
                    ],
                ],
                [
                    'label' => 'Administration',
                    'icon' => 'logs',
                    'roles' => ['admin', 'owner', 'company', 'manager'],
                    'collapsible' => true,
                    'active' => [
                        'admin.users.*',
                        'users.*',
                        'admin.early-access-leads.*',
                        'admin.logs.*',
                        'activity.*',
                        'admin.security',
                        'admin.guide',
                        'reports.training.*',
                        'reports.familiarity.*',
                    ],
                    'children' => [
                        [
                            'label' => 'Users',
                            'routes' => [
                                ['name' => 'admin.users.index', 'roles' => ['admin']],
                            ],
                            'active' => ['admin.users.*', 'users.*'],
                        ],
                        [
                            'label' => 'Early Access Signups',
                            'routes' => [
                                ['name' => 'admin.early-access-leads.index', 'roles' => ['admin']],
                            ],
                            'active' => ['admin.early-access-leads.*'],
                        ],
                        [
                            'label' => 'Activity Logs',
                            'routes' => [
                                ['name' => 'admin.logs.index', 'roles' => ['admin', 'manager', 'owner']],
                            ],
                            'active' => ['admin.logs.*', 'activity.*'],
                        ],
                        [
                            'label' => 'Security',
                            'routes' => [
                                ['name' => 'admin.security', 'roles' => ['admin', 'owner', 'company']],
                            ],
                            'active' => ['admin.security'],
                        ],
                        [
                            'label' => 'Admin Guide',
                            'routes' => [
                                ['name' => 'admin.guide', 'roles' => $staff],
                            ],
                            'active' => ['admin.guide'],
                        ],
                    ],
                ],
            ],
        ],

        // Housekeeper-only menu (unchanged behavior). Admin-facing Cleaning,
        // Training, Jobs, New Job, Reports links were removed per the spec.
        [
            'label' => 'My Work',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'icon' => 'dashboard',
                    'roles' => ['housekeeper'],
                    'exclude_roles' => ['admin'],
                    'routes' => [
                        ['name' => 'dashboard', 'roles' => ['housekeeper']],
                    ],
                    'active' => ['dashboard'],
                ],
                [
                    'label' => 'My Jobs',
                    'icon' => 'assignment',
                    'roles' => ['housekeeper'],
                    'exclude_roles' => ['admin'],
                    'routes' => [
                        ['name' => 'assignments.index', 'roles' => ['housekeeper']],
                    ],
                    'active' => ['assignments.*', 'sessions.*'],
                ],
                [
                    'label' => 'Training',
                    'icon' => 'guide',
                    'roles' => ['housekeeper'],
                    'exclude_roles' => ['admin'],
                    'routes' => [
                        ['name' => 'training.index', 'roles' => ['housekeeper']],
                    ],
                    'active' => ['training.*'],
                ],
                [
                    'label' => 'Videos',
                    'icon' => 'guide',
                    'roles' => ['housekeeper'],
                    'exclude_roles' => ['admin'],
                    'routes' => [
                        ['name' => 'resources.videos', 'roles' => ['housekeeper']],
                    ],
                    'active' => ['resources.videos'],
                ],
                [
                    'label' => 'Photos',
                    'icon' => 'image',
                    'roles' => ['housekeeper'],
                    'exclude_roles' => ['admin'],
                    'routes' => [
                        ['name' => 'resources.photos', 'roles' => ['housekeeper']],
                    ],
                    'active' => ['resources.photos'],
                ],
                [
                    'label' => 'Guides',
                    'icon' => 'guide',
                    'roles' => ['housekeeper'],
                    'exclude_roles' => ['admin'],
                    'routes' => [
                        ['name' => 'resources.guides', 'roles' => ['housekeeper']],
                    ],
                    'active' => ['resources.guides'],
                ],
                [
                    'label' => 'My Account',
                    'icon' => 'users',
                    'roles' => ['housekeeper', 'manager', 'staff', 'viewer'],
                    'exclude_roles' => ['admin'],
                    'routes' => [
                        ['name' => 'profile.edit', 'roles' => ['housekeeper', 'manager', 'staff', 'viewer']],
                    ],
                    'active' => ['profile.*'],
                ],
            ],
        ],

        // Settings gear: put this section last / pinned to the bottom in the sidebar Blade.
        [
            'label' => '',
            'items' => [
                [
                    'label' => 'Settings',
                    'icon' => 'settings',
                    'tour' => 'nav-settings',
                    'routes' => [
                        ['name' => 'admin.settings.edit', 'roles' => ['admin']],
                    ],
                    'active' => ['admin.settings.*', 'admin.payments.*', 'admin.videos.*', 'rooms.*', 'tasks.*', 'resources.*'],

                    // TEMPORARY: remove once the tabbed Settings page exists.
                    'collapsible' => true,
                    'children' => [
                        ['label' => 'General', 'routes' => [['name' => 'admin.settings.edit', 'roles' => ['admin']]], 'active' => ['admin.settings.edit']],
                        ['label' => 'Cleaning Rooms', 'routes' => [['name' => 'rooms.index', 'roles' => ['admin', 'owner', 'company']]], 'active' => ['rooms.*']],
                        ['label' => 'Cleaning Tasks', 'routes' => [['name' => 'tasks.index', 'roles' => ['admin', 'owner', 'company']]], 'active' => ['tasks.*']],
                        ['label' => 'Video Library', 'routes' => [['name' => 'admin.videos.index', 'roles' => ['admin', 'owner', 'company']]], 'active' => ['admin.videos.*']],
                        ['label' => 'Notification Settings', 'routes' => [['name' => 'admin.settings.notifications.edit', 'roles' => ['admin']]], 'active' => ['admin.settings.notifications.*']],
                        ['label' => 'Payments', 'routes' => [['name' => 'admin.payments.index', 'roles' => ['admin', 'manager']]], 'active' => ['admin.payments.*']],
                        ['label' => 'Legal', 'routes' => [['name' => 'admin.settings.legal.edit', 'roles' => ['admin']]], 'active' => ['admin.settings.legal.*']],
                    ],
                ],
            ],
        ],
    ],
];
