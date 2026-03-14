<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\SquareWebhookService;
use App\Services\Payments\SquareWebhookSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SquareWebhookController extends Controller
{
    public function __construct(
        protected SquareWebhookSignatureVerifier $verifier,
        protected SquareWebhookService $webhookService
    ) {}

    /**
     * POST /webhooks/square — handle Square event notifications.
     */
    public function __invoke(Request $request): Response
    {
        $rawBody = $request->getContent();
        $signatureHeader = $request->header('x-square-hmacsha256-signature') ?? '';

        if ($rawBody === '') {
            Log::warning('Square webhook received with empty body');
            return response('', 400);
        }

        $skipVerification = config('app.env') === 'testing'
            && config('square.webhook_skip_verification', false);

        if (! $skipVerification) {
            if ($signatureHeader === '') {
                Log::warning('Square webhook signature failed: missing header');
                return response('', 403);
            }

            if (! $this->verifier->verify($rawBody, $signatureHeader)) {
                Log::warning('Square webhook signature failed: invalid signature');
                return response('', 403);
            }
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            Log::warning('Square webhook invalid JSON');
            return response('', 400);
        }

        $eventType = $payload['type'] ?? null;
        $eventId = $payload['event_id'] ?? null;

        Log::info('Square webhook received', [
            'event_type' => $eventType,
            'event_id' => $eventId,
        ]);

        $this->webhookService->handleEvent($eventType, $payload);

        return response('', 200);
    }
}
