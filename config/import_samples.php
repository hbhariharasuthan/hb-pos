<?php
return [

'brands' => [
    'headers' => ['name', 'code', 'description', 'is_active'],
    'rows' => [
        ['Apple', 'APL', 'Electronics brand', 1],
        ['Samsung', 'SAM', 'Mobile brand', 1],
    ],
],

'categories' => [
    'headers' => ['name', 'code', 'description', 'is_active'],
    'rows' => [
        ['Mobiles', 'MOB', 'Mobile category', 1],
    ],
],

'products' => [
        'headers' => [
            'name',
            'sku',
            'barcode',
            'category_code',
            'brand_code',
            'description',
            'cost_price',
            'selling_price',
            'stock_quantity',
            'min_stock_level',
            'unit',
            'is_active',
        ],
        'rows' => [
            [
                'iPhone 14',
                'IP14-128',
                '8901234567890',
                'MOBILE',
                'APPLE',
                'Apple smartphone',
                50000,
                65000,
                10,
                2,
                'pcs',
                1
            ],
            [
                'HP Keyboard',
                'HP-KB-01',
                '',
                'COMPUTER',
                'HP',
                'USB Keyboard',
                700,
                1200,
                25,
                5,
                'pcs',
                1
            ],
        ],
    ],

];