<?php

namespace Anik\Laravel\Prometheus\Middlewares;

use Anik\Laravel\Prometheus\Extractors\Request as RequestExtractor;
use Anik\Laravel\Prometheus\Extractors\Response as ResponseExtractor;
use Anik\Laravel\Prometheus\PrometheusManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class PrometheusMiddleware
{
    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response)
    {
        if (defined('LARAVEL_START')) {
            $start = LARAVEL_START;
        } elseif (defined('APP_START')) {
            $start = APP_START;
        } else {
            return;
        }

        $config = config('prometheus.request');
        if ($methods = $config['ignore']['methods'] ?? []) {
            foreach (Arr::wrap($methods) as $method) {
                if ($request->isMethod($method)) {
                    return;
                }
            }
        }

        if ($paths = $config['ignore']['paths'] ?? []) {
            foreach ($paths as $path => $method) {
                if (!$request->is($path)) {
                    continue;
                }

                if ($method === '' || $method === '*') {
                    return;
                }

                // Supports multiple verbs
                foreach (Arr::wrap($method) as $method) {
                    if ($request->isMethod($method)) {
                        return;
                    }
                }
            }
        }

        $requestData = app(
            $config['extractor']['request'] ?? RequestExtractor::class,
            ['request' => $request, 'naming' => $config['naming']]
        )->toArray();

        $responseData = app(
            $config['extractor']['response'] ?? ResponseExtractor::class,
            ['response' => $response, 'naming' => $config['naming']]
        )->toArray();

        $data = array_merge($requestData, $responseData);

        /** @var \Anik\Laravel\Prometheus\Metric $metric */
        $metric = app(PrometheusManager::class)->metric();
        if (($config['counter']['enabled'] ?? true) !== false) {
            $metric->counter($config['counter']['name'] ?? 'request')
                   ->labels($data)
                   ->increment();
        }

        if (($config['histogram']['enabled'] ?? true) !== false) {
            $time = microtime(true) - $start;

            $histogram = $metric->histogram($config['histogram']['name'] ?? 'request_latency')
                                ->labels($data)
                                ->observe($time);
            if (!empty($buckets = $config['histogram']['buckets'] ?? null)) {
                $histogram->buckets($buckets);
            }
        }
    }
}
