<?php

use App\Http\Controllers\Webhooks\SquareWebhookController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/square', SquareWebhookController::class)->name('webhooks.square');

/*
| تشغيل أمر storage:link عبر الرابط (مفيد عندما لا يتوفر SSH أو الـ symlink لا يعمل).
| استخدم: /storage-link?token=YOUR_TOKEN
| ضع في .env: STORAGE_LINK_TOKEN=كلمة_سر_سرية
*/
Route::get('/storage-link', function () {
    $token = request()->query('token');
    $expected = config('app.storage_link_token');
    if ($expected && $token !== $expected) {
        abort(403, 'Invalid token');
    }
    Artisan::call('storage:link');
    $output = Artisan::output();
    return response()->json([
        'message' => 'storage:link executed',
        'output' => trim($output),
    ], 200, [], JSON_UNESCAPED_UNICODE);
})->name('storage.link');

// Stripe hosted checkout redirect pages (used by Stripe Checkout).
// Mobile mainly relies on webhooks + refresh, but Stripe requires valid URLs.
Route::get('/payment/stripe/success', function () {
    return response()->json([
        'message' => 'Stripe payment success',
    ]);
});

Route::get('/payment/stripe/cancel', function () {
    return response()->json([
        'message' => 'Stripe payment cancelled',
    ]);
});
