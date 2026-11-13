<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\Preorder;
use App\Models\RestaurantTable;
use App\Services\PosSettings;
use App\Services\PreorderService;
use App\Support\MenuBookContact;
use App\Support\PreorderInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HtmlResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pre-orders taken from the menu book. The page is the book itself, for staff to open in front of a
 * guest or to share as a link or picture; a list of the pre-orders taken sits beside it.
 */
class PreorderController extends Controller
{
    /** What each button does: from which statuses, and to which. */
    private const TRANSITIONS = [
        'complete' => Preorder::COMPLETED,
        'cancel' => Preorder::CANCELLED,
    ];

    public function __construct(private readonly PreorderService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('preorders.view');

        $branch = $this->currentBranch($request);

        $categories = MenuCategory::query()
            ->where('branch_id', $branch->id)
            ->active()
            ->ordered()
            ->with(['items' => fn ($query) => $query->available()->with(['variants', 'addOns', 'media'])->orderBy('sort_order')])
            ->get()
            ->filter(fn (MenuCategory $category): bool => $category->items->isNotEmpty())
            ->values();

        $preorders = Preorder::query()
            ->where('branch_id', $branch->id)
            ->with('table:id,name')
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (Preorder $preorder): array => $this->present($preorder))
            ->values();

        return Inertia::render('admin/preorders/Index', [
            'branch' => $branch->only(['id', 'name', 'name_bn', 'code', 'address', 'phone', 'vat_percent', 'vat_inclusive', 'service_charge_percent']),
            'categories' => $categories->toArray(),
            'contact' => MenuBookContact::for($branch),
            'tables' => RestaurantTable::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'area', 'seats']),
            'preorders' => $preorders,
            'shareUrl' => route('web.preorder', ['branchCode' => strtolower($branch->code)]),
            'placed' => session()->pull('preorder_placed'),
            'canManage' => (bool) $request->user('staff')?->can('preorders.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('preorders.manage');

        $branch = $this->currentBranch($request);
        $user = $request->user('staff');

        $validated = $request->validate(PreorderInput::rules($branch->id, withTable: true), [
            'items.required' => __('preorders.cart_empty'),
            'items.min' => __('preorders.cart_empty'),
        ]);

        $preorder = $this->service->place($branch, $validated['items'], PreorderInput::details($validated), Preorder::SOURCE_STAFF, $user->id);

        activity()->performedOn($preorder)->causedBy($user)->log('preorders.created');

        return back()->with('preorder_placed', $this->placed($preorder));
    }

    public function transition(Request $request, Preorder $preorder, string $action): RedirectResponse
    {
        $this->authorize('preorders.manage');

        abort_unless($request->user('staff')->canAccessBranch($preorder->branch_id), 404);
        abort_unless(isset(self::TRANSITIONS[$action]), 404);

        if ($preorder->status !== Preorder::PENDING) {
            return back()->with('error', __('preorders.cannot_change'));
        }

        $status = self::TRANSITIONS[$action];
        $preorder->update(['status' => $status]);

        activity()->performedOn($preorder)->causedBy($request->user('staff'))->log('preorders.'.$status);

        return back()->with('success', __('preorders.status_'.$status.'_done'));
    }

    /**
     * The printable copy for the guest, as HTML like the other slips.
     */
    public function print(Request $request, Preorder $preorder): HtmlResponse
    {
        $this->authorize('preorders.view');

        abort_unless($request->user('staff')->canAccessBranch($preorder->branch_id), 404);

        return $this->slip($preorder, autoPrint: $request->boolean('auto'));
    }

    public static function slip(Preorder $preorder, bool $autoPrint): HtmlResponse
    {
        $preorder->load(['branch', 'table']);

        return response()
            ->view('receipts.preorder', [
                'preorder' => $preorder,
                'layout' => PosSettings::for($preorder->branch)['invoice_layout'],
                'autoPrint' => $autoPrint,
            ])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * What the book shows after a pre-order is placed.
     *
     * @return array<string, mixed>
     */
    public static function placed(Preorder $preorder): array
    {
        $preorder->loadMissing('table');

        return [
            'order_no' => $preorder->order_no,
            'customer_mobile' => $preorder->customer_mobile,
            'address' => $preorder->address,
            'party_size' => $preorder->party_size,
            'expected_at' => $preorder->expected_at?->toIso8601String(),
            'table' => $preorder->table?->name,
            'note' => $preorder->note,
            'total' => (string) $preorder->total,
            'advance_amount' => (string) $preorder->advance_amount,
            'balance_due' => number_format($preorder->balanceDue(), 2, '.', ''),
            'items' => $preorder->items,
            'customer_name' => $preorder->customer_name,
            'sms_sent' => $preorder->sms_sent_at !== null,
            'print_url' => $preorder->source === Preorder::SOURCE_STAFF
                ? route('admin.preorders.print', ['preorder' => $preorder->id, 'auto' => 1])
                : route('web.preorder.slip', ['token' => $preorder->token]),
            'slip_url' => route('web.preorder.slip', ['token' => $preorder->token]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Preorder $preorder): array
    {
        return [
            'id' => $preorder->id,
            'order_no' => $preorder->order_no,
            'status' => $preorder->status,
            'source' => $preorder->source,
            'customer_name' => $preorder->customer_name,
            'customer_mobile' => $preorder->customer_mobile,
            'address' => $preorder->address,
            'party_size' => $preorder->party_size,
            'expected_at' => $preorder->expected_at?->toIso8601String(),
            'table' => $preorder->table?->name,
            'note' => $preorder->note,
            'items' => $preorder->items,
            'item_count' => $preorder->itemCount(),
            'total' => (string) $preorder->total,
            'advance_amount' => (string) $preorder->advance_amount,
            'balance_due' => number_format($preorder->balanceDue(), 2, '.', ''),
            'sms_sent' => $preorder->sms_sent_at !== null,
            'created_at' => $preorder->created_at->toIso8601String(),
            'print_url' => route('admin.preorders.print', ['preorder' => $preorder->id]),
        ];
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
