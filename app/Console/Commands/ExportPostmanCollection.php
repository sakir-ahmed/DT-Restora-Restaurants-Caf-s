<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ExportPostmanCollection extends Command
{
    protected $signature = 'api:postman {--path=postman/dt-restora.postman_collection.json}';

    protected $description = 'Export the Scramble OpenAPI document as a Postman collection';

    public function handle(): int
    {
        $response = app()->handle(Request::create('/api-docs.json', 'GET'));
        $document = json_decode($response->getContent(), true);

        if (! is_array($document) || ! isset($document['paths'])) {
            $this->error('OpenAPI document was not available.');

            return self::FAILURE;
        }

        $serverUrl = $document['servers'][0]['url'] ?? '/api';
        $serverUrl = is_string($serverUrl) ? rtrim($serverUrl, '/') : '/api';
        $baseUrl = str_starts_with($serverUrl, 'http') ? $serverUrl : 'http://localhost'.$serverUrl;

        $items = [];

        foreach ($document['paths'] as $path => $operations) {
            if (! is_array($operations)) {
                continue;
            }

            foreach ($operations as $method => $operation) {
                if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true) || ! is_array($operation)) {
                    continue;
                }

                $items[] = [
                    'name' => $operation['summary'] ?? strtoupper($method).' '.$path,
                    'request' => [
                        'method' => strtoupper($method),
                        'header' => [
                            ['key' => 'Accept', 'value' => 'application/json'],
                            ['key' => 'Authorization', 'value' => 'Bearer {{token}}'],
                        ],
                        'url' => '{{baseUrl}}'.$path,
                    ],
                ];
            }
        }

        $collection = [
            'info' => [
                'name' => 'DT Restora API',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [
                ['key' => 'baseUrl', 'value' => $baseUrl],
                ['key' => 'token', 'value' => ''],
            ],
            'item' => $items,
        ];

        $path = base_path((string) $this->option('path'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->info('Wrote '.$path.' ('.count($items).' requests).');

        return self::SUCCESS;
    }
}
