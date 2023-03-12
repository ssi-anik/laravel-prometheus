<?php

namespace Anik\Laravel\Prometheus\Test;

use Prometheus\Storage\InMemory;

trait InteractsWithStorage
{
    protected static string $STORAGE_NAME = 'mocked_storage';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMockStorageAdapter(self::$STORAGE_NAME);
    }

    protected function configureMockStorageAdapter(string $bindingName = 'mock_adapter')
    {
        $mock = $this->createMock(InMemory::class);
        $this->app->singleton($bindingName, fn() => $mock);
        config(['prometheus.storage' => $bindingName]);
    }
}
