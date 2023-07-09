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
            'driver' => 'redis',
            'host' => env('PROMETHEUS_REDIS_HOST', '127.0.0.1'),
            'username' => env('PROMETHEUS_REDIS_USERNAME'),
            'password' => env('PROMETHEUS_REDIS_PASSWORD'),
            'port' => env('PROMETHEUS_REDIS_PORT', 6379),
            'database' => env('PROMETHEUS_REDIS_DB', 0),
            'timeout' => env('PROMETHEUS_REDIS_TIMEOUT', 0.1),
            'read_timeout' => env('PROMETHEUS_REDIS_READ_TIMEOUT', 10),
            'persistent_connections' => env('PROMETHEUS_REDIS_PERSISTENT_CONNECTION', false),
        ],
        /*'apc' => ['driver' => 'apc', 'prometheusPrefix' => ''],
        'apcng' => ['driver' => 'apcng', 'prometheusPrefix' => ''],*/
    ],
    'request' => [
        /** Enable request response metrics */
        'enabled' => true,

        /** Ignore incoming HTTP requests */
        'ignore' => [
            /** Ignore HTTP Method: "OPTIONS" by default. */
            'methods' => 'OPTIONS',

            /**
             * Ignore HTTP Requests for matching paths
             *
             * Format:
             *
             * 'path/to/match' => 'HTTP_METHOD/VERB'
             *
             * Examples:
             *     'path' => '',
             *     'path' => '*',
             *     'path' => ['get', 'post', 'delete'],
             */
            'paths' => [
                'metrics' => '*',
            ],
        ],

        /** Rename metric labels */
        'labels' => [
            'method' => 'method',
            'url' => 'url',
            'status' => 'status',
        ],
        'counter' => [
            /** Enable counter metric type */
            'enabled' => true,
            'name' => env('PROMETHEUS_REQUEST_COUNTER_NAME', 'request'),
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
    'http' => [
        /** Enable remote service request response metrics */
        'enabled' => true,

        /** Stores data after response is sent */
        'after_response' => false,

        /**
         * Ignore HTTP requests
         * Example:
         *      # Ignores paths for the matching hosts
         *      "host" => ['/path1', '/path/2', '*']
         *      # Ignore all paths for the matching host
         *      "host" => [],
         */
        'ignore' => [],

        /** Rename metric labels */
        'labels' => [
            'scheme' => 'scheme',
            'host' => 'host',
            'path' => 'path',
            'method' => 'method',
            'status' => 'status',
        ],
        'counter' => [
            /** Enable counter metric type */
            'enabled' => true,
            'name' => env('PROMETHEUS_HTTP_COUNTER_NAME', 'http'),
        ],
        'histogram' => [
            /** Enable histogram metric type */
            'enabled' => true,
            'name' => env('PROMETHEUS_HTTP_HISTOGRAM_NAME', 'http_latency'),
            /** Buckets for histogram metric type */
            'buckets' => [0.02, 0.05, 0.1, 0.2, 0.5, 1, 1.5, 2.0],
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
