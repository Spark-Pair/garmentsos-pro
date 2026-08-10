<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Licensing Enforcement
    |--------------------------------------------------------------------------
    |
    | Client builds should enable licensing and enforcement. A separate explicit
    | development bypass is available for local/demo builds.
    |
    */
    'enabled' => env('LICENSE_ENABLED', true),

    'client_id' => env('LICENSE_CLIENT_ID', ''),

    'client_name' => env('LICENSE_CLIENT_NAME', ''),

    'license_key' => env('LICENSE_KEY', ''),

    'expires_at' => env('LICENSE_EXPIRES_AT', ''),

    'server_url' => env('LICENSE_CHECK_URL', env('LICENSE_SERVER_URL', 'https://www.sparkpair.dev/api/licenses/verify')),

    'register_url' => env('LICENSE_REGISTER_URL', 'https://www.sparkpair.dev/api/licenses/register-install'),

    'request_demo_url' => env('LICENSE_REQUEST_DEMO_URL', 'https://www.sparkpair.dev/api/licenses/request-demo'),

    'auto_register' => env('LICENSE_AUTO_REGISTER', true),

    'enforcement_enabled' => env('LICENSE_ENFORCEMENT_ENABLED', true),

    'development_bypass' => env('LICENSE_DEVELOPMENT_BYPASS', false),

    'last_check_at' => env('LICENSE_LAST_CHECK_AT', ''),

    'status' => env('LICENSE_STATUS', 'active'),

    'installation_mode' => env('INSTALLATION_MODE', 'local_lan'),

    /*
    |--------------------------------------------------------------------------
    | Public Verification Key
    |--------------------------------------------------------------------------
    |
    | This is a public key only. Never ship private signing keys to client PCs.
    |
    */
    'public_key' => env('LICENSE_PUBLIC_KEY', ''),

    'offline_grace_days' => (int) env('LICENSE_GRACE_DAYS', env('LICENSE_OFFLINE_GRACE_DAYS', 7)),

    'expiring_soon_days' => (int) env('LICENSE_EXPIRING_SOON_DAYS', 14),

    'cache_path' => storage_path('app/license/license.json'),

    'verify_cache_path' => storage_path('app/license/verify-cache.json'),

    'last_response_cache_path' => storage_path('app/license/last-response.json'),

    'registration_cache_path' => storage_path('app/license/registration-cache.json'),

    'request_cache_path' => storage_path('app/license/request-cache.json'),

    'identity_path' => storage_path('app/license/installation.json'),

    'install_id_path' => storage_path('app/install-id.txt'),

    'request_timeout_seconds' => (int) env('LICENSE_REQUEST_TIMEOUT_SECONDS', 3),

    'status_cache_seconds' => (int) env('LICENSE_STATUS_CACHE_SECONDS', 900),

    'default_enforcement_mode' => 'readonly',
];
