<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Delivery partners
|--------------------------------------------------------------------------
|
| The partners the system knows how to talk to. Admins cannot add or
| rename a partner; they switch it on or off and fill the fields listed
| here. "api" partners also have a live adapter (see PlatformAdapters).
|
| Field names: commission_percent is always shown. The others are
| environment, chain_id, client_id, client_secret, webhook_secret and
| vendor_map (one outlet or store id per branch). Optional logo: a path
| under public/ (partners without one show a coloured initial mark).
*/

return [
    'partners' => [
        'foodpanda' => [
            'name' => 'Foodpanda',
            'logo' => 'images/partners/foodpanda.svg',
            'commission' => '25.00',
            'api' => true,
            'fields' => ['environment', 'chain_id', 'client_id', 'client_secret', 'webhook_secret', 'vendor_map'],
        ],
        'ubereats' => [
            'name' => 'Uber Eats',
            'logo' => 'images/partners/ubereats.svg',
            'commission' => '25.00',
            'api' => true,
            'fields' => ['environment', 'client_id', 'client_secret', 'vendor_map'],
        ],
        'pathao_food' => [
            'name' => 'Pathao Food',
            'logo' => 'images/partners/pathao_food.png',
            'commission' => '20.00',
            'api' => false,
            'fields' => ['webhook_secret'],
        ],
        'foodi' => [
            'name' => 'Foodi',
            'commission' => '18.00',
            'api' => false,
            'fields' => ['webhook_secret'],
        ],
        'hungrynaki' => [
            'name' => 'HungryNaki',
            'commission' => '22.00',
            'api' => false,
            'fields' => ['webhook_secret'],
        ],
        'shohoz_food' => [
            'name' => 'Shohoz Food',
            'commission' => '20.00',
            'api' => false,
            'fields' => ['webhook_secret'],
        ],
    ],
];
