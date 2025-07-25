<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://192.168.0.26:8000', 'http://192.168.0.26:8001', 'http://192.168.1.206:8000', 'http://172.24.176.1:8001', 'http://192.168.1.206:8001', 'http://172.25.192.1:8000', 'http://172.25.192.1:8001', 'http://172.25.192.1:8000', 'http://172.25.192.1:8001', 'http://192.168.208.1:8000', 'http://192.168.1.203:8000', 'http://192.168.64.1:8001', 'http://192.168.20.27:8000', 'http://192.168.1.114:8000', 'http://192.168.1.114:8001', 'http://192.168.1.114:8002', 'http://192.168.1.116:8001', 'http://localhost:8001', 'http://172.20.10.2:8001'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,];
