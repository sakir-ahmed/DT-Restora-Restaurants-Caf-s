<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sync;

use App\Http\Controllers\Api\Controller;
use App\Models\SyncItem;
use App\Services\Sync\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(private readonly SyncService $sync) {}

    /**
     * Full reference data for a fresh device cache.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        return $this->success($this->sync->bootstrap($request->user()));
    }

    /**
     * Delta updates since a data version.
     */
    public function changes(Request $request): JsonResponse
    {
        $since = (int) $request->query('since', 0);

        return $this->success($this->sync->changes($request->user(), $since));
    }

    /**
     * Push queued offline writes. Each item gets its own result:
     * accepted, duplicate or conflict with a reason code.
     */
    public function push(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'max:200'],
            'items.*.client_id' => ['required', 'string', 'max:40'],
            'items.*.type' => ['required', 'in:order,payment,shift,wastage,stock_count,attendance'],
            'items.*.payload' => ['required', 'array'],
            'items.*.created_at_device' => ['nullable', 'date'],
            'items.*.data_version' => ['nullable', 'integer'],
        ]);

        $results = $this->sync->push($request->user(), $validated['items']);

        return $this->success([
            'results' => array_values($results),
            'conflicts' => count(array_filter($results, fn ($result) => $result['status'] === SyncItem::STATUS_CONFLICT)),
        ]);
    }
}
