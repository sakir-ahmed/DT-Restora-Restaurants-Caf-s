<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SyncItem;
use App\Services\Sync\SyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SyncConflictController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('sync.resolve');

        $conflicts = SyncItem::query()
            ->where('status', SyncItem::STATUS_CONFLICT)
            ->with('user:id,name')
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('admin/sync/Index', [
            'conflicts' => $conflicts,
            'recent' => SyncItem::query()
                ->where('status', SyncItem::STATUS_ACCEPTED)
                ->with('user:id,name')
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }

    /**
     * Discard a conflicting offline item after the manager reviewed it.
     */
    public function discard(Request $request, SyncItem $item): RedirectResponse
    {
        $this->authorize('sync.resolve');

        $item->update(['status' => 'discarded']);

        activity()->performedOn($item)->causedBy($request->user('staff'))->log('sync.conflict_discarded');

        return back()->with('success', __('sync.conflict_discarded'));
    }

    /**
     * Retry a conflicting item (for example after stock was topped up).
     */
    public function retry(Request $request, SyncItem $item, SyncService $sync): RedirectResponse
    {
        $this->authorize('sync.resolve');

        $result = $sync->push($request->user('staff'), [
            ['client_id' => $item->client_id.'-retry-'.time(), 'type' => $item->type, 'payload' => $item->payload],
        ]);

        $outcome = reset($result);

        if ($outcome !== false && $outcome['status'] === SyncItem::STATUS_ACCEPTED) {
            $item->update(['status' => SyncItem::STATUS_ACCEPTED, 'accepted_at' => now(), 'conflict_reason' => null]);

            return back()->with('success', __('sync.retried'));
        }

        return back()->with('error', __('sync.retry_failed'));
    }
}
