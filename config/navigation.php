<?php

return [
    'sections' => [
        [
            'label' => 'Guest Admin',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'icon' => 'dashboard',
                    'tour' => 'nav-dashboard',
                    'routes' => [
                        ['name' => 'admin.dashboard', 'roles' => ['admin', 'owner', 'company']],
                    ],
                    'active' => ['admin.dashboard'],
                ],
                [
                    'label' => 'Guests',
                    'icon' => 'calendar',
                    'tour' => 'nav-calendar',
                    'routes' => [
                        ['name' => 'admin.guests.index', 'roles' => ['admin', 'owner', 'company']],
                    ],
                    'active' => ['admin.guests.*'],
                ],
                [
                    'label' => 'Properties',
                    'icon' => 'properties',
                    'tour' => 'nav-properties',
                    'routes' => [
                        ['name' => 'admin.properties.index', 'roles' => ['admin', 'owner', 'company']],
                    ],
                    'active' => [
                        'admin.properties.*',
                        'admin.instructions.*',
                        'admin.guest-guide.*',
                    ],
                    'collapsible' => true,
                    'property_children' => [
                        'route' => 'admin.properties.index',
                        'roles' => ['admin', 'owner', 'company'],
                        'actions' => [
                            [
                                'label' => 'Check In/Out Details',
                                'route' => 'admin.instructions.show',
                                'active' => ['admin.instructions.*'],
                            ],
                            [
                                'label' => 'Guest Guide',
                                'route' => 'admin.guest-guide.show',
                                'active' => ['admin.guest-guide.*'],
                            ],
                            [
                                'label' => 'Availability',
                                'route' => 'admin.properties.availability.index',
                                'active' => ['admin.properties.availability.*'],
                            ],
                            [
                                'label' => 'Rooms',
                                'route' => 'properties.rooms.index',
                                'active' => ['properties.rooms.*'],
                            ],
                            [
                                'label' => 'Tasks',
                                'route' => 'properties.property-tasks.index',
                                'active' => ['properties.property-tasks.*', 'properties.tasks.*'],
                            ],
                            [
                                'label' => 'Notifications',
                                'route' => 'properties.notifications.settings',
                                'active' => ['properties.notifications.*'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'label' => 'Settings',
            'items' => [
                [
                    'label' => 'Settings',
                    'icon' => 'settings',
                    'tour' => 'nav-settings',
                    'routes' => [
                        ['name' => 'admin.settings.edit', 'roles' => ['admin']],
                    ],
                    'active' => ['admin.settings.*', 'admin.payments.*', 'admin.notices.*'],
                    'collapsible' => true,
                    'children' => [
                        [
                            'label' => 'General',
                            'routes' => [
                                ['name' => 'admin.settings.edit', 'roles' => ['admin']],
                            ],
                            'active' => ['admin.settings.edit'],
                        ],
                        [
                            'label' => 'Legal',
                            'routes' => [
                                ['name' => 'admin.settings.legal.edit', 'roles' => ['admin']],
                            ],
                            'active' => ['admin.settings.legal.*'],
                        ],
                        [
                            'label' => 'Notifications',
                            'routes' => [
                                ['name' => 'admin.settings.notifications.edit', 'roles' => ['admin']],
                            ],
                            'active' => ['admin.settings.notifications.*'],
                        ],
                        [
                            'label' => 'Payments',
                            'routes' => [
                                ['name' => 'admin.payments.index', 'roles' => ['admin', 'manager']],
                            ],
                            'active' => ['admin.payments.*'],
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
            ],
        ],
        [
            'label' => 'Cleaning Ops',
            'items' => [
                [
                    'label' => 'Cleaning',
                    'icon' => 'assignment',
                    'roles' => ['admin', 'owner', 'company'],
                    'active' => [
                        'dashboard',
                        'assignments.*',
                        'calendar.*',
                        'manage.sessions.*',
                        'sessions.*',
                        'properties.*',
                        'resources.*',
                        'admin.videos.*',
                        'users.*',
                        'rooms.*',
                        'tasks.*',
                        'training.*',
                        'reports.training.*',
                        'reports.familiarity.*',
                    ],
                    'collapsible' => true,
                    'children' => [
                        [
                            'label' => 'Cleaning Dashboard',
                            'routes' => [
                                ['name' => 'dashboard', 'roles' => ['admin', 'owner', 'company']],
                            ],
                            'active' => ['dashboard'],
                        ],
                        [
                            'label' => 'Jobs',
                            'routes' => [
                                ['name' => 'manage.sessions.index', 'roles' => ['admin', 'owner', 'company']],
                            ],
                            'active' => ['manage.sessions.index'],
                        ],
                        [
                            'label' => 'New Job',
                            'routes' => [
                                ['name' => 'manage.sessions.create', 'roles' => ['admin', 'owner', 'company']],
                            ],
                            'active' => ['manage.sessions.create'],
                        ],
                        [
                            'label' => 'Rooms',
                            'icon' => 'properties',
                            'routes' => [
                                ['name' => 'rooms.index', 'roles' => ['admin', 'owner', 'company']],
                            ],
                            'active' => ['rooms.*'],
                        ],
                        [
                            'label' => 'Tasks',
                            'icon' => 'assignment',
                            'routes' => [
                                ['name' => 'tasks.index', 'roles' => ['admin', 'owner', 'company']],
                            ],
                            'active' => ['tasks.*'],
                        ],
                        [
                            'label' => 'Video Library',
                            'icon' => 'folder',
                            'routes' => [
                                ['name' => 'admin.videos.index', 'roles' => ['admin', 'owner', 'company']],
                            ],
                            'active' => ['admin.videos.*'],
                        ],
                        [
                            'label' => 'Training Report',
                            'icon' => 'logs',
                            'routes' => [
                                ['name' => 'reports.training.index', 'roles' => ['admin', 'owner', 'company']],
                            ],
                            'active' => ['reports.training.*'],
                        ],
                        [
                            'label' => 'Familiarity Report',
                            'icon' => 'logs',
                            'routes' => [
                                ['name' => 'reports.familiarity.index', 'roles' => ['admin', 'owner', 'company']],
                            ],
                            'active' => ['reports.familiarity.*'],
                        ],
                    ],
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
                    'label' => 'Calendar',
                    'icon' => 'calendar',
                    'roles' => ['housekeeper'],
                    'exclude_roles' => ['admin'],
                    'routes' => [
                        ['name' => 'calendar.index', 'roles' => ['housekeeper']],
                    ],
                    'active' => ['calendar.*'],
                ],
                [
                    'label' => 'Sessions',
                    'icon' => 'assignment',
                    'roles' => ['housekeeper'],
                    'exclude_roles' => ['admin'],
                    'routes' => [
                        ['name' => 'sessions.index', 'roles' => ['housekeeper']],
                    ],
                    'active' => ['sessions.*'],
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
                    'label' => 'Videos',
                    'icon' => 'folder',
                    'roles' => ['housekeeper'],
                    'exclude_roles' => ['admin'],
                    'routes' => [
                        ['name' => 'resources.videos', 'roles' => ['housekeeper']],
                    ],
                    'active' => ['resources.videos'],
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
                    'roles' => ['housekeeper'],
                    'exclude_roles' => ['admin'],
                    'routes' => [
                        ['name' => 'profile.edit', 'roles' => ['housekeeper']],
                    ],
                    'active' => ['profile.*'],
                ],
            ],
        ],
        [
            'label' => 'Administration',
            'items' => [
                [
                    'label' => 'Manage Categories',
                    'icon' => 'categories',
                    'routes' => [
                        ['name' => 'admin.categories.index', 'roles' => ['admin', 'owner', 'company']],
                    ],
                    'active' => ['admin.categories.*'],
                ],
                [
                    'label' => 'Media Library',
                    'icon' => 'folder',
                    'routes' => [
                        ['name' => 'admin.media.index', 'roles' => ['admin', 'owner', 'company']],
                    ],
                    'active' => ['admin.media.*'],
                ],
                [
                    'label' => 'Users',
                    'icon' => 'users',
                    'routes' => [
                        ['name' => 'admin.users.index', 'roles' => ['admin']],
                    ],
                    'active' => ['admin.users.*'],
                ],
                [
                    'label' => 'Early Access Signups',
                    'icon' => 'users',
                    'routes' => [
                        ['name' => 'admin.early-access-leads.index', 'roles' => ['admin']],
                    ],
                    'active' => ['admin.early-access-leads.*'],
                ],
                [
                    'label' => 'Activity Logs',
                    'icon' => 'logs',
                    'routes' => [
                        ['name' => 'admin.logs.index', 'roles' => ['admin', 'manager', 'owner']],
                    ],
                    'active' => ['admin.logs.*', 'activity.*'],
                ],
                [
                    'label' => 'Security',
                    'icon' => 'security',
                    'routes' => [
                        ['name' => 'admin.security', 'roles' => ['admin', 'owner', 'company']],
                    ],
                    'active' => ['admin.security'],
                ],
                [
                    'label' => 'Admin Guide',
                    'icon' => 'guide',
                    'routes' => [
                        ['name' => 'admin.guide', 'roles' => ['admin', 'owner', 'company']],
                    ],
                    'active' => ['admin.guide'],
                ],
            ],
        ],
    ],
];
