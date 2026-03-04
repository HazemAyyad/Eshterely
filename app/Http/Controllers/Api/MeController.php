<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\User;
use App\Models\UserDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        return response()->json([
            'action_required' => true,
            'expiry_date' => 'Mar 1, 2026',
            'description' => 'Your government-issued ID is required for international shipping compliance.',
        ]);
    }

    public function addresses(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->with(['country', 'city'])->orderByRaw('is_default DESC')->get();

        return response()->json($addresses->map(fn (Address $a) => [
            'id' => (string) $a->id,
            'address_line' => $a->address_line ?? trim(implode(', ', array_filter([$a->street_address, $a->area_district, $a->city?->name, $a->country?->name]))),
            'country_id' => $a->country?->code ?? $a->country_id,
            'country_name' => $a->country?->name ?? '',
            'city_id' => $a->city?->code ?? $a->city_id,
            'city_name' => $a->city?->name ?? '',
            'phone' => $a->phone,
            'is_default' => $a->is_default,
            'nickname' => $a->nickname,
            'address_type' => $a->address_type,
            'area_district' => $a->area_district,
            'street_address' => $a->street_address,
            'building_villa_suite' => $a->building_villa_suite,
            'is_verified' => $a->is_verified,
            'is_residential' => $a->is_residential,
            'linked_to_active_order' => $a->linked_to_active_order,
            'is_locked' => $a->is_locked,
            'lat' => $a->lat ? (float) $a->lat : null,
            'lng' => $a->lng ? (float) $a->lng : null,
        ]));
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

        return response()->json($address, 201);
    }

    public function updateAddress(Request $request, int $id): JsonResponse
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);

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

        return response()->json($address);
    }

    public function setDefaultAddress(Request $request, int $id): JsonResponse
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);
        $request->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return response()->json(['message' => 'Updated']);
    }

    public function updateFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:500'],
            'device_type' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();
        UserDeviceToken::updateOrCreate(
            ['user_id' => $user->id, 'fcm_token' => $validated['fcm_token']],
            ['device_type' => $validated['device_type'] ?? 'unknown', 'updated_at' => now()]
        );

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

        return response()->json(['message' => 'Password updated']);
    }
}
