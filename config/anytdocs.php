<?php

return [

    'domain' => env('APP_DOMAIN', 'localhost'),

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
