<?php

namespace Anik\Laravel\Prometheus\Test;

use Anik\Laravel\Prometheus\Middlewares\PrometheusMiddleware;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Prometheus\Storage\InMemory;

class DummyController
{
    public function success(Request $request): JsonResponse
    {
        $code = $request->get('code', 200);

        return response()->json(['success' => true], $code);
    }

    public function error(Request $request): JsonResponse
    {
        $code = $request->get('code', 400);

        return response()->json(['success' => false], $code);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $code = $request->get('code', 200);

        return response()->json(['success' => $code >= 200 && $code < 400], $code);
    }
}

class PrometheusMiddlewareTest extends TestCase
{
    /*protected static string $SUCCESS_ROUTE_GET = '/get/success';
    protected static string $SUCCESS_ROUTE_POST = '/post/success';
    protected static string $ERROR_ROUTE_GET = '/get/error';
    protected static string $ERROR_ROUTE_POST = '/post/error';*/

    use InteractsWithStorage;

    /*protected function afterSetUpHook(): void
    {
        $this->addRoutes();
    }*/

    public function disableRequestMetrics($app)
    {
        $app['config']->set(['prometheus.request.enabled' => false]);
    }

    public function addRoute(string $url, string $method = 'GET', $attributes = [])
    {
        $method = strtolower($method);
        $handler = function (Request $request): JsonResponse {
            $code = $request->get('code', 200);

            return response()->json(['success' => $code >= 200 && $code < 400], $code);
        };

        if (empty($attributes)) {
            $attributes = $handler;
        } elseif (is_array($attributes) && empty($attributes['uses'])) {
            $attributes['uses'] = $handler;
        }

        $this->app['router']->$method($url, $attributes);
        /*$this->app['router']->get(static::$SUCCESS_ROUTE_GET, function (Request $request) {
            $code = $request->query('code', 200);

            return response()->json(['success' => true], $code);
        });

        $this->app['router']->post(static::$SUCCESS_ROUTE_POST, function (Request $request) {
            $code = $request->input('code', 200);

            return response()->json(['success' => true], $code);
        });

        $this->app['router']->get(static::$ERROR_ROUTE_GET, function (Request $request) {
            $code = $request->query('code', 400);

            return response()->json(['success' => false], $code);
        });

        $this->app['router']->post(static::$ERROR_ROUTE_POST, function (Request $request) {
            $code = $request->input('code', 400);

            return response()->json(['success' => false], $code);
        });*/
    }

    public function testRequestMetricsIsEnabledByDefault()
    {
        $this->assertTrue($this->app[Kernel::class]->hasMiddleware(PrometheusMiddleware::class));
    }

    /** @define-env disableRequestMetrics */
    public function testRequestMetricsCanBeDisabledUsingConfig()
    {
        $this->assertFalse($this->app[Kernel::class]->hasMiddleware(PrometheusMiddleware::class));
    }

    public function testRequestMiddlewareIsTerminable()
    {
        $this->assertTrue($this->app->bound(PrometheusMiddleware::class));
        $this->assertTrue(method_exists(PrometheusMiddleware::class, 'terminate'));
    }

    public function testRequestMetricInNeverStoredIfConstantIsNotDefined()
    {
        // Constant is not set, so terminate method will just return without calling metrics save
        $mock = $this->createMock(InMemory::class);
        $mock->expects($this->never())->method($this->anything());
        $this->app->singleton(static::$STORAGE_NAME, fn() => $mock);

        $this->addRoute('/get/success');
        $this->get('/get/success');
    }

    public function testMetricGetsPushedToStorage()
    {
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        $mock = $this->createMock(InMemory::class);
        // 2 times, because the default values will be set
        $mock->expects($this->atLeast(2))->method($this->anything());
        $this->app->singleton(static::$STORAGE_NAME, fn() => $mock);
        $this->addRoute('/test/abcd/edfe');
        $this->get('/test/abcd/edfe');
    }
}
