<?php

return [

    /*
    | "apcu": memória compartilhada entre workers (FrankenPHP/PHP-FPM).
    | "in_memory": por processo. Usado nos testes.
    */
    'storage' => env('METRICS_STORAGE', 'apcu'),

    'namespace' => env('METRICS_NAMESPACE', 'saas'),

    /*
    | Token Bearer exigido em /metrics (o Prometheus o envia com `authorization`
    | no scrape_config). Fora de local/testing a rota é fechada: sem token
    | configurado ela responde 403, e com ele, 401 a quem não o apresenta. Isso
    | cobre quem alcança a porta da API sem passar pelo gateway.
    */
    'token' => env('METRICS_TOKEN'),

    'require_token' => (bool) env('METRICS_REQUIRE_TOKEN', ! in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),

];
