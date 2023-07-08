<?php

namespace Anik\Laravel\Prometheus\Extractors;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

class HttpRequest implements Arrayable
{
    /** @var \Illuminate\Http\Request|\Laravel\Lumen\Http\Request $request */
    protected $request;

    /** @var \Symfony\Component\HttpFoundation\Response | \Illuminate\Http\Response $response */
    protected $response;

    protected array $labels;

    public function __construct($request, $response, array $labels = [])
    {
        $this->request = $request;
        $this->response = $response;
        $this->labels = $labels;
    }

    public function toArray(): array
    {
        $data = [];

        if (isset($this->labels['url'])) {
            $action = $this->getRouteAction();
            $data[$this->labels['url']] = $this->routeAs($action) ?? $this->routeUses($action) ?? $this->request->path();
        }

        if (isset($this->labels['method'])) {
            $data[$this->labels['method']] = $this->request->method();
        }

        if (isset($this->labels['status'])) {
            $data[$this->labels['status']] = $this->response->getStatusCode();
        }

        return $data;
    }

    protected function getRouteAction(): array
    {
        return $this->request->route()->getAction();
    }

    protected function routeAs(array $action): ?string
    {
        return $action['as'] ?? null;
    }

    protected function routeUses(array $action): ?string
    {
        /**
         * Laravel always pushes `uses` as route action even if it's a closure
         *
         * Lumen pushes `uses` as route action
         * .... if handler is string
         * .... else if explicitly specified by the developer
         *
         * In Lumen, unless `uses` is specified, in most cases $action[0] is handling the route, and it's a closure.
         * Otherwise, developer is trying any hack which IDK! 🤷🤷
         *
         */

        $uses = $action['uses'] ?? $action[0] ?? null;
        if ($uses instanceof Closure) {
            $uses = 'Closure';
        }

        return is_null($uses) ? null : implode('@', Arr::wrap($uses));
    }
}
