<?php

namespace Anik\Laravel\Prometheus\Middlewares;

use Anik\Laravel\Prometheus\Extractors\HttpRequest;
use Anik\Laravel\Prometheus\Matcher;
use Anik\Laravel\Prometheus\PrometheusManager;
use Closure;
use Illuminate\Http\Request;
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
            if (false !== Matcher::matches($methods, $request->getMethod())) {
                return;
            }
        }

        if ($paths = $config['ignore']['paths'] ?? []) {
            if (false !== ($methods = Matcher::matches($paths, $request->decodedPath()))) {
                if (empty($methods) || $methods === '*') {
                    return;
                }

                if (false !== Matcher::matches($methods, $request->getMethod())) {
                    return;
                }
            }
        }

        $data = app(
            $config['extractor'] ?? HttpRequest::class,
            ['request' => $request, 'response' => $response, 'labels' => $config['labels']]
        )->toArray();

        if (empty($data) && ($config['allow_empty'] ?? false) !== true) {
            return;
        }

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
