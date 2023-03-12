<?php

namespace Anik\Laravel\Prometheus\Test;

use Anik\Laravel\Prometheus\Collector\Counter;
use Anik\Laravel\Prometheus\Collector\Gauge;
use Anik\Laravel\Prometheus\Collector\Histogram;
use Anik\Laravel\Prometheus\Collector\Summary;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;

class CollectorTest extends TestCase
{
    public function testMetricsWillBeSavedOnceEvenIfSaveMethodIsCalledMultipleTimes()
    {
        $counterMock = $this->createMock(\Prometheus\Counter::class);
        $counterMock->expects($this->once())->method('incBy');

        $histogramMock = $this->createMock(\Prometheus\Histogram::class);
        $histogramMock->expects($this->once())->method('observe');

        $gaugeMock = $this->createMock(\Prometheus\Gauge::class);
        $gaugeMock->expects($this->once())->method('set');

        $summaryMock = $this->createMock(\Prometheus\Summary::class);
        $summaryMock->expects($this->once())->method('observe');

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->method('getOrRegisterCounter')
                     ->willReturn($counterMock);
        $registryMock->method('getOrRegisterGauge')
                     ->willReturn($gaugeMock);
        $registryMock->method('getOrRegisterHistogram')
                     ->willReturn($histogramMock);
        $registryMock->method('getOrRegisterSummary')
                     ->willReturn($summaryMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        $counter = Counter::create('counter')->increment();
        $counter->save();
        $counter->save();
        $counter->save();

        $histogram = Histogram::create('histogram')->observe(2.2);
        $histogram->save();
        $histogram->save();
        $histogram->save();

        $gauge = Gauge::create('gauge')->set(2.2);
        $gauge->save();
        $gauge->save();
        $gauge->save();

        $summary = Summary::create('summary')->observe(2.2);
        $summary->save();
        $summary->save();
        $summary->save();
    }

    public function testMetricsWillUseStoragePropertyToBuildAdapterIfAdapterIsNotProvided()
    {
        /**
         * if redis storage is used, it should fail
         * as the redis service/container will not be available during the tests.
         */
        config(['prometheus.storage' => 'redis']);

        $storage = 'fake-storage';
        $this->app->bind($storage, fn() => new InMemory());

        $counter = Counter::create('counter')->setStorage($storage);
        $this->assertInstanceOf(InMemory::class, $counter->getAdapter());

        $histogram = Histogram::create('histogram')->setStorage($storage);
        $this->assertInstanceOf(InMemory::class, $histogram->getAdapter());
        $histogram->observe(2.2);

        $gauge = Gauge::create('gauge')->setStorage($storage);
        $this->assertInstanceOf(InMemory::class, $gauge->getAdapter());
        $gauge->set(2.2);

        $summary = Summary::create('summary')->setStorage($storage);
        $this->assertInstanceOf(InMemory::class, $summary->getAdapter());
        $summary->observe(2.2);
    }

    public function testCallingSkipMethodWillNotSaveTheMetrics()
    {
        $counterMock = $this->createMock(\Prometheus\Counter::class);
        $counterMock->expects($this->never())->method('incBy');

        $histogramMock = $this->createMock(\Prometheus\Histogram::class);
        $histogramMock->expects($this->never())->method('observe');

        $gaugeMock = $this->createMock(\Prometheus\Gauge::class);
        $gaugeMock->expects($this->never())->method('set');

        $summaryMock = $this->createMock(\Prometheus\Summary::class);
        $summaryMock->expects($this->never())->method('observe');

        $registryMock = $this->createMock(CollectorRegistry::class);
        $registryMock->method('getOrRegisterCounter')
                     ->willReturn($counterMock);
        $registryMock->method('getOrRegisterGauge')
                     ->willReturn($gaugeMock);
        $registryMock->method('getOrRegisterHistogram')
                     ->willReturn($histogramMock);
        $registryMock->method('getOrRegisterSummary')
                     ->willReturn($summaryMock);

        $this->app->bind(CollectorRegistry::class, fn() => $registryMock);

        Counter::create('counter')->increment()->skip();

        Histogram::create('histogram')->observe(2.2)->skip();

        Gauge::create('gauge')->set(2.2)->skip();

        Summary::create('summary')->observe(2.2)->skip();
    }
}
