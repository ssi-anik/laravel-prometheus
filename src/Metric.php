<?php

namespace Anik\Laravel\Prometheus;

use Anik\Laravel\Prometheus\Collector\Counter;
use Anik\Laravel\Prometheus\Collector\Gauge;
use Anik\Laravel\Prometheus\Collector\Histogram;
use Anik\Laravel\Prometheus\Collector\Summary;
use Prometheus\Storage\Adapter;

class Metric
{
    protected Adapter $adapter;
    protected string $namespace;

    public function __construct(Adapter $adapter, string $namespace = '')
    {
        $this->adapter = $adapter;
        $this->namespace = $namespace;
    }

    public function getAdapter(): Adapter
    {
        return $this->adapter;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function counter(string $name): Counter
    {
        return Counter::create($name)
                      ->setNamespace($this->namespace)
                      ->setAdapter($this->adapter);
    }

    public function histogram(string $name): Histogram
    {
        return Histogram::create($name)
                        ->setNamespace($this->namespace)
                        ->setAdapter($this->adapter);
    }

    public function gauge(string $name): Gauge
    {
        return Gauge::create($name)
                    ->setNamespace($this->namespace)
                    ->setAdapter($this->adapter);
    }

    public function summary(string $name): Summary
    {
        return Summary::create($name)
                      ->setNamespace($this->namespace)
                      ->setAdapter($this->adapter);
    }
}
