<?php

namespace Anik\Laravel\Prometheus\Listeners;

use Anik\Laravel\Prometheus\Extractors\HttpClient;
use Anik\Laravel\Prometheus\Matcher;
use Anik\Laravel\Prometheus\PrometheusManager;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\Events\ResponseReceived;

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

            $hosts = $config['ignore'] ?? [];
            if ($hosts && false !== ($paths = Matcher::matches($hosts, $stats->getRequest()->getUri()->getHost()))) {
                if (empty($paths)) {
                    return;
                }

                // Check for matching paths
                if (false !== ($methods = Matcher::matches($paths, $stats->getRequest()->getUri()->getPath()))) {
                    if (empty($methods) || $methods === '*') {
                        return;
                    }

                    // Check for matching Http methods/verbs
                    if (false !== Matcher::matches($methods, $stats->getRequest()->getMethod())) {
                        return;
                    }
                }
            }

            $data = app(
                $config['extractor'] ?? HttpClient::class,
                [
                    'stats' => $stats,
                    'labels' => $config['labels'],
                    'modifiers' => $config['modifiers'] ?? [],
                ]
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
