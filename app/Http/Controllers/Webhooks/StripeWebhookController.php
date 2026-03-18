<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {}

    /**
     * POST /api/webhooks/stripe — handle Stripe event notifications.
     */
    public function __invoke(Request $request): Response
    {
        $gateway = $this->gatewayManager->resolve('stripe');
        return $gateway->handleWebhook($request);
    }
}

