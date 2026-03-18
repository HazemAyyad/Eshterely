<?php

namespace App\Http\Controllers\Admin\Config;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PaymentGatewaysController extends Controller
{
    public function edit(): View
    {
        $row = null;
        if (Schema::hasTable('payment_gateway_settings')) {
            $row = DB::table('payment_gateway_settings')->first();
        }

        $values = [
            'default_gateway' => $row->default_gateway ?? 'square',
            'square_enabled' => (bool) ($row->square_enabled ?? true),
            'square_environment' => $row->square_environment ?? 'sandbox',
            'square_application_id' => $row->square_application_id ?? '',
            'square_access_token' => $row->square_access_token ?? '',
            'square_location_id' => $row->square_location_id ?? '',
            'square_webhook_signature_key' => $row->square_webhook_signature_key ?? '',
            'square_webhook_notification_url' => $row->square_webhook_notification_url ?? '',

            'stripe_enabled' => (bool) ($row->stripe_enabled ?? false),
            'stripe_environment' => $row->stripe_environment ?? 'test',
            'stripe_currency_default' => $row->stripe_currency_default ?? 'USD',
            'stripe_publishable_key' => $row->stripe_publishable_key ?? '',
            'stripe_secret_key' => $row->stripe_secret_key ?? '',
            'stripe_webhook_secret' => $row->stripe_webhook_secret ?? '',
        ];

        return view('admin.config.payment-gateways.edit', ['values' => $values]);
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        if (! Schema::hasTable('payment_gateway_settings')) {
            return redirect()->back()->with('error', __('admin.error'));
        }

        $validated = $request->validate([
            'default_gateway' => 'required|in:square,stripe',

            'square_enabled' => 'nullable|boolean',
            'square_environment' => 'nullable|in:sandbox,production',
            'square_application_id' => 'nullable|string|max:100',
            'square_access_token' => 'nullable|string',
            'square_location_id' => 'nullable|string|max:100',
            'square_webhook_signature_key' => 'nullable|string',
            'square_webhook_notification_url' => 'nullable|url|max:500',

            'stripe_enabled' => 'nullable|boolean',
            'stripe_environment' => 'nullable|in:test,live',
            'stripe_currency_default' => 'nullable|string|max:10',
            'stripe_publishable_key' => 'nullable|string|max:200',
            'stripe_secret_key' => 'nullable|string',
            'stripe_webhook_secret' => 'nullable|string',
        ]);

        $existing = DB::table('payment_gateway_settings')->first();
        $wasEmpty = $existing === null;
        $existing = $existing ?: (object) [];
        $rowId = is_object($existing) && isset($existing->id) ? (int) $existing->id : 1;

        $now = now();

        // Helpers: keep old secrets when admin submits empty inputs.
        $maybeKeep = static function (?string $new, ?string $old): ?string {
            $n = is_string($new) ? trim($new) : '';
            return $n !== '' ? $n : ($old !== null ? (string) $old : null);
        };

        $data = [
            'default_gateway' => $validated['default_gateway'],
            'square_enabled' => (bool) ($validated['square_enabled'] ?? false),
            'square_environment' => $validated['square_environment'] ?? 'sandbox',
            'square_application_id' => $validated['square_application_id'] ?? null,
            'square_access_token' => $maybeKeep($validated['square_access_token'] ?? null, $existing->square_access_token ?? null),
            'square_location_id' => $maybeKeep($validated['square_location_id'] ?? null, $existing->square_location_id ?? null),
            'square_webhook_signature_key' => $maybeKeep($validated['square_webhook_signature_key'] ?? null, $existing->square_webhook_signature_key ?? null),
            'square_webhook_notification_url' => $maybeKeep($validated['square_webhook_notification_url'] ?? null, $existing->square_webhook_notification_url ?? null),

            'stripe_enabled' => (bool) ($validated['stripe_enabled'] ?? false),
            'stripe_environment' => $validated['stripe_environment'] ?? 'test',
            'stripe_currency_default' => $validated['stripe_currency_default'] ?? 'USD',
            'stripe_publishable_key' => $maybeKeep($validated['stripe_publishable_key'] ?? null, $existing->stripe_publishable_key ?? null),
            'stripe_secret_key' => $maybeKeep($validated['stripe_secret_key'] ?? null, $existing->stripe_secret_key ?? null),
            'stripe_webhook_secret' => $maybeKeep($validated['stripe_webhook_secret'] ?? null, $existing->stripe_webhook_secret ?? null),
            'updated_at' => $now,
        ];

        if ($wasEmpty) {
            $data['created_at'] = $now;
        }

        // Basic sanity checks when gateways are enabled.
        if ($data['square_enabled']) {
            $required = [
                'square_access_token' => $data['square_access_token'],
                'square_location_id' => $data['square_location_id'],
                'square_webhook_signature_key' => $data['square_webhook_signature_key'],
                'square_webhook_notification_url' => $data['square_webhook_notification_url'],
            ];
            foreach ($required as $k => $v) {
                if (empty((string) $v)) {
                    return back()->with('error', "Missing required {$k}.")->withInput();
                }
            }
        }

        if ($data['stripe_enabled']) {
            $required = [
                'stripe_secret_key' => $data['stripe_secret_key'],
                'stripe_webhook_secret' => $data['stripe_webhook_secret'],
            ];
            foreach ($required as $k => $v) {
                if (empty((string) $v)) {
                    return back()->with('error', "Missing required {$k}.")->withInput();
                }
            }
        }

        // Ensure default gateway is enabled.
        if ($data['default_gateway'] === 'square' && ! $data['square_enabled']) {
            $data['default_gateway'] = $data['stripe_enabled'] ? 'stripe' : 'square';
        }
        if ($data['default_gateway'] === 'stripe' && ! $data['stripe_enabled']) {
            $data['default_gateway'] = $data['square_enabled'] ? 'square' : 'stripe';
        }

        DB::table('payment_gateway_settings')->updateOrInsert(['id' => $rowId], $data);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.success')]);
        }

        return redirect()->route('admin.config.payment-gateways.edit')->with('success', __('admin.success'));
    }
}

