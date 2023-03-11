<?php

namespace Anik\Laravel\Prometheus\Collector;

final class Histogram extends Collector
{
    public static function forRequest(): self
    {
        return self::create(config('prometheus.request.histogram.name'))
                   ->setNamespace(config('prometheus.namespace') ?? '');
    }

    public static function forDatabase(): self
    {
        return self::create(config('prometheus.database.histogram.name'))
                   ->setNamespace(config('prometheus.namespace') ?? '');
    }

    protected function store(): bool
    {
        return true;
    }
}
