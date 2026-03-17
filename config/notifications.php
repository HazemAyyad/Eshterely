<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin notification: app navigation targets (route_key)
    | Used in the admin send form and FCM payload for deep-linking.
    |--------------------------------------------------------------------------
    */
    'route_keys' => [
        'notifications' => 'الإشعارات',
        'order_details' => 'تفاصيل الطلب',
        'shipment_details' => 'تفاصيل الشحنة',
        'cart' => 'السلة',
        'wallet' => 'المحفظة',
        'profile' => 'الملف الشخصي',
        'offers' => 'العروض',
        'home' => 'الرئيسية',
    ],

    /*
    |--------------------------------------------------------------------------
    | Target entity types (target_type)
    | When "none" is selected, target_id is not required.
    |--------------------------------------------------------------------------
    */
    'target_types' => [
        'none' => 'بدون (إشعار عام)',
        'order' => 'طلب',
        'shipment' => 'شحنة',
        'offer' => 'عرض',
        'wallet_transaction' => 'معاملة محفظة',
        'product' => 'منتج',
    ],

    /*
    |--------------------------------------------------------------------------
    | Action button label presets (action_label)
    | Key = value sent to app; value = Arabic label in admin form.
    | Use "custom" in form to allow free-text action_label.
    |--------------------------------------------------------------------------
    */
    'action_labels' => [
        'view_details' => 'عرض التفاصيل',
        'track_shipment' => 'تتبع الشحنة',
        'open_wallet' => 'فتح المحفظة',
        'open_cart' => 'فتح السلة',
        'see_offers' => 'عرض العروض',
        'view_notification' => 'عرض الإشعار',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route pattern for action_route generation
    | route_key => pattern with {id} placeholder (replaced by target_id).
    | Used when target_type is not "none" and no manual action_route override.
    |--------------------------------------------------------------------------
    */
    'route_patterns' => [
        'order_details' => '/orders/{id}',
        'shipment_details' => '/shipments/{id}',
        'wallet' => '/wallet',
        'profile' => '/profile',
        'offers' => '/offers',
        'home' => '/',
        'notifications' => '/notifications',
        'cart' => '/cart',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification image upload
    |--------------------------------------------------------------------------
    */
    'image' => [
        'disk' => 'public',
        'directory' => 'notifications',
        'max_size_kb' => 512,
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ],

];
