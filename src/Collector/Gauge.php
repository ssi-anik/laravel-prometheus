<?php

namespace Anik\Laravel\Prometheus\Collector;

use Anik\Laravel\Prometheus\Exceptions\PrometheusException;

final class Gauge extends Collector
{
    private static int $SET = 1;
    private static int $INCREMENT = 2;
    private static int $DECREMENT = 3;

    /** @var int|float */
    protected $value = 1;
    protected ?int $operation = null;

    public function increment($count = 1): self
    {
        $this->operation = self::$INCREMENT;
        $this->value = $count;

        return $this;
    }

    public function decrement($count = 1): self
    {
        $this->operation = self::$DECREMENT;
        $this->value = $count;

        return $this;
    }

    public function set($value): self
    {
        $this->operation = self::$SET;
        $this->value = $value;

        return $this;
    }

    /**
     * @throws \Prometheus\Exception\MetricsRegistrationException
     * @throws \Anik\Laravel\Prometheus\Exceptions\PrometheusException
     */
    public function store(): void
    {
        switch ($this->operation) {
            case self::$INCREMENT:
                $method = 'incBy';
                break;
            case self::$DECREMENT:
                $method = 'decBy';
                break;
            case self::$SET:
                $method = 'set';
                break;
            default:
                throw new PrometheusException('Did you forget to perform an operation (set/increment/decrement)?');
        }

        $keys = array_keys($this->labels);
        $values = array_values($this->labels);

        $gauge = $this->getCollectorRegistry()
                      ->getOrRegisterGauge($this->getNamespace(), $this->getName(), $this->getHelpText(), $keys);

        call_user_func_array([$gauge, $method], [$this->value, $values]);
    }
}
