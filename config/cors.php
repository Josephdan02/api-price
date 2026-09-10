<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | PRICE API — FASE 6.4.8 (auditoría final).
    |
    | El cliente principal es una app Android nativa (Bearer token), que no
    | aplica la política CORS del navegador. Esta configuración mínima habilita
    | únicamente los endpoints de la API para un eventual frontend web o
    | herramientas de prueba sin introducir dominios arbitrarios.
    |
    | En producción se puede restringir orígenes mediante:
    |   CORS_ALLOWED_ORIGINS=https://dominio1,https://dominio2
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Acta-Pages', 'X-Acta-Documento-Id'],

    'max_age' => 0,

    'supports_credentials' => false,

];