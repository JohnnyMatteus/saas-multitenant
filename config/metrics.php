<?php

return [

    /*
    | "apcu": memória compartilhada entre workers (FrankenPHP/PHP-FPM).
    | "in_memory": por processo. Usado nos testes.
    */
    'storage' => env('METRICS_STORAGE', 'apcu'),

    'namespace' => env('METRICS_NAMESPACE', 'saas'),

];
