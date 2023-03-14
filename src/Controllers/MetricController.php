<?php

namespace Anik\Laravel\Prometheus\Controllers;

use Anik\Laravel\Prometheus\PrometheusManager;
use Illuminate\Http\Request;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

class MetricController
{
    public function __invoke(Request $request)
    {
        $storage = $request->query('storage');
        $registry = new CollectorRegistry(app()->make(PrometheusManager::class)->adapter($storage));

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        return response(
            $result,
            200,
            ['content-type' => RenderTextFormat::MIME_TYPE]
        );
    }
}
