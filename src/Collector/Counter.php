<?php

namespace Anik\Laravel\Prometheus\Collector;

final class Counter extends Collector
{
    public static function forRequest(): self
    {
        return self::create(config('prometheus.request.count.name'))
                   ->namespace(config('prometheus.namespace') ?? '');
    }

    public static function forDatabase(): self
    {
        return self::create(config('prometheus.database.count.name'))
                   ->namespace(config('prometheus.namespace') ?? '');
    }

    public function store(): bool
    {
        return true;
    }
}
