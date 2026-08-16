<?php

namespace App\Support;

use Inertia\Ssr\Gateway;
use Inertia\Ssr\Response;

/**
 * No-op SSR gateway. Prevents Inertia from HTTP-calling 127.0.0.1:13714
 * when no Node SSR process is running (Cloudflare 502 on Inertia pages).
 */
class DisabledInertiaSsrGateway implements Gateway
{
    /**
     * @param  array<string, mixed>  $page
     */
    public function dispatch(array $page): ?Response
    {
        return null;
    }
}
