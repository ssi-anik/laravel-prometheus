<?php

namespace Anik\Laravel\Prometheus\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Anik\Laravel\Prometheus\Metric metric(?string $storage = null)
 * @method static \Prometheus\Storage\Adapter adapter(?string $storage = null)
 * @method static \Prometheus\Storage\Redis createRedisAdapter()
 * @method static \Prometheus\Storage\APC createApcAdapter()
 * @method static \Prometheus\Storage\APCng createApcngAdapter()
 * @method static \Prometheus\Storage\InMemory createMemoryAdapter()
 * @method static \Prometheus\Storage\InMemory createInMemoryAdapter()
 * @method static \Anik\Laravel\Prometheus\Collector\Counter counter(string $name)
 * @method static \Anik\Laravel\Prometheus\Collector\Histogram histogram(string $name)
 * @method static \Anik\Laravel\Prometheus\Collector\Gauge gauge(string $name)
 * @method static \Anik\Laravel\Prometheus\Collector\Summary summary(string $name)
 */
class Prometheus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'prometheus';
    }
}
