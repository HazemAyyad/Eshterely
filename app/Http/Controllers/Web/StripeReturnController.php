<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Payments\StripeWebhookService;
use Illuminate\Http\JsonResponse;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;

class StripeReturnController extends Controller
{
    public function success(): JsonResponse
    {
        $sessionId = (string) request()->query('session_id', '');
        if ($sessionId !== '') {
            $this->syncSession($sessionId);
        }

        return response()->json([
            'message' => 'Stripe payment success',
            'session_id' => $sessionId,
        ]);
    }

    public function cancel(): JsonResponse
    {
        return response()->json([
            'message' => 'Stripe payment cancelled',
            'session_id' => (string) request()->query('session_id', ''),
        ]);
    }

    private function syncSession(string $sessionId): void
    {
        try {
            $secret = (string) config('stripe.secret_key', '');
            if ($secret === '') {
                return;
            }
            Stripe::setApiKey($secret);
            $session = StripeCheckoutSession::retrieve($sessionId, []);
            $object = json_decode($session->toJSON(), true);
            if (! is_array($object)) {
                return;
            }

            app(StripeWebhookService::class)->handleEvent('checkout.session.completed', [
                'id' => 'stripe_return_' . $sessionId,
                'type' => 'checkout.session.completed',
                'data' => ['object' => $object],
            ]);
        } catch (\Throwable $e) {
            // Best-effort fallback when webhook delivery is delayed/unavailable.
        }
    }
}
