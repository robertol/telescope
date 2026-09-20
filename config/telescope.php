<?php

use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Watchers;

return [

    /*
    |--------------------------------------------------------------------------
    | Telescope Master Switch
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable all Telescope watchers regardless
    | of their individual configuration, which simply provides a single
    | and convenient way to enable or disable Telescope data storage.
    |
    */

    'enabled' => env('TELESCOPE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | HTTP Prune Limit
    |--------------------------------------------------------------------------
    |
    | Prune from the dashboard runs inside the web request. Above this many
    | matching rows the button is refused, so one worker cannot sit on a
    | large delete. The check stops at limit + 1 and does not scan the table.
    |
    */

    'prune_request_limit' => 20000,

    /*
    |--------------------------------------------------------------------------
    | Telescope Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Telescope will be accessible from. If the
    | setting is null, Telescope will reside under the same domain as the
    | application. Otherwise, this value will be used as the subdomain.
    |
    */

    'domain' => env('TELESCOPE_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Telescope Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Telescope will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('TELESCOPE_PATH', 'telescope'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Authentication
    |--------------------------------------------------------------------------
    |
    | Telescope keeps its own dashboard users in `telescope_users`, independent
    | of the application's users table. The default account is created when
    | the table is empty so you can sign in and register the others.
    |
    | Laravel guards remain a fallback for hosts that already authorize via
    | viewTelescope.
    |
    */

    'auth' => [
        'guards' => ['web'],
        'default' => [
            'name' => env('TELESCOPE_USER_NAME', 'Telescope'),
            'email' => env('TELESCOPE_USER_EMAIL', 'telescope@local'),
            'password' => env('TELESCOPE_USER_PASSWORD', 'telescope'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Storage Driver
    |--------------------------------------------------------------------------
    |
    | This configuration options determines the storage driver that will
    | be used to store Telescope's data. In addition, you may set any
    | custom options as needed by the particular driver you choose.
    |
    */

    'driver' => env('TELESCOPE_DRIVER', 'database'),

    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
            'chunk' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Observability Storage
    |--------------------------------------------------------------------------
    |
    | Specialized tables sit beside telescope_entries. Keep legacy writes on
    | until dual-write is validated. Metric rollups (request_metrics_1m, etc.)
    | are intentionally not created in this phase.
    |
    */

    'observability' => [
        'storage_enabled' => env('OBSERVABILITY_STORAGE_ENABLED', false),
        'legacy_storage' => env('OBSERVABILITY_TELESCOPE_LEGACY_STORAGE', true),
        'chunk' => 1000,
        'prune_chunk' => 5000,
        'retention' => [
            'request' => env('OBSERVABILITY_REQUEST_RETENTION_DAYS', 30),
            'query' => env('OBSERVABILITY_QUERY_RETENTION_DAYS', 7),
            'exception' => env('OBSERVABILITY_EXCEPTION_RETENTION_DAYS', 90),
            'job' => env('OBSERVABILITY_JOB_RETENTION_DAYS', 14),
            'http_client' => env('OBSERVABILITY_HTTP_CLIENT_RETENTION_DAYS', 14),
            'cache' => env('OBSERVABILITY_CACHE_RETENTION_DAYS', 3),
            'log' => env('OBSERVABILITY_LOG_RETENTION_DAYS', 7),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Queue
    |--------------------------------------------------------------------------
    |
    | This configuration options determines the queue connection and queue
    | which will be used to process ProcessPendingUpdate jobs. This can
    | be changed if you would prefer to use a non-default connection.
    |
    */

    'queue' => [
        'connection' => env('TELESCOPE_QUEUE_CONNECTION'),
        'queue' => env('TELESCOPE_QUEUE'),
        'delay' => env('TELESCOPE_QUEUE_DELAY', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be assigned to every Telescope route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => [
        'web',
        Authorize::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed / Ignored Paths & Commands
    |--------------------------------------------------------------------------
    |
    | The following array lists the URI paths and Artisan commands that will
    | not be watched by Telescope. In addition to this list, some Laravel
    | commands, like migrations and queue commands, are always ignored.
    |
    */

    'only_paths' => [
        // 'api/*'
    ],

    'ignore_paths' => [
        'livewire*',
        'nova-api*',
        'pulse*',
        '_boost*',
        '.well-known*',
    ],

    'ignore_commands' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Watchers
    |--------------------------------------------------------------------------
    |
    | The following array lists the "watchers" that will be registered with
    | Telescope. The watchers gather the application's profile data when
    | a request or task is executed. Feel free to customize this list.
    |
    */

    'watchers' => [
        Watchers\BatchWatcher::class => env('TELESCOPE_BATCH_WATCHER', true),

        Watchers\CacheWatcher::class => [
            'enabled' => env('TELESCOPE_CACHE_WATCHER', true),
            'hidden' => [],
            'ignore' => [],
        ],

        Watchers\ClientRequestWatcher::class => [
            'enabled' => env('TELESCOPE_CLIENT_REQUEST_WATCHER', true),
            'ignore_hosts' => [],
        ],

        Watchers\CommandWatcher::class => [
            'enabled' => env('TELESCOPE_COMMAND_WATCHER', true),
            'ignore' => [],
        ],

        Watchers\DumpWatcher::class => [
            'enabled' => env('TELESCOPE_DUMP_WATCHER', true),
            'always' => env('TELESCOPE_DUMP_WATCHER_ALWAYS', false),
        ],

        Watchers\EventWatcher::class => [
            'enabled' => env('TELESCOPE_EVENT_WATCHER', true),
            'ignore' => [],
        ],

        Watchers\ExceptionWatcher::class => env('TELESCOPE_EXCEPTION_WATCHER', true),

        Watchers\GateWatcher::class => [
            'enabled' => env('TELESCOPE_GATE_WATCHER', true),
            'ignore_abilities' => [],
            'ignore_packages' => true,
            'ignore_paths' => [],
        ],

        Watchers\JobWatcher::class => env('TELESCOPE_JOB_WATCHER', true),

        Watchers\LogWatcher::class => [
            'enabled' => env('TELESCOPE_LOG_WATCHER', true),
            'level' => 'error',
        ],

        Watchers\MailWatcher::class => env('TELESCOPE_MAIL_WATCHER', true),

        Watchers\ModelWatcher::class => [
            'enabled' => env('TELESCOPE_MODEL_WATCHER', true),
            'events' => ['eloquent.*'],
            'hydrations' => true,
        ],

        Watchers\NotificationWatcher::class => env('TELESCOPE_NOTIFICATION_WATCHER', true),

        Watchers\QueryWatcher::class => [
            'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
            'ignore_packages' => true,
            'ignore_paths' => [],
            'slow' => 100,
        ],

        Watchers\RedisWatcher::class => env('TELESCOPE_REDIS_WATCHER', true),

        Watchers\RequestWatcher::class => [
            'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
            'size_limit' => env('TELESCOPE_RESPONSE_SIZE_LIMIT', 64),
            'ignore_http_methods' => [],
            'ignore_status_codes' => [],
        ],

        Watchers\ScheduleWatcher::class => env('TELESCOPE_SCHEDULE_WATCHER', true),
        Watchers\ViewWatcher::class => env('TELESCOPE_VIEW_WATCHER', true),
    ],
];
