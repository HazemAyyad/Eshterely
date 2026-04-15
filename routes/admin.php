<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CartReviewController;
use App\Http\Controllers\Admin\Config\AppConfigController;
use App\Http\Controllers\Admin\Config\FeaturedStoresController;
use App\Http\Controllers\Admin\Config\ShippingSettingsController;
use App\Http\Controllers\Admin\Config\ShippingCarrierZonesController;
use App\Http\Controllers\Admin\Config\ShippingCarrierRatesController;
use App\Http\Controllers\Admin\Config\MarketCountriesController;
use App\Http\Controllers\Admin\Config\OnboardingController;
use App\Http\Controllers\Admin\Config\PromoCodesController;
use App\Http\Controllers\Admin\Config\PromoBannersController;
use App\Http\Controllers\Admin\Config\PaymentGatewaysController;
use App\Http\Controllers\Admin\Config\SplashConfigController;
use App\Http\Controllers\Admin\Config\ThemeConfigController;
use App\Http\Controllers\Admin\Config\WarehousesController;
use App\Http\Controllers\Admin\Config\ProductImportStoreSettingsController;
use App\Http\Controllers\Admin\Config\ProductImportLogsController;
use App\Http\Controllers\Admin\Config\ProductImportTesterController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Admin\WalletRefundsController;
use App\Http\Controllers\Admin\SavedPaymentMethodsController;
use App\Http\Controllers\Admin\WalletTopupRequestsController;
use App\Http\Controllers\Admin\WalletWithdrawalsController;
use App\Http\Controllers\Admin\WarehouseReceivingController;
use App\Http\Controllers\Admin\OutboundShipmentController;
use App\Http\Controllers\Admin\OrderLineItemProcurementController;
use App\Http\Controllers\Admin\WarehouseQueueController;
use App\Http\Controllers\Admin\OutboundShipmentsAdminController;
use Illuminate\Support\Facades\Route;

Route::get('login', [AdminAuthController::class, 'showLogin'])->name('login');
Route::post('login', [AdminAuthController::class, 'login']);
Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout')->middleware('auth:admin');

Route::middleware('auth:admin')->group(function () {
    Route::get('set-locale/{lang}', function ($lang) {
        if (in_array($lang, ['ar', 'en'])) {
            session(['admin_locale' => $lang]);
        }
        return redirect()->back();
    })->name('set-locale');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Config
    Route::prefix('config')->name('config.')->group(function () {
        Route::get('theme', [ThemeConfigController::class, 'edit'])->name('theme');
        Route::patch('theme', [ThemeConfigController::class, 'update']);
        Route::get('splash', [SplashConfigController::class, 'edit'])->name('splash');
        Route::patch('splash', [SplashConfigController::class, 'update']);
        Route::get('onboarding/data', [OnboardingController::class, 'data'])->name('onboarding.data');
Route::resource('onboarding', OnboardingController::class)->except(['show'])->names('onboarding');
        Route::get('market-countries/data', [MarketCountriesController::class, 'data'])->name('market-countries.data');
Route::resource('market-countries', MarketCountriesController::class)->except(['show'])->names('market-countries');
        Route::get('featured-stores/data', [FeaturedStoresController::class, 'data'])->name('featured-stores.data');
        Route::resource('featured-stores', FeaturedStoresController::class)->except(['show'])->names('featured-stores');
        Route::get('promo-banners/data', [PromoBannersController::class, 'data'])->name('promo-banners.data');
        Route::resource('promo-banners', PromoBannersController::class)->except(['show'])->names('promo-banners');
        Route::get('promo-codes/data', [PromoCodesController::class, 'data'])->name('promo-codes.data');
        Route::resource('promo-codes', PromoCodesController::class)->except(['show'])->names('promo-codes');
        Route::get('warehouses/data', [WarehousesController::class, 'data'])->name('warehouses.data');
        Route::resource('warehouses', WarehousesController::class)->except(['show'])->names('warehouses');
        Route::get('app-config', [AppConfigController::class, 'edit'])->name('app-config');
        Route::patch('app-config', [AppConfigController::class, 'update']);

        Route::get('payment-gateways', [PaymentGatewaysController::class, 'edit'])->name('payment-gateways.edit');
        Route::patch('payment-gateways', [PaymentGatewaysController::class, 'update'])->name('payment-gateways.update');

        Route::get('shipping-settings', [ShippingSettingsController::class, 'edit'])->name('shipping-settings.edit');
        Route::patch('shipping-settings', [ShippingSettingsController::class, 'update'])->name('shipping-settings.update');

        // Product Import
        Route::get('product-import/store-settings', [ProductImportStoreSettingsController::class, 'index'])->name('product-import.store-settings.index');
        Route::get('product-import/store-settings/{setting}/edit', [ProductImportStoreSettingsController::class, 'edit'])->name('product-import.store-settings.edit');
        Route::patch('product-import/store-settings/{setting}', [ProductImportStoreSettingsController::class, 'update'])->name('product-import.store-settings.update');
        Route::get('product-import/logs', [ProductImportLogsController::class, 'index'])->name('product-import.logs.index');
        Route::get('product-import/logs/data', [ProductImportLogsController::class, 'data'])->name('product-import.logs.data');
        Route::get('product-import/tester', [ProductImportTesterController::class, 'index'])->name('product-import.tester');
        Route::post('product-import/tester/test', [ProductImportTesterController::class, 'test'])->name('product-import.tester.test');
        Route::resource('shipping-zones', ShippingCarrierZonesController::class)
            ->except(['show'])
            ->names('shipping-zones');
        Route::resource('shipping-rates', ShippingCarrierRatesController::class)
            ->except(['show'])
            ->names('shipping-rates');
    });

    // Users
    Route::get('users/data', [UserController::class, 'data'])->name('users.data');
    Route::patch('users/{user}/password', [UserController::class, 'updatePassword'])->name('users.update-password');
    Route::post('users/{user}/wallet-credit', [UserController::class, 'addWalletCredit'])->name('users.wallet-credit');
    Route::resource('users', UserController::class)->only(['index', 'show'])->names('users');

    // Orders
    Route::get('orders/data', [OrderController::class, 'data'])->name('orders.data');
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::patch('orders/{order}/review', [OrderController::class, 'review'])->name('orders.review');
    Route::patch('orders/{order}/shipping-override', [OrderController::class, 'shippingOverride'])->name('orders.shipping-override');
    Route::patch('orders/{order}/shipments/{shipment}', [OrderController::class, 'updateShipment'])->name('orders.shipments.update');
    Route::post('orders/{order}/shipments/{shipment}/events', [OrderController::class, 'addShipmentEvent'])->name('orders.shipments.events.store');
    Route::patch('orders/{order}/shipments/{shipment}/delivered', [OrderController::class, 'markShipmentDelivered'])->name('orders.shipments.delivered');

    Route::patch('order-line-items/{orderLineItem}/procurement', [OrderLineItemProcurementController::class, 'update'])->name('order-line-items.procurement');

    // Warehouse receiving queue (operations)
    Route::get('warehouse/data', [WarehouseQueueController::class, 'data'])->name('warehouse.data');
    Route::get('warehouse/order-line-items/{orderLineItem}/receive', [WarehouseQueueController::class, 'receiveForm'])->name('warehouse.receive-form');
    Route::get('warehouse', [WarehouseQueueController::class, 'index'])->name('warehouse.index');

    // Outbound shipments admin (pack / ship — customer shipments)
    Route::get('shipments/data', [OutboundShipmentsAdminController::class, 'data'])->name('shipments.data');
    Route::get('shipments/{shipment}/pack', [OutboundShipmentsAdminController::class, 'packForm'])->name('shipments.pack-form');
    Route::get('shipments/{shipment}/ship', [OutboundShipmentsAdminController::class, 'shipForm'])->name('shipments.ship-form');
    Route::get('shipments/{shipment}', [OutboundShipmentsAdminController::class, 'show'])->name('shipments.show');
    Route::get('shipments', [OutboundShipmentsAdminController::class, 'index'])->name('shipments.index');

    // Outbound shipments (user warehouse → second payment) — POST handlers (API + admin forms)
    Route::post('warehouse/order-line-items/{orderLineItem}/receive', [WarehouseReceivingController::class, 'store'])->name('warehouse.receive');
    Route::put('warehouse/order-line-items/{orderLineItem}/receive/{warehouseReceipt}', [WarehouseReceivingController::class, 'update'])->name('warehouse.receive-update');
    Route::post('shipments/{shipment}/pack', [OutboundShipmentController::class, 'pack'])->name('outbound-shipments.pack');
    Route::post('shipments/{shipment}/ship', [OutboundShipmentController::class, 'ship'])->name('outbound-shipments.ship');

    // Cart Review
    Route::get('cart-review/data', [CartReviewController::class, 'data'])->name('cart-review.data');
    Route::get('cart-review', [CartReviewController::class, 'index'])->name('cart-review.index');
    Route::patch('cart-review/{id}/review', [CartReviewController::class, 'approveOrReject'])->name('cart-review.update');
    Route::patch('cart-review/{id}/shipping', [CartReviewController::class, 'updateShipping'])->name('cart-review.shipping');
    Route::patch('cart-review/{id}/package', [CartReviewController::class, 'updatePackage'])->name('cart-review.package');
    Route::post('cart-review/{id}/recalculate-shipping', [CartReviewController::class, 'recalculateShipping'])->name('cart-review.recalculate-shipping');

    // Wallets
    Route::get('wallets/data', [WalletController::class, 'data'])->name('wallets.data');
    Route::get('wallets', [WalletController::class, 'index'])->name('wallets.index');
    Route::get('wallets/{user}', [WalletController::class, 'show'])->name('wallets.show');

    // Refund to wallet (order/shipment)
    Route::get('wallet-refunds/data', [WalletRefundsController::class, 'data'])->name('wallet-refunds.data');
    Route::get('wallet-refunds', [WalletRefundsController::class, 'index'])->name('wallet-refunds.index');
    Route::get('wallet-refunds/{walletRefund}', [WalletRefundsController::class, 'show'])->name('wallet-refunds.show');
    Route::patch('wallet-refunds/{walletRefund}/status', [WalletRefundsController::class, 'updateStatus'])->name('wallet-refunds.update-status');

    // Withdraw to bank
    Route::get('wallet-withdrawals/data', [WalletWithdrawalsController::class, 'data'])->name('wallet-withdrawals.data');
    Route::get('wallet-withdrawals', [WalletWithdrawalsController::class, 'index'])->name('wallet-withdrawals.index');
    Route::get('wallet-withdrawals/{walletWithdrawal}', [WalletWithdrawalsController::class, 'show'])->name('wallet-withdrawals.show');
    Route::patch('wallet-withdrawals/{walletWithdrawal}/status', [WalletWithdrawalsController::class, 'updateStatus'])->name('wallet-withdrawals.update-status');

    // Manual wallet funding (wire / Zelle) & saved Stripe cards
    Route::get('wallet-topup-requests/data', [WalletTopupRequestsController::class, 'data'])->name('wallet-topup-requests.data');
    Route::get('wallet-topup-requests', [WalletTopupRequestsController::class, 'index'])->name('wallet-topup-requests.index');
    Route::get('wallet-topup-requests/{walletTopupRequest}', [WalletTopupRequestsController::class, 'show'])->name('wallet-topup-requests.show');
    Route::patch('wallet-topup-requests/{walletTopupRequest}/status', [WalletTopupRequestsController::class, 'updateStatus'])->name('wallet-topup-requests.update-status');

    Route::get('saved-payment-methods/data', [SavedPaymentMethodsController::class, 'data'])->name('saved-payment-methods.data');
    Route::get('saved-payment-methods', [SavedPaymentMethodsController::class, 'index'])->name('saved-payment-methods.index');
    Route::patch('saved-payment-methods/{savedPaymentMethod}/unblock', [SavedPaymentMethodsController::class, 'unblock'])->name('saved-payment-methods.unblock');
    Route::patch('saved-payment-methods/{savedPaymentMethod}/disable', [SavedPaymentMethodsController::class, 'disable'])->name('saved-payment-methods.disable');

    // Support
    Route::get('support/data', [SupportController::class, 'data'])->name('support.data');
    Route::get('support', [SupportController::class, 'index'])->name('support.index');
    Route::get('support/{ticket}', [SupportController::class, 'show'])->name('support.show');
    Route::post('support/{ticket}/messages', [SupportController::class, 'storeMessage'])->name('support.store-message');
    Route::patch('support/{ticket}/status', [SupportController::class, 'updateStatus'])->name('support.update-status');

    // Notifications
    Route::get('notifications/send', [NotificationController::class, 'showSendForm'])->name('notifications.send');
    Route::post('notifications/send', [NotificationController::class, 'send'])->name('notifications.send.submit');
    Route::post('notifications/send-to-user/{user}', [NotificationController::class, 'sendToUser'])->name('notifications.send-to-user');
});
