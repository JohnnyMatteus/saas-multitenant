<?php

return [

    /*
    | Disco do Laravel para mídia dos estúdios (logos). "local" em testes e
    | fora do Docker; "s3" no docker-compose (LocalStack) e na AWS real.
    | O endpoint S3 é configurável via AWS_ENDPOINT (config/filesystems.php).
    */
    'disk' => env('STUDIO_MEDIA_DISK', 'local'),

    'max_logo_kilobytes' => (int) env('STUDIO_MEDIA_MAX_LOGO_KB', 512),

];
