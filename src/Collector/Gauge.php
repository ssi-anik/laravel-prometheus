<?php

namespace Anik\Laravel\Prometheus\Collector;

final class Gauge extends Collector
{
    public function store(): bool
    {
        return true;
    }
}
