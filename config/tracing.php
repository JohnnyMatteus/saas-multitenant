<?php

return [

    /*
    | Exportação de spans por OTLP/HTTP (JSON). Sem endpoint o tracer é um no-op:
    | nada é exportado, mas o trace_id do `traceparent` recebido continua nos logs.
    | Ex.: http://jaeger:4318 (o caminho /v1/traces é acrescentado).
    */
    'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT'),

    'service_name' => env('OTEL_SERVICE_NAME', 'saas-multitenant-api'),

    // Fração de traces novos amostrados; quem chama (gateway) decide pelos traces que já chegam com flag.
    'sample_ratio' => (float) env('OTEL_TRACES_SAMPLER_ARG', 1.0),

    // Exportar nunca pode segurar a requisição por muito tempo.
    'timeout_seconds' => (float) env('OTEL_EXPORTER_OTLP_TIMEOUT', 2),

];
