<?php

namespace Anik\Laravel\Prometheus\Test;

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

    public function testExportFunctionalityIsEnabledByDefault()
    {
        $this->assertTrue($this->app['router']->has('laravel.prometheus.export'));
    }

    /** @define-env disableExport */
    public function testExportFunctionalityCanBeDisabledUsingConfig()
    {
        $this->assertFalse($this->app['router']->has('laravel.prometheus.export'));
    }

    /** @define-env changeExportRoutePath */
    public function testExportPathCanBeChanged()
    {
        $this->get('/prometheus-export-route')->assertStatus(200);
    }

    public function testCanPassQueryParamToSelectStorageOnTheFly()
    {
        // Default metrics is pushed whenever collector registry is instantiated
        $firstMock = $this->createMock(InMemory::class);
        $firstMock->expects($this->atLeast(2))->method($this->anything());
        $this->app->bind('_mock', fn() => $firstMock);

        $secondMock = $this->createMock(InMemory::class);
        $secondMock->expects($this->atLeast(2))->method($this->anything());
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
