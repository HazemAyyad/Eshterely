<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Activity\UserActivityLogger;
use App\Services\Fcm\DeviceTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(
        protected UserActivityLogger $activityLogger
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^[0-9]+$/', 'min:10', 'max:15', 'unique:users,phone'],
            'full_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)->numbers()->symbols()],
            'country_id' => ['nullable', 'string', 'max:20'],
            'city_id' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'phone' => $request->phone,
            'full_name' => $request->full_name,
            'name' => $request->full_name,
            'display_name' => explode(' ', $request->full_name)[0] ?? $request->full_name,
            'email' => null,
            'password' => $request->password,
        ]);

        $code = config('app.debug') ? '123456' : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        OtpCode::create([
            'phone' => $user->phone,
            'code' => $code,
            'mode' => 'signup',
            'expires_at' => now()->addMinutes(10),
        ]);

        $payload = [
            'message' => 'OTP sent',
            'user_id' => $user->id,
            'phone' => $user->phone,
        ];
        if (config('app.debug')) {
            $payload['otp'] = $code;
        }
        return response()->json($payload, 201);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $rules = [
            'phone' => ['required', 'string', 'regex:/^[0-9]+$/', 'min:10', 'max:15'],
            'code' => ['required', 'string', 'size:6'],
            'mode' => ['nullable', 'string', 'in:signup,reset,login'],
            'fcm_token' => ['nullable', 'string', 'max:500'],
            'device_type' => ['nullable', 'string', 'max:40'],
            'platform' => ['nullable', 'string', 'max:30'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'device_model' => ['nullable', 'string', 'max:191'],
            'app_country' => ['nullable', 'string', 'max:191'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ];
        if ($request->mode === 'reset') {
            $rules['password'] = ['required', 'confirmed', Password::min(8)->numbers()->symbols()];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mode = $request->mode ?? 'signup';

        $otp = OtpCode::where('phone', $request->phone)
            ->where('code', $request->code)
            ->where('mode', $mode)
            ->where('expires_at', '>', now())
            ->where('used', false)
            ->first();

        if (!$otp && config('app.debug') && $request->code === '123456') {
            $otp = OtpCode::where('phone', $request->phone)
                ->where('mode', $mode)
                ->where('expires_at', '>', now())
                ->where('used', false)
                ->first();
        }

        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $otp->update(['used' => true]);

        $user = User::where('phone', $request->phone)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($mode === 'reset' && $request->password) {
            $user->update(['password' => $request->password]);
        }

        // Password reset: revoke all sessions. Other flows keep existing device tokens for multi-device.
        if ($mode === 'reset') {
            $user->tokens()->delete();
        }

        $newToken = $user->createToken('mobile-app');
        $this->attachSanctumSessionMeta($newToken->accessToken, $request);
        $this->upsertFcmToken($user, $request);

        if ($mode !== 'reset') {
            $newId = $newToken->accessToken->id;
            $isNew = $this->activityLogger->isNewDevice($user, $request, $newId);
            $this->activityLogger->logAuthLogin($user, $request, $isNew, $newId);
        }

        return response()->json([
            'token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^[0-9]+$/', 'min:10', 'max:15'],
            'password' => ['required'],
            'fcm_token' => ['nullable', 'string', 'max:500'],
            'device_type' => ['nullable', 'string', 'max:40'],
            'platform' => ['nullable', 'string', 'max:30'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'device_model' => ['nullable', 'string', 'max:191'],
            'app_country' => ['nullable', 'string', 'max:191'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $newToken = $user->createToken('mobile-app');
        $this->attachSanctumSessionMeta($newToken->accessToken, $request);
        $this->upsertFcmToken($user, $request);

        $newId = $newToken->accessToken->id;
        $isNew = $this->activityLogger->isNewDevice($user, $request, $newId);
        $this->activityLogger->logAuthLogin($user, $request, $isNew, $newId);

        return response()->json([
            'token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    private function attachSanctumSessionMeta(PersonalAccessToken $token, Request $request): void
    {
        if (! Schema::hasColumn('personal_access_tokens', 'device_type')) {
            return;
        }
        $ip = $request->ip();
        $countryHint = $request->header('X-App-Country');
        if (! is_string($countryHint) || trim($countryHint) === '') {
            $countryHint = $request->input('app_country');
        }
        $countryHint = is_string($countryHint) ? trim($countryHint) : '';
        $locationLabel = $countryHint !== ''
            ? $countryHint.($ip ? ' · IP: '.$ip : '')
            : ($ip ? 'IP: '.$ip : null);
        if (is_string($locationLabel) && mb_strlen($locationLabel) > 191) {
            $locationLabel = mb_substr($locationLabel, 0, 188).'...';
        }

        $deviceModel = $request->input('device_model') ?: $request->input('device_name');
        $deviceModel = is_string($deviceModel) ? mb_substr($deviceModel, 0, 191) : null;

        $row = [
            'device_type' => $request->input('device_type'),
            'ip_address' => $ip,
            'location_label' => $locationLabel,
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('personal_access_tokens', 'device_model')) {
            $row['device_model'] = $deviceModel;
        }

        DB::table('personal_access_tokens')->where('id', $token->id)->update($row);
    }

    private function upsertFcmToken(User $user, Request $request): void
    {
        $fcmToken = $request->input('fcm_token');
        if (empty($fcmToken)) {
            return;
        }
        app(DeviceTokenService::class)->upsertToken(
            $user,
            $fcmToken,
            $request->input('device_type'),
            $request->input('platform'),
            $request->input('device_name'),
            $request->input('app_version'),
        );
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^[0-9]+$/', 'min:10', 'max:15'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('phone', $request->phone)->first();
        if (!$user) {
            return response()->json(['message' => 'Phone not found'], 404);
        }

        $code = config('app.debug') ? '123456' : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        OtpCode::create([
            'phone' => $user->phone,
            'code' => $code,
            'mode' => 'reset',
            'expires_at' => now()->addMinutes(10),
        ]);

        $payload = [
            'message' => 'OTP sent',
            'phone' => $user->phone,
        ];
        if (config('app.debug')) {
            $payload['otp'] = $code;
        }
        return response()->json($payload);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Send OTP for passwordless login (user must already exist).
     */
    public function loginOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^[0-9]+$/', 'min:10', 'max:15'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('phone', $request->phone)->first();
        if (! $user) {
            return response()->json(['message' => 'Phone not registered'], 404);
        }

        OtpCode::query()
            ->where('phone', $request->phone)
            ->where('mode', 'login')
            ->where('used', false)
            ->update(['used' => true]);

        $code = config('app.debug') ? '123456' : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        OtpCode::create([
            'phone' => $user->phone,
            'code' => $code,
            'mode' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        $payload = [
            'message' => 'OTP sent',
            'phone' => $user->phone,
        ];
        if (config('app.debug')) {
            $payload['otp'] = $code;
        }

        return response()->json($payload);
    }
}
