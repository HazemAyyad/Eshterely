<?php

/**
 * Product import engine metadata for storefronts (separate from DB `featured_stores` UI records).
 * Priority: higher = preferred when ordering; Amazon stays above eBay/Walmart.
 */
return [
    'stores' => [
        [
            'key' => 'amazon',
            'label' => 'Amazon',
            'supported' => true,
            'has_structured' => true,
            'priority' => 100,
        ],
        [
            'key' => 'ebay',
            'label' => 'eBay',
            'supported' => true,
            'has_structured' => true,
            'priority' => 50,
        ],
        [
            'key' => 'walmart',
            'label' => 'Walmart',
            'supported' => true,
            'has_structured' => true,
            'priority' => 50,
        ],
    ],
];
