<?php

namespace Anik\Laravel\Prometheus\Test;

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
        /*$inMemoryMock = $this->createMock(InMemory::class);
        $inMemoryMock->expects($this->never())->method($this->anything());
        $this->app->bind('_mock', fn() => $inMemoryMock);
        $this->app->bind('_test', fn() => $inMemoryMock);*/

        $this->get('/metrics?storage=_mock')->assertStatus(200);
        $this->get('/metrics?storage=_test')->assertStatus(200);
    }

    public function testExportRouteSendsHeaderWithMimeType()
    {
        config(['prometheus.storage' => 'memory']);
    }

}
