<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    |
    | By uncommenting the Laravel Echo configuration, you may connect Filament
    | to any Pusher-compatible websockets server.
    |
    | This will allow your users to receive real-time notifications.
    |
    */

    'broadcasting' => [

        // 'echo' => [
        //     'broadcaster' => 'pusher',
        //     'key' => env('VITE_PUSHER_APP_KEY'),
        //     'cluster' => env('VITE_PUSHER_APP_CLUSTER'),
        //     'wsHost' => env('VITE_PUSHER_HOST'),
        //     'wsPort' => env('VITE_PUSHER_PORT'),
        //     'wssPort' => env('VITE_PUSHER_PORT'),
        //     'authEndpoint' => '/broadcasting/auth',
        //     'disableStats' => true,
        //     'encrypted' => true,
        //     'forceTLS' => true,
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | This is the storage disk Filament will use to store files. You may use
    | any of the disks defined in the `config/filesystems.php`.
    |
    */

    'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Assets Path
    |--------------------------------------------------------------------------
    |
    | This is the directory where Filament's assets will be published to. It
    | is relative to the `public` directory of your Laravel application.
    |
    | After changing the path, you should run `php artisan filament:assets`.
    |
    */

    'assets_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Cache Path
    |--------------------------------------------------------------------------
    |
    | This is the directory that Filament will use to store cache files that
    | are used to optimize the registration of components.
    |
    | After changing the path, you should run `php artisan filament:cache-components`.
    |
    */

    'cache_path' => base_path('bootstrap/cache/filament'),

    /*
    |--------------------------------------------------------------------------
    | Livewire Loading Delay
    |--------------------------------------------------------------------------
    |
    | This sets the delay before loading indicators appear.
    |
    | Setting this to 'none' makes indicators appear immediately, which can be
    | desirable for high-latency connections. Setting it to 'default' applies
    | Livewire's standard 200ms delay.
    |
    */

    'livewire_loading_delay' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Default Theme
    |--------------------------------------------------------------------------
    |
    | This is the default theme that will be used by Filament.
    |
    */

    'default_theme' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Theme Colors
    |--------------------------------------------------------------------------
    |
    | These are the default colors that will be used by Filament.
    | You can customize these colors to match your brand.
    |
    */

    'colors' => [
        'primary' => [
            50 => '#f0f9ff',
            100 => '#e0f2fe',
            200 => '#bae6fd',
            300 => '#7dd3fc',
            400 => '#38bdf8',
            500 => '#0ea5e9',
            600 => '#0284c7',
            700 => '#0369a1',
            800 => '#075985',
            900 => '#0c4a6e',
            950 => '#082f49',
        ],
        'danger' => [
            50 => '#fef2f2',
            100 => '#fee2e2',
            200 => '#fecaca',
            300 => '#fca5a5',
            400 => '#f87171',
            500 => '#ef4444',
            600 => '#dc2626',
            700 => '#b91c1c',
            800 => '#991b1b',
            900 => '#7f1d1d',
            950 => '#450a0a',
        ],
        'success' => [
            50 => '#f0fdf4',
            100 => '#dcfce7',
            200 => '#bbf7d0',
            300 => '#86efac',
            400 => '#4ade80',
            500 => '#22c55e',
            600 => '#16a34a',
            700 => '#15803d',
            800 => '#166534',
            900 => '#14532d',
            950 => '#052e16',
        ],
        'warning' => [
            50 => '#fffbeb',
            100 => '#fef3c7',
            200 => '#fde68a',
            300 => '#fcd34d',
            400 => '#fbbf24',
            500 => '#f59e0b',
            600 => '#d97706',
            700 => '#b45309',
            800 => '#92400e',
            900 => '#78350f',
            950 => '#451a03',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Styles
    |--------------------------------------------------------------------------
    |
    | These are custom styles that will be applied to Filament components.
    |
    */

    'styles' => [
        'card' => [
            'rounded' => 'rounded-lg',
            'shadow' => 'shadow-md',
        ],
        'button' => [
            'rounded' => 'rounded-md',
            'padding' => 'px-4 py-2',
        ],
        'table' => [
            'rounded' => 'rounded-lg',
            'shadow' => 'shadow-sm',
        ],
    ],

];
