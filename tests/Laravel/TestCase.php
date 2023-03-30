<?php

namespace Anik\Laravel\Prometheus\Test\Laravel;

use Anik\Laravel\Prometheus\Providers\PrometheusServiceProvider;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Orchestra\Testbench\TestCase as BaseTestCase;
use ReflectionFunction;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PrometheusServiceProvider::class,
        ];
    }

    protected function isListenerAttachedToEvent($event, $listener): bool
    {
        /**
         * https://luisdalmolin.dev/blog/laravel-testing-if-event-listener-is-registered/
         *
         * https://github.com/laravel/framework/pull/36690/files
         */
        $dispatcher = $this->app->make(Dispatcher::class);

        foreach ($dispatcher->getListeners(is_object($event) ? get_class($event) : $event) as $listenerClosure) {
            $reflection = new ReflectionFunction($listenerClosure);
            $listenerClass = $reflection->getStaticVariables()['listener'];

            if ($listenerClass === $listener) {
                return true;
            }

            if ($listenerClass instanceof Closure && $listener === Closure::class) {
                return true;
            }
        }

        return false;
    }

    protected function assertListenerIsAttachedToEvent($event, $listener)
    {
        $this->assertTrue(
            $this->isListenerAttachedToEvent($event, $listener),
            sprintf('Event %s does not have the %s listener attached to it', $event, $listener)
        );
    }

    protected function assertListenerIsNotAttachedToEvent($event, $listener)
    {
        $this->assertFalse(
            $this->isListenerAttachedToEvent($event, $listener),
            sprintf('Event %s has the %s listener attached to it', $event, $listener)
        );
    }
}
