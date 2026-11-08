<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('activity.view');

        $entries = Activity::query()
            ->with('causer')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Activity $activity): array => $this->present($activity))
            ->values();

        return Inertia::render('admin/activity/Index', [
            'entries' => $entries,
        ]);
    }

    /**
     * @return array{id: int|string, description: string, causer: string|null, subject_type: string|null, created_at: string|null}
     */
    private function present(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'description' => $activity->description,
            'causer' => $this->causerName($activity->causer),
            'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }

    private function causerName(?Model $causer): ?string
    {
        if ($causer instanceof User || $causer instanceof Customer) {
            return $causer->name;
        }

        return null;
    }
}
