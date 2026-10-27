<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Tables booked for a guest for a stretch of time. While a booking waits for its guests the
 * tables' QR codes stay closed; once the party is seated they open. A booking can carry a
 * pre-order (sent to the kitchen on seating) and booking money (taken off the bill).
 */
class Reservation extends Model
{
    use BelongsToBranch, HasFactory;

    public const BOOKED = 'booked';

    public const SEATED = 'seated';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const NO_SHOW = 'no_show';

    public const STATUSES = [self::BOOKED, self::SEATED, self::COMPLETED, self::CANCELLED, self::NO_SHOW];

    /** Bookings that still hold their tables. */
    public const HOLDING = [self::BOOKED, self::SEATED];

    public const DEPOSIT_NONE = 'none';

    public const DEPOSIT_HELD = 'held';

    public const DEPOSIT_APPLIED = 'applied';

    public const DEPOSIT_REFUNDED = 'refunded';

    public const DEPOSIT_FORFEITED = 'forfeited';

    protected $fillable = [
        'branch_id', 'booking_no', 'guest_name', 'guest_mobile', 'party_size',
        'starts_at', 'ends_at', 'status', 'note', 'preorder', 'preorder_total',
        'deposit_amount', 'deposit_applied', 'deposit_status', 'deposit_method', 'deposit_reference',
        'deposit_paid_at', 'deposit_closed_at', 'deposit_order_id', 'created_by',
    ];

    /** What a new booking starts with, before the database fills in its own defaults. */
    protected $attributes = [
        'status' => self::BOOKED,
        'deposit_status' => self::DEPOSIT_NONE,
        'deposit_amount' => 0,
        'deposit_applied' => 0,
        'preorder_total' => 0,
    ];

    protected $casts = [
        'party_size' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'preorder' => 'array',
        'preorder_total' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'deposit_applied' => 'decimal:2',
        'deposit_paid_at' => 'datetime',
        'deposit_closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $reservation): void {
            if (blank($reservation->booking_no)) {
                $reservation->booking_no = self::newBookingNumber();
            }
        });
    }

    /**
     * A short number guests can read out over the phone, like RSV-1028-K7QD.
     */
    public static function newBookingNumber(): string
    {
        do {
            // Letters and digits that are easy to misread over the phone are left out of the random part.
            $suffix = strtr(Str::upper(Str::random(4)), ['0' => 'Q', 'O' => 'W', '1' => 'K', 'I' => 'M', 'L' => 'N']);
            $number = 'RSV-'.now()->format('md').'-'.$suffix;
        } while (self::query()->withoutBranchScope()->where('booking_no', $number)->exists());

        return $number;
    }

    /** @return BelongsToMany<RestaurantTable, $this> */
    public function tables(): BelongsToMany
    {
        return $this->belongsToMany(RestaurantTable::class, 'reservation_table')->withPivot('position')->orderBy('reservation_table.position');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function depositOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'deposit_order_id');
    }

    /**
     * Money still held for the guests: collected, and neither used on a bill nor given back.
     */
    public function depositBalance(): float
    {
        if ($this->deposit_status !== self::DEPOSIT_HELD && $this->deposit_status !== self::DEPOSIT_APPLIED) {
            return 0.0;
        }

        return max(0.0, round((float) $this->deposit_amount - (float) $this->deposit_applied, 2));
    }

    /**
     * Bookings that overlap the given stretch of time.
     *
     * @param  Builder<Reservation>  $builder
     * @return Builder<Reservation>
     */
    public function scopeOverlapping(Builder $builder, Carbon $from, Carbon $to): Builder
    {
        return $builder->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    /**
     * Bookings that include the given table.
     *
     * @param  Builder<Reservation>  $builder
     * @param  array<int, int>  $tableIds
     * @return Builder<Reservation>
     */
    public function scopeForTables(Builder $builder, array $tableIds): Builder
    {
        return $builder->whereHas('tables', fn (Builder $query) => $query->withoutGlobalScopes()->whereIn('restaurant_tables.id', $tableIds));
    }

    /**
     * The booking that holds this table right now, if any: still waiting for its guests and inside its time.
     */
    public static function holdingNow(int $tableId, ?Carbon $now = null): ?self
    {
        $now ??= now();

        return self::query()
            ->withoutBranchScope()
            ->forTables([$tableId])
            ->where('status', self::BOOKED)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * Whether another booking on any of these tables clashes with this stretch of time.
     *
     * @param  array<int, int>  $tableIds
     */
    public static function clashes(array $tableIds, Carbon $from, Carbon $to, ?int $ignoreId = null): bool
    {
        return self::query()
            ->withoutBranchScope()
            ->forTables($tableIds)
            ->whereIn('status', self::HOLDING)
            ->overlapping($from, $to)
            ->when($ignoreId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    /**
     * Guests are being served at this table: seat the booking that is running or about to start,
     * or return the party already seated here. Null when nobody booked it.
     */
    public static function seatAt(int $tableId, ?Carbon $now = null): ?self
    {
        $now ??= now();

        $reservation = self::query()
            ->withoutBranchScope()
            ->forTables([$tableId])
            ->where('ends_at', '>', $now)
            ->where(function (Builder $query) use ($now): void {
                $query->where('status', self::SEATED)
                    ->orWhere(fn (Builder $booked) => $booked->where('status', self::BOOKED)->where('starts_at', '<=', $now->copy()->addHour()));
            })
            ->orderBy('starts_at')
            ->first();

        if ($reservation !== null && $reservation->status === self::BOOKED) {
            $reservation->update(['status' => self::SEATED]);
        }

        return $reservation;
    }
}
