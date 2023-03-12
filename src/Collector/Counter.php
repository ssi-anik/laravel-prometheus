<?php

namespace Anik\Laravel\Prometheus\Collector;

final class Counter extends Collector
{
    /** @var int|float */
    protected $count = 1;

    public function increment($count = 1): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * @throws \Prometheus\Exception\MetricsRegistrationException
     */
    public function store(): bool
    {
        $keys = array_keys($this->labels);
        $values = array_values($this->labels);

        $this->getCollectorRegistry()
             ->getOrRegisterCounter(
                 $this->getNamespace(),
                 $this->getName(),
                 $this->getHelpText(),
                 $keys
             )
             ->incBy($this->count, $values);

        return true;
    }
}
