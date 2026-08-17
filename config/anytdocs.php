<?php

return [

    'domain' => env('APP_DOMAIN', 'localhost'),

    /*
    | Public CNAME target for custom domains (Cloudflare SSL for SaaS fallback origin).
    | Prefer this over {subdomain}.{APP_DOMAIN} whenever it is set.
    */
    'cname_target' => env('ANYTDOCS_CNAME_TARGET', env('CLOUDFLARE_FALLBACK_ORIGIN')),

    'cloudflare' => [
        'enabled' => filter_var(env('CLOUDFLARE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'api_email' => env('CLOUDFLARE_EMAIL'),
        'api_key' => env('CLOUDFLARE_API_KEY'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'fallback_origin' => env('CLOUDFLARE_FALLBACK_ORIGIN'),
    ],

    'subdomain_pattern' => '/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])$/',

    'reserved_subdomains' => [
        'www',
        'app',
        'admin',
        'api',
        'mail',
        'platform',
        'docs',
        'status',
        'blog',
        'help',
        'support',
        'billing',
        'dashboard',
        'auth',
        'login',
        'register',
        'static',
        'assets',
        'cdn',
        'email',
        'webmail',
        'portal',
        'account',
        'accounts',
        'settings',
        'onboarding',
        'staging',
        'dev',
        'test',
    ],

];
