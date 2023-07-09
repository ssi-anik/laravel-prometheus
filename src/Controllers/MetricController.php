<?php

namespace Anik\Laravel\Prometheus\Controllers;

use Anik\Laravel\Prometheus\PrometheusManager;
use Illuminate\Http\Request;
use Prometheus\RenderTextFormat;

class MetricController
{
    public function __invoke(Request $request, PrometheusManager $manager)
    {
        $renderer = new RenderTextFormat();
        $result = $renderer->render($manager->samples(
            $request->query('storage'),
            filter_var($request->query('default_metrics'), FILTER_VALIDATE_BOOLEAN)
        )->collect());

        return response(
            $result,
            200,
            ['content-type' => RenderTextFormat::MIME_TYPE]
        );
    }
}
