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
| vendor_map (one outlet or store id per branch).
*/

return [
    'partners' => [
        'foodpanda' => [
            'name' => 'Foodpanda',
            'commission' => '25.00',
            'api' => true,
            'fields' => ['environment', 'chain_id', 'client_id', 'client_secret', 'webhook_secret', 'vendor_map'],
        ],
        'ubereats' => [
            'name' => 'Uber Eats',
            'commission' => '25.00',
            'api' => true,
            'fields' => ['environment', 'client_id', 'client_secret', 'vendor_map'],
        ],
        'pathao_food' => [
            'name' => 'Pathao Food',
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
