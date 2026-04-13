<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyWalletFundingRequestSubmitted;
use App\Models\WalletTopupRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WalletFundingRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = WalletTopupRequest::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'wallet_topup_requests' => $rows->map(fn (WalletTopupRequest $r) => $this->serialize($r)),
        ]);
    }

    public function storeWire(Request $request): JsonResponse
    {
        return $this->storeManual($request, WalletTopupRequest::METHOD_WIRE, [
            'reference' => 'nullable|string|max:255',
            'sender_name' => 'nullable|string|max:255',
            'sender_email' => 'nullable|email|max:255',
            'sender_phone' => 'nullable|string|max:64',
            'bank_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);
    }

    public function storeZelle(Request $request): JsonResponse
    {
        return $this->storeManual($request, WalletTopupRequest::METHOD_ZELLE, [
            'reference' => 'nullable|string|max:255',
            'sender_email' => 'nullable|email|max:255',
            'sender_phone' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:5000',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);
    }

    private function storeManual(Request $request, string $method, array $extraRules): JsonResponse
    {
        $t0 = microtime(true);
        $trace = function (string $phase, array $extra = []) use ($t0, $method): void {
            Log::info('wallet_funding.manual', array_merge([
                'phase' => $phase,
                'method' => $method,
                'ms' => (int) round((microtime(true) - $t0) * 1000),
            ], $extra));
        };

        $trace('request_received');

        $validated = $request->validate(array_merge([
            'amount' => 'required|numeric|min:1',
            'currency' => 'nullable|string|size:3',
        ], $extraRules));

        $trace('validation_done');

        if ($method === WalletTopupRequest::METHOD_ZELLE) {
            $em = trim((string) ($validated['sender_email'] ?? ''));
            $ph = trim((string) ($validated['sender_phone'] ?? ''));
            if ($em === '' && $ph === '') {
                return response()->json([
                    'message' => 'Provide your Zelle sender email or phone.',
                    'error_key' => 'zelle_sender_required',
                ], 422);
            }
        }

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $trace('proof_upload_start', ['bytes' => $request->file('proof')?->getSize()]);
            $proofPath = $request->file('proof')->store('wallet-topup-proofs', 'public');
            $trace('proof_upload_end', ['stored' => $proofPath]);
        }

        $currency = strtoupper(trim((string) ($validated['currency'] ?? 'USD')));

        $trace('db_create_start');
        $row = WalletTopupRequest::create([
            'user_id' => $request->user()->id,
            'method' => $method,
            'amount' => round((float) $validated['amount'], 2),
            'currency' => $currency,
            'reference' => $validated['reference'] ?? null,
            'sender_name' => $validated['sender_name'] ?? null,
            'sender_email' => $validated['sender_email'] ?? null,
            'sender_phone' => $validated['sender_phone'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'proof_file' => $proofPath,
            'notes' => $validated['notes'] ?? null,
            'status' => WalletTopupRequest::STATUS_PENDING,
        ]);
        $trace('db_create_end', ['id' => $row->id]);

        // Queue notifications: with database/redis the worker runs FCM/in-app off the HTTP worker.
        // With sync, defer so work runs in terminate (after response is sent; see Symfony send + fastcgi_finish_request).
        $id = $row->id;
        defer(function () use ($id): void {
            NotifyWalletFundingRequestSubmitted::dispatch($id);
        });

        $trace('serialize_start');
        $payload = [
            'wallet_topup_request' => $this->serialize($row),
        ];
        $trace('serialize_end');

        $trace('response_ready', ['total_ms' => (int) round((microtime(true) - $t0) * 1000)]);

        return response()->json($payload, 201);
    }

    private function serialize(WalletTopupRequest $r): array
    {
        $proofUrl = null;
        if (is_string($r->proof_file) && $r->proof_file !== '') {
            $proofUrl = Storage::disk('public')->url($r->proof_file);
        }

        $hasProof = is_string($r->proof_file) && $r->proof_file !== '';

        return [
            'id' => (string) $r->id,
            'method' => $r->method,
            'amount' => (float) $r->amount,
            'currency' => $r->currency,
            'reference' => $r->reference,
            'sender_name' => $r->sender_name,
            'sender_email' => $r->sender_email,
            'sender_phone' => $r->sender_phone,
            'bank_name' => $r->bank_name,
            'notes' => $r->notes,
            'proof_url' => $proofUrl,
            'proof_attached' => $hasProof,
            'status' => $r->status,
            'admin_notes' => $r->admin_notes,
            'team_message' => $r->admin_notes,
            'rejection_reason' => $r->status === WalletTopupRequest::STATUS_REJECTED
                ? $r->admin_notes
                : null,
            'created_at' => $r->created_at?->toIso8601String(),
            'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            'approved_at' => $r->approved_at?->toIso8601String(),
        ];
    }
}
