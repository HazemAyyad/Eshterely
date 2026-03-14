<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CitiesController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\CountriesController;
use App\Http\Controllers\Api\FavoritesController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\NotificationPrefsController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentCheckoutController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductImportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SessionsController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WarehousesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Config (public)
Route::get('config/bootstrap', [ConfigController::class, 'bootstrap']);

// Countries & Cities (public for address forms)
Route::get('countries', CountriesController::class);
Route::get('cities', CitiesController::class);

// Auth (public)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
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
    Route::patch('fcm-token', [MeController::class, 'updateFcmToken']);
    Route::get('notification-preferences', [NotificationPrefsController::class, 'show']);
    Route::patch('notification-preferences', [NotificationPrefsController::class, 'update']);
    Route::get('settings', [SettingsController::class, 'show']);
    Route::patch('settings', [SettingsController::class, 'update']);
    Route::get('sessions', [SessionsController::class, 'index']);
    Route::delete('sessions/{id}', [SessionsController::class, 'destroy']);
});

// Cart (auth required)
Route::middleware('auth:sanctum')->prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::get('items', [CartController::class, 'index']);
    Route::post('items', [CartController::class, 'store']);
    Route::patch('items/{id}', [CartController::class, 'update']);
    Route::delete('items/{id}', [CartController::class, 'destroy']);
    Route::delete('/', [CartController::class, 'clear']);
});

// Orders (auth required)
Route::middleware('auth:sanctum')->get('orders', [OrderController::class, 'index']);
Route::middleware('auth:sanctum')->get('orders/{id}', [OrderController::class, 'show']);
Route::middleware('auth:sanctum')->get('orders/{order}/payments', [OrderController::class, 'payments']);
Route::middleware('auth:sanctum')->post('orders/{order}/pay', PaymentCheckoutController::class);

// Payments (auth required, policy: view own only)
Route::middleware('auth:sanctum')->get('payments/{payment}', [PaymentController::class, 'show']);

// Checkout (auth required)
Route::middleware('auth:sanctum')->prefix('checkout')->group(function () {
    Route::get('review', [CheckoutController::class, 'review']);
    Route::post('confirm', [CheckoutController::class, 'confirm']);
});

// Wallet (auth required)
Route::middleware('auth:sanctum')->prefix('wallet')->group(function () {
    Route::get('/', [WalletController::class, 'show']);
    Route::get('transactions', [WalletController::class, 'transactions']);
    Route::get('activity', [WalletController::class, 'transactions']);
    Route::post('top-up', [WalletController::class, 'topUp']);
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
});

// Warehouses (public)
Route::get('warehouses', WarehousesController::class);

// Product import (auth required)
Route::middleware('auth:sanctum')->post('products/import-from-url', [ProductImportController::class, 'importFromUrl']);
