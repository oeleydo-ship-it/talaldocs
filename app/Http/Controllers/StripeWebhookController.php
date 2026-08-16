<?php

namespace App\Http\Controllers;

use App\Support\StripeWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(private StripeWebhookHandler $handler) {}

    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (! $this->handler->verifySignature($payload, $signature, is_string($secret) ? $secret : null)) {
            Log::warning('Stripe webhook signature verification failed');

            return response('Invalid signature', 400);
        }

        /** @var array<string, mixed>|null $event */
        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response('Invalid payload', 400);
        }

        $this->handler->handle($event);

        return response('OK', 200);
    }
}
