<?php

namespace Anik\Laravel\Prometheus\Listeners;

use Anik\Laravel\Prometheus\Extractors\HttpClient;
use Anik\Laravel\Prometheus\PrometheusManager;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ResponseReceivedListener
{
    public function handle(ResponseReceived $event): void
    {
        $stats = $event->response->transferStats;
        
        $stats ? $this->processTransferStats($stats) : null;
    }

    protected function processTransferStats(TransferStats $stats)
    {
        $config = config('prometheus.http');

        $callback = function () use ($config, $stats) {
            $ignore = $config['ignore'] ?? [];
            $host = $stats->getRequest()->getUri()->getHost();
            $path = $stats->getRequest()->getUri()->getPath();
            $method = $stats->getRequest()->getMethod();

            /**
             * Sample
             * [
             *      "example.com" => [
             *              '/path',
             *              '/path' => [],
             *              '/path' => '',
             *              '/path' => ['get'],
             *      ],
             *      "example.cc" => [],
             * ]
             */
            foreach ($ignore as $hostPatterns => $paths) {
                if (!Str::is($hostPatterns, $host)) {
                    continue;
                }

                if (empty($paths)) {
                    return;
                }

                foreach ($paths as $key => $value) {
                    if (is_numeric($key)) {
                        // indexed array, value is the path pattern
                        $pathPattern = $value;
                        $methods = [];
                    } else {
                        // associative array
                        $pathPattern = $key;
                        $methods = $value;
                    }

                    if (!Str::is($pathPattern, $path)) {
                        continue;
                    }

                    if (empty($methods)) {
                        return;
                    }

                    foreach (Arr::wrap($methods) as $methodPattern) {
                        if (Str::is(strtoupper($methodPattern), $method)) {
                            return;
                        }
                    }
                }
            }

            $data = app(
                $config['extractor'] ?? HttpClient::class,
                ['stats' => $stats, 'naming' => $config['naming']]
            )->toArray();

            /** @var \Anik\Laravel\Prometheus\Metric $metric */
            $metric = app(PrometheusManager::class)->metric();
            if (($config['counter']['enabled'] ?? true) !== false) {
                $metric->counter($config['counter']['name'] ?? 'http')
                       ->labels($data)
                       ->increment();
            }

            if (($config['histogram']['enabled'] ?? true) !== false) {
                // https://github.com/laravel/framework/issues/32068#issuecomment-602566705
                $time = $stats->getTransferTime() ?? 0;

                $histogram = $metric->histogram($config['histogram']['name'] ?? 'http_latency')
                                    ->labels($data)
                                    ->observe($time);
                if (!empty($buckets = $config['histogram']['buckets'] ?? null)) {
                    $histogram->buckets($buckets);
                }
            }
        };

        if (false === ($config['after_response'] ?? false)) {
            call_user_func($callback);
        } else {
            app()->terminating($callback);
        }
    }
}
