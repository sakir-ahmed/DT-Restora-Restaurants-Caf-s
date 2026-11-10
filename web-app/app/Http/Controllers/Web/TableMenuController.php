<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Sales\CreateOrderAction;
use App\Enums\OrderChannel;
use App\Enums\OrderType;
use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\RestaurantTable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Ordering from the QR code on a table. The code carries a long random secret, not the table
 * name, and each table can issue a new one at any time: the old printed code then lands on the
 * "expired" page. An order placed here goes straight to the kitchen and shows up on the POS
 * with the table already chosen.
 */
class TableMenuController extends Controller
{
    private const SECURITY_HEADERS = [
        'Cache-Control' => 'no-store, private',
        'Referrer-Policy' => 'no-referrer',
        'X-Robots-Tag' => 'noindex, nofollow',
    ];

    public function show(Request $request, string $token): SymfonyResponse
    {
        $table = RestaurantTable::findByQrToken($token);

        if ($table === null) {
            return $this->expired($request);
        }

        $branch = $table->branch;

        $categories = MenuCategory::query()
            ->where('branch_id', $branch->id)
            ->active()
            ->ordered()
            ->with(['items' => fn ($query) => $query->available()->with(['variants', 'addOns', 'media'])->orderBy('sort_order')])
            ->get()
            ->filter(fn (MenuCategory $category): bool => $category->items->isNotEmpty())
            ->values();

        $response = Inertia::render('web/TableMenu', [
            'token' => $token,
            'table' => $table->only(['name', 'area', 'seats']),
            'branch' => $branch->only(['name', 'name_bn', 'code', 'address', 'phone', 'vat_percent', 'vat_inclusive', 'service_charge_percent']),
            'categories' => $categories->toArray(),
            'ordering' => $this->orderingState($table),
            'placed' => session()->pull('table_order'),
        ])->toResponse($request);

        return $response->withHeaders(self::SECURITY_HEADERS);
    }

    public function place(Request $request, string $token, CreateOrderAction $action): SymfonyResponse
    {
        $table = RestaurantTable::findByQrToken($token);

        if ($table === null) {
            return $this->expired($request);
        }

        $branch = $table->branch;

        $ordering = $table->qrOrdering();

        if (! $ordering['allowed']) {
            throw ValidationException::withMessages(['order' => $this->closedMessage($ordering)]);
        }

        // A guest at a table can order a few times; a script cannot flood the kitchen.
        $guestKey = 'table-order:'.$table->id.':'.sha1((string) $request->ip());
        $tableKey = 'table-order:'.$table->id;

        if (RateLimiter::tooManyAttempts($guestKey, 6) || RateLimiter::tooManyAttempts($tableKey, 20)) {
            throw ValidationException::withMessages(['order' => __('tablemenu.too_many_orders')]);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.menu_item_id' => ['required', 'integer', Rule::exists('menu_items', 'id')->where('branch_id', $branch->id)->where('is_available', true)],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:menu_item_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.add_ons' => ['nullable', 'array', 'max:10'],
            'items.*.add_ons.*.id' => ['required', 'integer', 'exists:menu_item_addons,id'],
            'items.*.add_ons.*.qty' => ['nullable', 'integer', 'min:1', 'max:9'],
            'guest_name' => ['nullable', 'string', 'max:60'],
            'guest_mobile' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:200'],
        ], [
            'items.required' => __('tablemenu.cart_empty'),
            'items.min' => __('tablemenu.cart_empty'),
        ]);

        RateLimiter::hit($guestKey, 600);
        RateLimiter::hit($tableKey, 600);

        $order = $action->execute(
            branch: $branch,
            type: OrderType::DineIn,
            lines: $validated['items'],
            tableId: $table->id,
            note: $this->guestNote($validated),
            channel: OrderChannel::Qr,
            terminalCode: 'QR',
        );

        broadcast(new OrderCreated($branch->id, [
            'order_id' => $order->id,
            'invoice_no' => $order->invoice_no,
            'order_type' => $order->order_type->value,
            'channel' => OrderChannel::Qr->value,
            'table' => $table->name,
            'items' => $order->items->map(fn ($item): array => ['name' => $item->name, 'quantity' => $item->quantity])->all(),
        ]));

        return redirect()->route('web.table', ['token' => $token])->with('table_order', [
            'invoice_no' => $order->invoice_no,
            'total' => (string) $order->total,
            'track' => route('web.track', ['token' => $order->tracking_token]),
        ]);
    }

    /**
     * @return array{allowed: bool, reason: ?string, until: ?string, message: ?string}
     */
    private function orderingState(RestaurantTable $table): array
    {
        $ordering = $table->qrOrdering();

        return [
            'allowed' => $ordering['allowed'],
            'reason' => $ordering['reason'],
            // A moment in time, so each guest's phone shows it in their own clock.
            'until' => $ordering['until']?->toIso8601String(),
            'message' => $ordering['allowed'] ? null : $this->closedMessage($ordering),
        ];
    }

    /**
     * @param  array{allowed: bool, reason: ?string, until: ?Carbon}  $ordering
     */
    private function closedMessage(array $ordering): string
    {
        return $ordering['reason'] === 'reserved' ? __('tablemenu.table_reserved') : __('tablemenu.table_in_use');
    }

    /**
     * Codes printed before this version used the table name or a number in the link.
     * They stay out of service the same way a rotated code does.
     */
    public function legacy(Request $request): SymfonyResponse
    {
        return $this->expired($request);
    }

    private function expired(Request $request): SymfonyResponse
    {
        return Inertia::render('web/TableInvalid')
            ->toResponse($request)
            ->setStatusCode(410)
            ->withHeaders(self::SECURITY_HEADERS);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function guestNote(array $validated): ?string
    {
        $parts = array_filter([
            filled($validated['guest_name'] ?? null) ? Str::limit(trim((string) $validated['guest_name']), 60, '') : null,
            filled($validated['guest_mobile'] ?? null) ? Str::limit(trim((string) $validated['guest_mobile']), 20, '') : null,
            filled($validated['note'] ?? null) ? Str::limit(trim((string) $validated['note']), 200, '') : null,
        ]);

        return $parts === [] ? null : __('tablemenu.note_prefix').' '.implode(' | ', $parts);
    }
}
