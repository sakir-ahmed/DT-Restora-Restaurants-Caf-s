<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReservationRequest;
use App\Jobs\SendSmsJob;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Services\PosSettings;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HtmlResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Table bookings. A booking holds one or more tables for a stretch of time: their QR codes stay
 * closed while it waits for its guests and open once they are seated. It can carry a pre-order and
 * booking money, and prints a confirmation slip for the guest.
 */
class ReservationController extends Controller
{
    /** What each button does: from which statuses, and to which. */
    private const TRANSITIONS = [
        'seat' => [[Reservation::BOOKED], Reservation::SEATED],
        'complete' => [[Reservation::SEATED], Reservation::COMPLETED],
        'cancel' => [[Reservation::BOOKED], Reservation::CANCELLED],
        'no-show' => [[Reservation::BOOKED], Reservation::NO_SHOW],
    ];

    public function __construct(private readonly ReservationService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('reservations.view');

        $branch = $this->currentBranch($request);

        // The page asks for the day on the viewer's own clock; without it, today on the server's clock.
        $from = $this->moment($request->query('from')) ?? now()->startOfDay();
        $to = $this->moment($request->query('to')) ?? $from->copy()->addDay();

        if ($to->lessThanOrEqualTo($from) || $from->diffInDays($to) > 31) {
            $to = $from->copy()->addDay();
        }

        $methods = PaymentMethod::query()->active()->orderBy('sort_order')->orderBy('id')->get();

        $reservations = Reservation::query()
            ->where('branch_id', $branch->id)
            ->overlapping($from, $to)
            ->with(['tables:id,name,area,seats'])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Reservation $reservation): array => $this->present($reservation, $methods))
            ->values();

        return Inertia::render('admin/reservations/Index', [
            'reservations' => $reservations,
            'tables' => RestaurantTable::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->get(['id', 'name', 'area', 'seats'])
                ->sortBy(fn (RestaurantTable $table): string => mb_strtolower(($table->area ?? '')."\0".$table->name), SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
            'menu' => MenuItem::query()
                ->where('branch_id', $branch->id)
                ->available()
                ->with(['variants:id,menu_item_id,name,name_bn,price,sort_order', 'addOns:id,menu_item_id,name,name_bn,price,is_active,sort_order'])
                ->orderBy('name')
                ->get(['id', 'name', 'name_bn', 'base_price', 'menu_category_id'])
                ->map(fn (MenuItem $item): array => [
                    'id' => $item->id,
                    'category_id' => $item->menu_category_id,
                    'name' => $item->name,
                    'name_bn' => $item->name_bn,
                    'base_price' => (string) $item->base_price,
                    'variants' => $item->variants->sortBy('sort_order')->map(fn ($variant): array => ['id' => $variant->id, 'name' => $variant->name, 'name_bn' => $variant->name_bn, 'price' => (string) $variant->price])->values(),
                    'add_ons' => $item->addOns->where('is_active', true)->sortBy('sort_order')->map(fn ($addOn): array => ['id' => $addOn->id, 'name' => $addOn->name, 'name_bn' => $addOn->name_bn, 'price' => (string) $addOn->price])->values(),
                ])
                ->values(),
            'menuCategories' => MenuCategory::query()
                ->where('branch_id', $branch->id)
                ->active()
                ->ordered()
                ->get(['id', 'name', 'name_bn']),
            'paymentMethods' => $methods->map(fn (PaymentMethod $method): array => ['code' => $method->code, 'name' => $method->name, 'name_bn' => $method->name_bn, 'type' => $method->type])->values(),
            'range' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'branchName' => $branch->name,
            'canManage' => (bool) $request->user('staff')?->can('reservations.manage'),
        ]);
    }

    public function store(ReservationRequest $request): RedirectResponse
    {
        $branch = Branch::query()->findOrFail($request->branchId());
        $user = $request->user('staff');

        abort_unless($user->canAccessBranch($branch->id), 404);

        $priced = $this->service->priceLines($branch, (array) $request->input('items', []));
        $deposit = round((float) $request->input('deposit_amount', 0), 2);

        $reservation = DB::transaction(function () use ($request, $branch, $user, $priced, $deposit): Reservation {
            $reservation = Reservation::query()->create($this->attributes($request, $priced) + [
                'branch_id' => $branch->id,
                'status' => Reservation::BOOKED,
                'created_by' => $user->id,
            ]);

            $reservation->tables()->sync($this->tablePositions($request));

            if ($deposit > 0) {
                $this->service->collectDeposit($reservation, $deposit, (string) $request->input('deposit_method'), $request->input('deposit_reference'), $user);
            }

            return $reservation;
        });

        activity()->performedOn($reservation)->causedBy($user)->log('reservations.created');

        $this->confirmBySms($reservation->fresh(['tables']), $branch);

        return back()->with('success', __('reservations.created', ['booking' => $reservation->booking_no]));
    }

    public function update(ReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($request->user('staff')->canAccessBranch($reservation->branch_id), 404);

        if (! in_array($reservation->status, Reservation::HOLDING, true)) {
            return back()->with('error', __('reservations.closed'));
        }

        $branch = Branch::query()->findOrFail($reservation->branch_id);
        $priced = $this->service->priceLines($branch, (array) $request->input('items', []));

        DB::transaction(function () use ($request, $reservation, $priced): void {
            $reservation->update($this->attributes($request, $priced));
            $reservation->tables()->sync($this->tablePositions($request));
        });

        activity()->performedOn($reservation)->causedBy($request->user('staff'))->log('reservations.updated');

        return back()->with('success', __('reservations.updated'));
    }

    public function transition(Request $request, Reservation $reservation, string $action): RedirectResponse
    {
        $this->authorize('reservations.manage');

        abort_unless($request->user('staff')->canAccessBranch($reservation->branch_id), 404);
        abort_unless(isset(self::TRANSITIONS[$action]), 404);

        [$from, $to] = self::TRANSITIONS[$action];

        if (! in_array($reservation->status, $from, true)) {
            return back()->with('error', __('reservations.cannot_change'));
        }

        $user = $request->user('staff');
        $message = __('reservations.status_'.$to.'_done');

        try {
            DB::transaction(function () use ($reservation, $to, $user, &$message): void {
                $reservation->update(['status' => $to]);

                // Seating the party sends their pre-ordered food to the kitchen.
                if ($to === Reservation::SEATED) {
                    $order = $this->service->sendPreorder($reservation, $user);

                    if ($order !== null) {
                        $message = __('reservations.seated_with_preorder', ['invoice' => $order->invoice_no]);
                    }
                }
            });
        } catch (ValidationException $exception) {
            return back()->with('error', (string) collect($exception->errors())->flatten()->first());
        }

        activity()->performedOn($reservation)->causedBy($user)->log('reservations.'.$to);

        return back()->with('success', $message);
    }

    public function collectDeposit(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('reservations.manage');

        abort_unless($request->user('staff')->canAccessBranch($reservation->branch_id), 404);

        $validated = $request->validate([
            'deposit_amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'deposit_method' => ['required', 'string', 'max:20'],
            'deposit_reference' => ['nullable', 'string', 'max:100'],
        ]);

        if (! in_array($reservation->status, Reservation::HOLDING, true)) {
            return back()->with('error', __('reservations.closed'));
        }

        $this->service->collectDeposit($reservation, (float) $validated['deposit_amount'], $validated['deposit_method'], $validated['deposit_reference'] ?? null, $request->user('staff'));

        return back()->with('success', __('reservations.deposit_collected'));
    }

    public function closeDeposit(Request $request, Reservation $reservation, string $outcome): RedirectResponse
    {
        $this->authorize('reservations.manage');

        abort_unless($request->user('staff')->canAccessBranch($reservation->branch_id), 404);

        $this->service->closeDeposit($reservation, $outcome, $request->user('staff'));

        return back()->with('success', __('reservations.deposit_'.$outcome.'_done'));
    }

    /**
     * The booking confirmation slip the guest takes away: booking number, time, tables,
     * pre-order and booking money, as printable HTML like the receipts.
     */
    public function confirmation(Request $request, Reservation $reservation): HtmlResponse
    {
        $this->authorize('reservations.view');

        abort_unless($request->user('staff')->canAccessBranch($reservation->branch_id), 404);

        $reservation->load(['tables:id,name,area,seats', 'branch']);

        return response()
            ->view('receipts.reservation', [
                'reservation' => $reservation,
                'depositMethod' => $reservation->deposit_method ? PaymentMethod::findByCode($reservation->deposit_method) : null,
                'layout' => PosSettings::for($reservation->branch)['invoice_layout'],
            ])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * @param  array{lines: array<int, array<string, mixed>>, total: float}  $priced
     * @return array<string, mixed>
     */
    private function attributes(ReservationRequest $request, array $priced): array
    {
        return [
            'guest_name' => trim((string) $request->input('guest_name')),
            'guest_mobile' => $request->filled('guest_mobile') ? trim((string) $request->input('guest_mobile')) : null,
            'party_size' => (int) $request->input('party_size'),
            'starts_at' => $request->moment('starts_at'),
            'ends_at' => $request->moment('ends_at'),
            'note' => $request->filled('note') ? trim((string) $request->input('note')) : null,
            'preorder' => $priced['lines'] === [] ? null : $priced['lines'],
            'preorder_total' => $priced['total'],
        ];
    }

    /**
     * The chosen tables keyed by id, remembering the order they were picked in.
     *
     * @return array<int, array{position: int}>
     */
    private function tablePositions(ReservationRequest $request): array
    {
        $positions = [];

        foreach (array_values(array_map('intval', (array) $request->input('table_ids'))) as $position => $tableId) {
            $positions[$tableId] = ['position' => $position];
        }

        return $positions;
    }

    private function confirmBySms(Reservation $reservation, Branch $branch): void
    {
        $mobile = normalize_bd_mobile((string) $reservation->guest_mobile);

        if ($mobile === '') {
            return;
        }

        SendSmsJob::deliver($mobile, __('reservations.sms_confirmed', [
            'booking' => $reservation->booking_no,
            'branch' => $branch->name,
            'tables' => $reservation->tables->pluck('name')->implode(', '),
            'when' => $reservation->starts_at->setTimezone(config('app.timezone'))->translatedFormat('D j M, g:i A'),
        ]));
    }

    /**
     * @param  Collection<int, PaymentMethod>  $methods
     * @return array<string, mixed>
     */
    private function present(Reservation $reservation, $methods): array
    {
        $method = $methods->firstWhere('code', $reservation->deposit_method);

        return [
            'id' => $reservation->id,
            'booking_no' => $reservation->booking_no,
            'guest_name' => $reservation->guest_name,
            'guest_mobile' => $reservation->guest_mobile,
            'party_size' => $reservation->party_size,
            'table_ids' => $reservation->tables->pluck('id')->values(),
            'tables' => $reservation->tables->map(fn (RestaurantTable $table): array => $table->only(['id', 'name', 'area', 'seats']))->values(),
            'seats' => $reservation->tables->sum('seats'),
            'starts_at' => $reservation->starts_at->toIso8601String(),
            'ends_at' => $reservation->ends_at->toIso8601String(),
            'status' => $reservation->status,
            'note' => $reservation->note,
            'preorder' => $reservation->preorder ?? [],
            'preorder_total' => (string) $reservation->preorder_total,
            'deposit' => [
                'status' => $reservation->deposit_status,
                'amount' => (string) $reservation->deposit_amount,
                'applied' => (string) $reservation->deposit_applied,
                'balance' => number_format($reservation->depositBalance(), 2, '.', ''),
                'method' => $reservation->deposit_method,
                'method_name' => $method?->name ?? $reservation->deposit_method,
                'method_name_bn' => $method?->name_bn,
                'reference' => $reservation->deposit_reference,
            ],
        ];
    }

    private function moment(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value)->setTimezone((string) config('app.timezone')) : null;
    }

    private function currentBranch(Request $request): Branch
    {
        $user = $request->user('staff');

        $branch = Branch::query()->find($user->branch_id ?? (int) session('branch_id', 0));

        return $branch ?? Branch::query()->orderBy('id')->firstOrFail();
    }
}
