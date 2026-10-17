<?php

return [
    'actions' => [
        'group' => 'Views',
        'save' => [
            'label' => 'Save current view',
            'notification' => 'View saved',
        ],
        'update' => [
            'label' => 'Update ":name" with the current view',
            'notification' => 'View updated',
        ],
        'reset' => [
            'label' => 'Clear view',
        ],
        'manage' => [
            'label' => 'Manage views',
            'description' => 'Rename, reorder, set the default or delete the views you no longer use.',
            'submit' => 'Save changes',
            'notification' => 'Views updated',
        ],
        'export' => [
            'label' => 'Export views',
        ],
        'import' => [
            'label' => 'Import views',
            'notification' => 'Views imported: :count',
            'invalid' => 'The file is not a valid views export.',
        ],
    ],
    'fields' => [
        'name' => 'Name',
        'icon' => 'Icon',
        'color' => 'Color',
        'is_default' => 'Open by default',
        'is_public' => 'Share with my team',
        'as_preset' => 'Save as a locked preset for everyone',
        'file' => 'JSON file',
    ],
    'colors' => [
        'primary' => 'Primary',
        'gray' => 'Gray',
        'info' => 'Info',
        'success' => 'Success',
        'warning' => 'Warning',
        'danger' => 'Danger',
    ],
    'badges' => [
        'preset' => 'Preset',
        'shared' => 'Shared',
    ],
    'tabs' => [
        'all' => 'All',
    ],
];
