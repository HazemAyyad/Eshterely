<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Fcm\DeviceTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
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
            'mode' => ['nullable', 'string', 'in:signup,reset'],
            'fcm_token' => ['nullable', 'string', 'max:500'],
            'device_type' => ['nullable', 'string', 'max:20'],
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

        $token = $user->createToken('mobile-app')->plainTextToken;

        $this->upsertFcmToken($user, $request->fcm_token, $request->device_type);

        return response()->json([
            'token' => $token,
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
            'device_type' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user->tokens()->delete();
        $token = $user->createToken('mobile-app')->plainTextToken;

        $this->upsertFcmToken($user, $request->fcm_token, $request->device_type);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    private function upsertFcmToken(User $user, ?string $fcmToken, ?string $deviceType): void
    {
        if (empty($fcmToken)) {
            return;
        }
        app(DeviceTokenService::class)->upsertToken($user, $fcmToken, $deviceType);
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
}
