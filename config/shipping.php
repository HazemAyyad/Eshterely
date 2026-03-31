<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cart shipping quotes use only the user's default saved address (country).
    |--------------------------------------------------------------------------
    |
    | When a product import cannot extract real weight/dimensions from the store
    | page, these fallback values are used so that a shipping estimate can still
    | be shown to the user. The UI must display a warning when fallback values
    | are in use (see fallback_note_* keys).
    |
    */

    /*
     * Fallback weight in kilograms when product weight cannot be extracted.
     */
    'default_weight' => (float) env('SHIPPING_DEFAULT_WEIGHT', 0.5),

    /*
     * Fallback parcel dimensions in centimetres.
     */
    'default_dimensions' => [
        'length' => (float) env('SHIPPING_DEFAULT_LENGTH', 20),
        'width'  => (float) env('SHIPPING_DEFAULT_WIDTH', 15),
        'height' => (float) env('SHIPPING_DEFAULT_HEIGHT', 10),
        'unit'   => 'cm',
    ],

    /*
     * User-facing note shown when fallback shipping measurements are used.
     */
    'fallback_note_en' => 'Shipping cost is estimated. Exact cost will be calculated after product measurements are confirmed.',
    'fallback_note_ar' => 'سعر الشحن تقديري. يتم احتساب السعر الفعلي بعد تأكيد أبعاد المنتج.',
];
