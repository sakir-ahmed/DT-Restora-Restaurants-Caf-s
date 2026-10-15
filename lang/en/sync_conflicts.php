<?php

declare(strict_types=1);

return [
    'title' => 'Sync conflicts',
    'subtitle' => 'Offline items the server could not accept.',
    'conflict_discarded' => 'Conflicting item discarded.',
    'retried' => 'Item retried and accepted.',
    'retry_failed' => 'The retry still conflicts.',
    'no_conflicts' => 'No sync conflicts. Everything synced cleanly.',
    'recent' => 'Recently synced',
    'client_id' => 'Client ID',
    'type' => 'Type',
    'reason' => 'Reason',
    'discard' => 'Discard',
    'retry' => 'Retry',
    'reasons' => [
        'NEGATIVE_STOCK' => 'Not enough stock for this order',
        'VALIDATION_FAILED' => 'The item is no longer valid',
        'SERVER_ERROR' => 'Server error while applying',
    ],
];
