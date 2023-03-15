<?php

return [
    'namespace' => env('PROMETHEUS_NAMESPACE', ''),
    /**
     * Supported storages: 'redis', 'apc', 'apcng', 'memory' (alias: 'in-memory')
     * It's also possible to use custom adapter that will be resolved via service container.
     */
    'storage' => env('PROMETHEUS_STORAGE', 'redis'),
    'options' => [
        'redis' => [
            'host' => env('PROMETHEUS_REDIS_HOST', '127.0.0.1'),
            'username' => env('PROMETHEUS_REDIS_USERNAME'),
            'password' => env('PROMETHEUS_REDIS_PASSWORD'),
            'port' => env('PROMETHEUS_REDIS_PORT', 6379),
            'database' => env('PROMETHEUS_REDIS_DB', 0),
            'timeout' => env('PROMETHEUS_REDIS_TIMEOUT', 0.1),
            'read_timeout' => env('PROMETHEUS_REDIS_READ_TIMEOUT', 10),
            'persistent_connections' => env('PROMETHEUS_REDIS_PERSISTENT_CONNECTION', false),
        ],
        /*'apc' => ['prometheusPrefix' => ''],
        'apcng' => ['prometheusPrefix' => ''],*/
    ],
    'request' => [
        /** Enable request response metrics */
        'enabled' => true,

        /** URLs to ignore. Regex is allowed. */
        'ignore' => ['/metrics'],

        /** Rename metric labels */
        'naming' => [
            'method' => 'method',
            'url' => 'url',
            'status' => 'status',
        ],
        'count' => [
            /** Enable count metric type */
            'enabled' => true,
            'name' => env('PROMETHEUS_REQUEST_COUNT_NAME', 'request'),
        ],
        'histogram' => [
            /** Enable histogram metric type */
            'enabled' => true,
            'name' => env('PROMETHEUS_REQUEST_HISTOGRAM_NAME', 'request_latency'),
            /** Buckets for histogram metric type */
            'buckets' => [0.01, 0.02, 0.05, 0.1, 0.2, 0.5, 1, 1.5,],
        ],
    ],
    'database' => [
        /** Enable database metrics */
        'enabled' => true,

        /** Table names to ignore. */
        'ignore' => [
            'failed_jobs',
            'migrations',
        ],

        /** Rename metric labels */
        'naming' => [
            'tables' => 'tables',
            'query' => 'query',
            'type' => 'type', // SELECT, UPDATE, DELETE
        ],
        'count' => [
            /** Enable count metric type */
            'enabled' => true,
            'name' => env('PROMETHEUS_DATABASE_COUNT_NAME', 'database'),
        ],
        'histogram' => [
            /** Enable histogram metric type */
            'enabled' => true,
            'name' => env('PROMETHEUS_DATABASE_HISTOGRAM_NAME', 'database_latency'),
            /** Buckets for histogram metric type */
            'buckets' => [0.05, 0.1, 0.2, 0.5, 1, 1.5,],
        ],
    ],
    'export' => [
        'enabled' => true,
        'method' => 'GET',
        'as' => 'laravel.prometheus.export',
        'path' => env('PROMETHEUS_EXPORT_PATH', '/metrics'),
        /**
         * Add route group attributes
         */
        'attributes' => [],
    ],
];
