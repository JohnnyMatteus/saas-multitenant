<?php

declare(strict_types=1);

namespace App\Modules\Observability\Http\Controllers;

use App\Modules\Observability\Metrics;
use Illuminate\Http\Response;
use Prometheus\RenderTextFormat;

/**
 * Endpoint de scrape do Prometheus. Em produção fica acessível apenas pela
 * rede interna (bloqueado no gateway), pois não exige token.
 */
final class MetricsController
{
    public function __invoke(Metrics $metrics): Response
    {
        return new Response($metrics->render(), 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
}
