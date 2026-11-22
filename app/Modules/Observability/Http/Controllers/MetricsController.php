<?php

declare(strict_types=1);

namespace App\Modules\Observability\Http\Controllers;

use App\Modules\Observability\Metrics;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Prometheus\RenderTextFormat;

/**
 * Endpoint de scrape do Prometheus. Fora de dev exige o token de `config/metrics.php`
 * (falha fechada se ele não existir), porque a porta da API pode ser alcançada sem o gateway.
 */
final class MetricsController
{
    public function __invoke(Request $request, Metrics $metrics): Response
    {
        if (config('metrics.require_token') === true) {
            $expected = config('metrics.token');
            if (! is_string($expected) || $expected === '') {
                return new Response('', 403);
            }

            if (! hash_equals($expected, (string) $request->bearerToken())) {
                return new Response('', 401, ['WWW-Authenticate' => 'Bearer realm="metrics"']);
            }
        }

        return new Response($metrics->render(), 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
}
