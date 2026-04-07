<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CitiesController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\CountriesController;
use App\Http\Controllers\Api\DraftOrderController;
use App\Http\Controllers\Api\ShippingQuoteController;
use App\Http\Controllers\Api\FavoritesController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\NotificationPrefsController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderPaymentController;
use App\Http\Controllers\Api\PaymentCheckoutController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ImportedProductController;
use App\Http\Controllers\Api\ProductImportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SessionsController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\ShipmentsController;
use App\Http\Controllers\Api\WarehousesController;
use App\Http\Controllers\Webhooks\SquareWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Config (public)
Route::get('config/bootstrap', [ConfigController::class, 'bootstrap']);

// Webhooks (public)
Route::post('webhooks/square', SquareWebhookController::class)->name('api.webhooks.square');
Route::post('webhooks/stripe', StripeWebhookController::class)->name('api.webhooks.stripe');

// Countries & Cities (public for address forms)
Route::get('countries', CountriesController::class);
Route::get('cities', CitiesController::class);

// Auth (public)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('login-otp', [AuthController::class, 'loginOtp']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// Me (auth required)
Route::middleware('auth:sanctum')->prefix('me')->group(function () {
    Route::get('/', [MeController::class, 'profile']);
    Route::patch('/', [MeController::class, 'updateProfile']);
    Route::post('avatar', [MeController::class, 'uploadAvatar']);
    Route::get('compliance', [MeController::class, 'compliance']);
    Route::get('addresses', [MeController::class, 'addresses']);
    Route::post('addresses', [MeController::class, 'storeAddress']);
    Route::patch('addresses/{id}', [MeController::class, 'updateAddress']);
    Route::post('addresses/{id}/default', [MeController::class, 'setDefaultAddress']); // Set as default address
    Route::post('change-password', [MeController::class, 'changePassword']);
    Route::get('security', [MeController::class, 'security']);
    Route::patch('two-factor', [MeController::class, 'updateTwoFactor']);
    Route::get('login-history', [SessionsController::class, 'loginHistory']);
    Route::patch('fcm-token', [MeController::class, 'updateFcmToken']);
    Route::get('notification-preferences', [NotificationPrefsController::class, 'show']);
    Route::patch('notification-preferences', [NotificationPrefsController::class, 'update']);
    Route::get('settings', [SettingsController::class, 'show']);
    Route::patch('settings', [SettingsController::class, 'update']);
    Route::get('sessions', [SessionsController::class, 'index']);
    Route::delete('sessions/{id}', [SessionsController::class, 'destroy']);
    Route::post('sessions/revoke-others', [SessionsController::class, 'revokeOthers']);
    Route::post('sessions/revoke-all', [SessionsController::class, 'revokeAll']);
});

// Cart (auth required)
Route::middleware('auth:sanctum')->prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::get('items', [CartController::class, 'index']);
    Route::post('shipping-estimate', [CartController::class, 'estimateShipping']);
    Route::post('items', [CartController::class, 'store']);
    Route::post('create-draft-order', [CartController::class, 'createDraftOrder']);
    Route::patch('items/{id}', [CartController::class, 'update']);
    Route::delete('items/{id}', [CartController::class, 'destroy']);
    Route::delete('/', [CartController::class, 'clear']);
});

// Draft orders (auth required, ownership enforced)
Route::middleware('auth:sanctum')->prefix('draft-orders')->group(function () {
    Route::get('/', [DraftOrderController::class, 'index']);
    Route::get('{draft_order}', [DraftOrderController::class, 'show']);
    Route::post('{draft_order}/checkout', [DraftOrderController::class, 'checkout']);
});

// Orders (auth required)
Route::middleware('auth:sanctum')->get('orders', [OrderController::class, 'index']);
Route::middleware('auth:sanctum')->get('orders/{id}', [OrderController::class, 'show']);
Route::middleware('auth:sanctum')->get('orders/{order}/payments', [OrderController::class, 'payments']);
Route::middleware('auth:sanctum')->post('orders/{order}/start-payment', [OrderPaymentController::class, 'startPayment']);
Route::middleware('auth:sanctum')->post('orders/{order}/pay', PaymentCheckoutController::class);

// Payments (auth required, policy: view own only)
Route::middleware('auth:sanctum')->get('payments/{payment}', [PaymentController::class, 'show']);

// Checkout (auth required)
Route::middleware('auth:sanctum')->prefix('checkout')->group(function () {
    Route::get('review', [CheckoutController::class, 'review']);
    Route::post('confirm', [CheckoutController::class, 'confirm']);
    Route::post('promo/validate', [CheckoutController::class, 'validatePromo']);
});

// Wallet (auth required)
Route::middleware('auth:sanctum')->prefix('wallet')->group(function () {
    Route::get('/', [WalletController::class, 'show']);
    Route::get('transactions', [WalletController::class, 'transactions']);
    Route::get('activity', [WalletController::class, 'transactions']);
    Route::post('top-up', [WalletController::class, 'topUp']);
});

// Warehouse & outbound shipments (second payment — auth required)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('warehouse/items', [WarehouseController::class, 'items']);
    Route::post('shipments/create', [ShipmentsController::class, 'store']);
    Route::get('shipments', [ShipmentsController::class, 'index']);
    Route::post('shipments/{shipment}/pay', [ShipmentsController::class, 'pay']);
});

// Favorites (auth required)
Route::middleware('auth:sanctum')->apiResource('favorites', FavoritesController::class)->except(['update', 'show']);

// Support (auth required)
Route::middleware('auth:sanctum')->prefix('support')->group(function () {
    Route::get('tickets', [SupportController::class, 'index']);
    Route::get('inbox', [SupportController::class, 'index']);
    Route::get('tickets/{id}', [SupportController::class, 'show']);
    Route::post('tickets/{id}/messages', [SupportController::class, 'storeMessage']);
    Route::post('requests', [SupportController::class, 'store']);
});

// Notifications (auth required)
Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationsController::class, 'index']);
    Route::patch('{id}/read', [NotificationsController::class, 'markRead']);
    Route::patch('mark-all-read', [NotificationsController::class, 'markAllRead']);
    Route::delete('{id}', [NotificationsController::class, 'destroy']);
});

// Warehouses (public)
Route::get('warehouses', WarehousesController::class);

// Product import (auth required)
Route::middleware('auth:sanctum')->post('products/import-from-url', [ProductImportController::class, 'importFromUrl']);

// Imported product confirm & add-to-cart (auth required)
Route::middleware('auth:sanctum')->prefix('imported-products')->group(function () {
    Route::post('confirm', [ImportedProductController::class, 'confirm']);
    Route::get('{imported_product}', [ImportedProductController::class, 'show']);
    Route::post('{imported_product}/add-to-cart', [ImportedProductController::class, 'addToCart']);
});

// Shipping quote preview (auth required, temporary for testing calculation engine)
Route::middleware('auth:sanctum')->post('shipping/quote-preview', [ShippingQuoteController::class, 'quotePreview']);
