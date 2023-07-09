<?php

namespace Anik\Laravel\Prometheus;

use Anik\Laravel\Prometheus\Collector\Counter;
use Anik\Laravel\Prometheus\Collector\Gauge;
use Anik\Laravel\Prometheus\Collector\Histogram;
use Anik\Laravel\Prometheus\Collector\Summary;
use Prometheus\CollectorRegistry;

class Metric
{
    protected CollectorRegistry $registry;
    protected string $namespace;

    public function __construct(CollectorRegistry $registry, string $namespace = '')
    {
        $this->registry = $registry;
        $this->namespace = $namespace;
    }

    public function counter(string $name): Counter
    {
        return Counter::create($name)
            ->setNamespace($this->namespace)
            ->setCollectoryRegistry($this->registry);
    }

    public function histogram(string $name): Histogram
    {
        return Histogram::create($name)
            ->setNamespace($this->namespace)
            ->setCollectoryRegistry($this->registry);
    }

    public function gauge(string $name): Gauge
    {
        return Gauge::create($name)
            ->setNamespace($this->namespace)
            ->setCollectoryRegistry($this->registry);
    }

    public function summary(string $name): Summary
    {
        return Summary::create($name)
            ->setNamespace($this->namespace)
            ->setCollectoryRegistry($this->registry);
    }

    public function samples(): array
    {
        return $this->registry->getMetricFamilySamples();
    }

    public function clear(): void
    {
        $this->registry->wipeStorage();
    }
}
