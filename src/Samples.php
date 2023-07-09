<?php

namespace Anik\Laravel\Prometheus;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;

class Samples
{
    protected Adapter $registry;

    public function __construct(CollectorRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function collect(): array
    {
        return $this->registry->getMetricFamilySamples();
    }

    public function clear(): void
    {
        $this->registry->wipeStorage();
    }
}
