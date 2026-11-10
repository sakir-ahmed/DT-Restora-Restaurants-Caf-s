<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Services\Sms\SmsFeatures;
use App\Services\SmsPromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The SMS module: what was sent (history) and sending a promotion. Choosing
 * the provider, switching features on or off and editing the wording are in
 * Settings.
 */
class SmsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('sms.manage');

        return Inertia::render('admin/sms/Index', [
            'features' => array_values(array_map(fn (string $key): array => ['key' => $key], SmsFeatures::keys())),
            'promotionEnabled' => SmsFeatures::enabled('promotion'),
            'audiences' => fn () => app(SmsPromotionService::class)->counts(),
            'history' => Inertia::optional(fn () => $this->history($request)),
        ]);
    }

    /**
     * Send a hand-written promotion to a group of customers.
     */
    public function sendPromotion(Request $request, SmsPromotionService $promotions): RedirectResponse
    {
        $this->authorize('sms.manage');

        $data = $request->validate([
            'message' => ['required', 'string', 'max:320'],
            'audience' => ['required', Rule::in(SmsPromotionService::AUDIENCES)],
        ]);

        if (! SmsFeatures::enabled('promotion')) {
            return back()->with('error', __('sms.promotion_feature_off'));
        }

        $queued = $promotions->send($data['message'], $data['audience'], $request->user('staff'));

        return $queued === 0
            ? back()->with('error', __('sms.promotion_none'))
            : back()->with('success', __('sms.promotion_sent', ['count' => $queued]));
    }

    /**
     * One page of the SMS history, newest first, filtered from the query string.
     *
     * @return array<string, mixed>
     */
    private function history(Request $request): array
    {
        $filters = $request->validate([
            'feature' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', Rule::in([SmsLog::STATUS_SENT, SmsLog::STATUS_FAILED, SmsLog::STATUS_SKIPPED, SmsLog::STATUS_DEMO])],
            'q' => ['nullable', 'string', 'max:40'],
        ]);

        $page = SmsLog::query()
            ->with('user:id,name')
            ->when($filters['feature'] ?? null, fn ($query, $feature) => $query->where('feature', $feature))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('mobile', 'like', '%'.preg_replace('/[^0-9+]/', '', $term).'%'))
            ->latest('id')
            ->paginate(15, ['*'], 'page');

        return [
            'data' => $page->getCollection()->map(fn (SmsLog $log): array => [
                'id' => $log->id,
                'feature' => $log->feature,
                'mobile' => $log->mobile,
                'message' => $log->message,
                'status' => $log->status,
                'provider' => $log->provider,
                'response' => $log->response,
                'by' => $log->user?->name,
                'at' => $log->created_at?->toIso8601String(),
            ])->values()->all(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ];
    }
}
