<?php

namespace Anik\Laravel\Prometheus\Test;

use Closure;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;

class ExportMetricTest extends TestCase
{
    use InteractsWithStorage;

    public function disableExport($app)
    {
        $app['config']->set(['prometheus.export.enabled' => false]);
    }

    public function changeExportRoutePath($app)
    {
        $app['config']->set(['prometheus.export.path' => '/prometheus-export-route']);
    }

    public function changeExportRouteHttpMethod($app)
    {
        $app['config']->set(['prometheus.export.method' => 'POST']);
    }

    public function changeRouteGroupAttribute($app)
    {
        $app['config']->set([
            'prometheus.export' => [
                'attributes' => ['middleware' => '__test_middleware'],
            ],
        ]);
    }

    public function changeRouteName($app)
    {
        $app['config']->set(['prometheus.export.as' => 'export.prometheus.laravel']);
    }

    public function testExportFunctionalityIsEnabledByDefault()
    {
        $this->assertTrue($this->app['router']->has('laravel.prometheus.export'));
    }

    /** @define-env disableExport */
    public function testExportFunctionalityCanBeDisabledUsingConfig()
    {
        $this->assertFalse($this->app['router']->has('laravel.prometheus.export'));
    }

    /** @define-env changeRouteName */
    public function testExportRouteNameCanBeChanged()
    {
        $this->assertTrue($this->app['router']->has('export.prometheus.laravel'));
    }

    /** @define-env changeExportRoutePath */
    public function testExportRoutePathCanBeChanged()
    {
        $this->get('/prometheus-export-route')->assertStatus(200);
    }

    /** @define-env changeExportRouteHttpMethod */
    public function testExportRouteHttpMethodCanBeChanged()
    {
        $this->post('/metrics')->assertStatus(200);
    }

    /** @define-env changeRouteGroupAttribute */
    public function testExportRouteCanAddRouteGroupAttribute()
    {
        $this->app['router']->aliasMiddleware('__test_middleware', function ($request, Closure $next) {
            if ($request->headers->get('X-CUSTOM-HEADER') !== '_PROM_TEST_') {
                return abort(403);
            }

            return $next($request);
        });

        $this->get('/metrics', ['X-Custom-Header' => '_PROM_TEST_'])->assertStatus(200);
    }

    public function testExportRouteCanHaveQueryParamToSelectStorageOnTheFly()
    {
        $firstMock = $this->createMock(InMemory::class);
        $firstMock->expects($this->atLeast(1))->method($this->anything());
        $this->app->bind('_mock', fn() => $firstMock);

        $secondMock = $this->createMock(InMemory::class);
        $secondMock->expects($this->atLeast(1))->method($this->anything());
        $this->app->bind('_test', fn() => $secondMock);

        $this->get('/metrics?storage=_mock')->assertStatus(200);
        $this->get('/metrics?storage=_test')->assertStatus(200);
    }

    public function testExportRouteSendsHeaderWithMimeType()
    {
        $response = $this->get('/metrics')->assertHeader('content-type');
        // Header gets manipulated in Symfony\Component\HttpFoundation\Response::prepare
        $this->assertStringContainsString(RenderTextFormat::MIME_TYPE, $response->headers->get('content-type'));
    }
}
