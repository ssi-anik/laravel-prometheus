<?php

namespace Anik\Laravel\Prometheus\Extractors;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

class Request implements Arrayable
{
    /** @var \Illuminate\Http\Request|\Laravel\Lumen\Http\Request $request */
    protected $request;
    protected array $mapper;

    public function __construct($request, array $mapper = [])
    {
        $this->request = $request;
        $this->mapper = $mapper;
    }

    protected function getRouteAction(): array
    {
        return $this->request->route()->getAction();
    }

    public function toArray(): array
    {
        $action = $this->getRouteAction();
        $as = $action['as'] ?? null;
        if ($uses = $action['uses'] ?? null) {
            if ($uses instanceof Closure) {
                $uses = 'Closure';
            }

            $uses = implode('@', Arr::wrap($uses));
        }

        return [
            ($this->mapper['method'] ?? 'method') => $this->request->method(),
            ($this->mapper['url'] ?? 'url') => $as ?? $uses ?? $this->request->path(),
        ];
    }
}
