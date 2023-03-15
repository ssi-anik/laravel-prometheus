<?php

namespace Anik\Laravel\Prometheus\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
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

        $end = microtime(true);
        $time = $end - $start;
        $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 'N/A';
        $identifier = $this->requestIdentifier($request);
        dd($identifier);

        app('log')->info(get_class(app()));
    }

    protected function requestIdentifier($request)
    {
        $route = $request->route();
        if ($route instanceof Route) {
            $route = $route->getAction();
        }

        if ($as = $route['as'] ?? null) {
            return $as;
        }

        $uses = $route['uses'] ?? null;
        if (is_array($uses)) {
            return sprintf('%s@%s', $uses[0] ?? '-', $uses[1] ?? '-');
        } elseif (is_string($uses)) {
            return $uses;
        }

        dd($request->path());
    }
}
