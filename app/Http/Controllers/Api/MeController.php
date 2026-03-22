<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\User;
use App\Services\Cart\ResetCartItemsReviewStatusService;
use App\Services\Fcm\DeviceTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class MeController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $defaultAddress = $user->addresses()->where('is_default', true)->first();

        return response()->json([
            'display_name' => $user->display_name ?? $user->name,
            'verified' => (bool) $user->verified,
            'last_verified_at' => $user->last_verified_at?->format('M j, Y'),
            'full_legal_name' => $user->full_name ?? $user->name,
            'date_of_birth' => $user->date_of_birth?->format('M j, Y'),
            'primary_address' => $defaultAddress?->address_line ?? '',
            'primary_address_country' => $defaultAddress?->country?->name ?? '',
            'is_default' => true,
            'is_address_locked' => $defaultAddress?->is_locked ?? false,
            'avatar_url' => $user->avatar_url,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_legal_name' => 'nullable|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
        ]);

        $user = $request->user();
        if (isset($validated['full_legal_name'])) {
            $user->full_name = $validated['full_legal_name'];
            $user->name = $validated['full_legal_name'];
        }
        if (isset($validated['display_name'])) {
            $user->display_name = $validated['display_name'];
        }
        if (array_key_exists('date_of_birth', $validated)) {
            $user->date_of_birth = $validated['date_of_birth'];
        }
        $user->save();

        return response()->json(['message' => 'Updated', 'user' => $user]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => 'required|image|max:2048']);

        $user = $request->user();
        if ($user->avatar_url) {
            Storage::disk('public')->delete($user->avatar_url);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar_url = Storage::url($path);
        $user->save();

        return response()->json(['avatar_url' => $user->avatar_url]);
    }

    public function compliance(Request $request): JsonResponse
    {
        // Hardening: avoid placeholder compliance requirements in production.
        // If/when a real KYC/compliance module exists, this endpoint can be backed by real checks.
        return response()->json([
            'action_required' => false,
            'expiry_date' => null,
            'description' => null,
        ]);
    }

    public function addresses(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->with(['country', 'city'])->orderByRaw('is_default DESC')->get();

        return response()->json(AddressResource::collection($addresses)->resolve());
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_id' => 'required', // can be id or code
            'country_name' => 'required|string',
            'city_id' => 'nullable',
            'city_name' => 'nullable|string',
            'address_line' => 'nullable|string',
            'street_address' => 'nullable|string',
            'area_district' => 'nullable|string',
            'building_villa_suite' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'is_default' => 'boolean',
            'nickname' => 'nullable|string|max:100',
            'address_type' => 'nullable|in:home,office,other',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $user = $request->user();

        $country = Country::where('code', $validated['country_id'])->orWhere('id', $validated['country_id'])->first();
        if (!$country) {
            return response()->json(['message' => 'Invalid country'], 422);
        }
        $cityId = null;
        if (!empty($validated['city_id'])) {
            $city = City::where('country_id', $country->id)->where(function ($q) use ($validated) {
                $q->where('code', $validated['city_id'])->orWhere('id', $validated['city_id']);
            })->first();
            $cityId = $city?->id;
        }

        if ($validated['is_default'] ?? false) {
            $user->addresses()->update(['is_default' => false]);
        }

        $line = $validated['address_line'] ?? trim(implode(', ', array_filter([
            $validated['street_address'] ?? '',
            $validated['area_district'] ?? '',
            $validated['city_name'] ?? '',
            $validated['country_name'] ?? '',
        ])));

        $address = Address::create([
            'user_id' => $user->id,
            'country_id' => $country->id,
            'city_id' => $cityId,
            'address_line' => $line,
            'street_address' => $validated['street_address'] ?? null,
            'area_district' => $validated['area_district'] ?? null,
            'building_villa_suite' => $validated['building_villa_suite'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'nickname' => $validated['nickname'] ?? null,
            'address_type' => $validated['address_type'] ?? 'home',
            'lat' => $validated['lat'] ?? null,
            'lng' => $validated['lng'] ?? null,
        ]);

        if ($validated['is_default'] ?? false) {
            app(ResetCartItemsReviewStatusService::class)($user->id);
        }

        return (new AddressResource($address->load(['country', 'city'])))
            ->response()
            ->setStatusCode(201);
    }

    public function updateAddress(Request $request, int $id): JsonResponse
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);
        $wasDefault = (bool) $address->is_default;

        $validated = $request->validate([
            'country_id' => 'sometimes|exists:countries,id',
            'country_name' => 'sometimes|string',
            'city_id' => 'nullable|exists:cities,id',
            'city_name' => 'nullable|string',
            'address_line' => 'nullable|string',
            'street_address' => 'nullable|string',
            'area_district' => 'nullable|string',
            'building_villa_suite' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'is_default' => 'boolean',
            'nickname' => 'nullable|string|max:100',
            'address_type' => 'nullable|in:home,office,other',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        if ($validated['is_default'] ?? false) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address->fill(array_filter($validated));
        $address->save();

        if (($validated['is_default'] ?? false) && ! $wasDefault) {
            app(ResetCartItemsReviewStatusService::class)($request->user()->id);
        }

        return response()->json(new AddressResource($address->fresh()->load(['country', 'city'])));
    }

    public function setDefaultAddress(Request $request, int $id): JsonResponse
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);
        $wasAlreadyDefault = (bool) $address->is_default;
        $request->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        if (! $wasAlreadyDefault) {
            app(ResetCartItemsReviewStatusService::class)($request->user()->id);
        }

        return response()->json(['message' => 'Updated']);
    }

    public function updateFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:500'],
            'device_type' => ['nullable', 'string', 'max:20'],
            'platform' => ['nullable', 'string', 'max:30'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'device_model' => ['nullable', 'string', 'max:191'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();
        app(DeviceTokenService::class)->upsertToken(
            $user,
            $validated['fcm_token'],
            $validated['device_type'] ?? null,
            $validated['platform'] ?? null,
            $validated['device_name'] ?? null,
            $validated['app_version'] ?? null
        );

        $current = $user->currentAccessToken();
        if ($current && Schema::hasColumn('personal_access_tokens', 'device_model')) {
            $deviceModel = $validated['device_model'] ?? $validated['device_name'] ?? null;
            $updates = [];
            if (is_string($deviceModel) && $deviceModel !== '') {
                $updates['device_model'] = mb_substr($deviceModel, 0, 191);
            }
            if (($validated['device_type'] ?? null) !== null && (string) $validated['device_type'] !== '') {
                $updates['device_type'] = $validated['device_type'];
            }
            if ($updates !== []) {
                $updates['updated_at'] = now();
                DB::table('personal_access_tokens')->where('id', $current->id)->update($updates);
            }
        }

        return response()->json(['message' => 'FCM token updated']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 400);
        }

        $user->update(['password' => $validated['password']]);

        // Hardening: revoke other tokens after password change (keep current session).
        $current = $user->currentAccessToken();
        if ($current) {
            $user->tokens()->whereKeyNot($current->id)->delete();
        } else {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'Password updated']);
    }

    /**
     * Summary for Security & Access screen in the mobile app.
     */
    public function security(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokens = $user->tokens()->orderByDesc('last_used_at')->orderByDesc('created_at')->get();
        $first = $tokens->first();

        return response()->json([
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
            'active_sessions_count' => $tokens->count(),
            'recent_activity_preview' => $first
                ? (string) (($first->name ?: 'Session').' • '.(($first->last_used_at ?? $first->created_at)?->diffForHumans() ?? ''))
                : '',
            'change_password_hint' => 'Use a strong password you don\'t use elsewhere.',
        ]);
    }

    public function updateTwoFactor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->update([
            'two_factor_enabled' => $validated['enabled'],
        ]);

        return response()->json([
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
            'message' => $validated['enabled'] ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled',
        ]);
    }
}
