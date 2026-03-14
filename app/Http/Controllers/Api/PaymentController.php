<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * GET /api/payments/{payment}
     * Show a single payment. Authorized via PaymentPolicy (user must own the payment).
     */
    public function show(Request $request, Payment $payment): JsonResponse|PaymentResource
    {
        $this->authorize('view', $payment);

        return new PaymentResource($payment);
    }
}
