<?php

return [

    /*
    | "jwks": busca as chaves públicas no endpoint JWKS do Keycloak (padrão).
    | "local": usa uma chave pública PEM local. Usado nos testes e em ambientes
    | sem IdP. Nunca há chave privada versionada neste repositório.
    */
    'mode' => env('AUTH_JWT_MODE', 'jwks'),

    'issuer' => env('AUTH_JWT_ISSUER', 'http://localhost:8080/realms/estudios'),

    'audience' => env('AUTH_JWT_AUDIENCE', 'studio-api'),

    'leeway_seconds' => (int) env('AUTH_JWT_LEEWAY', 30),

    'jwks' => [
        'uri' => env('AUTH_JWT_JWKS_URI', 'http://localhost:8080/realms/estudios/protocol/openid-connect/certs'),
        'cache_ttl_seconds' => (int) env('AUTH_JWT_JWKS_CACHE_TTL', 600),
        'min_refresh_interval_seconds' => (int) env('AUTH_JWT_JWKS_MIN_REFRESH', 30),
        'timeout_seconds' => (int) env('AUTH_JWT_JWKS_TIMEOUT', 3),
    ],

    'local' => [
        'kid' => env('AUTH_JWT_LOCAL_KID', 'local-dev'),
        'public_key_path' => env('AUTH_JWT_LOCAL_PUBLIC_KEY_PATH'),
    ],

    'claims' => [
        'tenant' => env('AUTH_JWT_TENANT_CLAIM', 'tenant'),
    ],

];
