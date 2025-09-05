<?php

return [
    // Toggle OIDC login integration
    'enabled' => (bool) env('OIDC_ENABLED', false),

    // Provider issuer base URL, e.g. https://your-tenant.auth0.com or https://login.microsoftonline.com/{tenant}/v2.0
    'issuer' => env('OIDC_ISSUER'),

    // OAuth client credentials
    'client_id' => env('OIDC_CLIENT_ID'),
    'client_secret' => env('OIDC_CLIENT_SECRET'),

    // Callback URL (defaults to APP_URL + /auth/oidc/callback)
    'redirect_uri' => env('OIDC_REDIRECT_URI', env('APP_URL') . '/auth/oidc/callback'),

    // Space-separated scopes, default to email + profile
    'scopes' => env('OIDC_SCOPES', 'openid profile email'),

    // TLS peer verification (keep true in production)
    'verify_tls' => (bool) env('OIDC_VERIFY_TLS', true),

    // Optional leeway in seconds for clock skew
    'leeway' => (int) env('OIDC_LEEWAY', 60),
];

