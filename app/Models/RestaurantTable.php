<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\RestaurantTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RestaurantTable extends Model
{
    /** @use HasFactory<RestaurantTableFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = ['branch_id', 'name', 'area', 'seats', 'status', 'is_active'];

    /** The QR secret never travels with the table to the POS or the API. */
    protected $hidden = ['qr_token'];

    protected $casts = [
        'seats' => 'integer',
        'is_active' => 'boolean',
        'qr_generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {

        static::creating(function (self $table): void {
            if ($table->qr_token === null) {
                $table->qr_token = self::newQrToken();
                $table->qr_generated_at = now();
            }
        });
    }

    /**
     * A long random secret for the table QR code. Rotating it makes every
     * QR code printed before that moment stop working.
     */
    public static function newQrToken(): string
    {
        return Str::random(32);
    }

    /**
     * Whether a guest may order from this table's QR code right now.
     *
     * A table held by a booking that is still waiting for its guests, and a table in use by guests
     * who were not seated through the QR code (a waiter's order, or a table marked occupied with
     * nothing open), are closed. Several phones at one table can order together: a table only
     * held by QR orders stays open.
     *
     * @return array{allowed: bool, reason: ?string, until: ?Carbon}
     */
    public function qrOrdering(?Carbon $now = null): array
    {
        $now ??= now();

        $booking = Reservation::holdingNow($this->id, $now);

        if ($booking !== null) {
            return ['allowed' => false, 'reason' => 'reserved', 'until' => $booking->ends_at];
        }

        if ($this->status === 'occupied') {
            $channels = $this->orders()
                ->whereIn('status', array_map(fn (OrderStatus $status): string => $status->value, array_filter(OrderStatus::cases(), fn (OrderStatus $status): bool => $status->isKitchenActive())))
                ->pluck('channel');

            $onlyQr = $channels->isNotEmpty() && $channels->every(fn ($channel): bool => ($channel instanceof OrderChannel ? $channel : OrderChannel::from((string) $channel)) === OrderChannel::Qr);

            if (! $onlyQr) {
                return ['allowed' => false, 'reason' => 'occupied', 'until' => null];
            }
        }

        return ['allowed' => true, 'reason' => null, 'until' => null];
    }

    /**
     * Where the table stands right now: occupied by an order, held by a booking, or free.
     */
    public function liveState(?Carbon $now = null): string
    {
        if ($this->status === 'occupied') {
            return 'occupied';
        }

        return Reservation::holdingNow($this->id, $now) !== null ? 'reserved' : 'free';
    }

    public function rotateQrToken(): void
    {
        $this->forceFill([
            'qr_token' => self::newQrToken(),
            'qr_generated_at' => now(),
        ])->save();
    }

    /**
     * The table a QR secret belongs to, when that table and its branch are still in use.
     */
    public static function findByQrToken(string $token): ?self
    {
        if (strlen($token) !== 32) {
            return null;
        }

        $table = self::query()
            ->where('qr_token', $token)
            ->where('is_active', true)
            ->whereHas('branch', fn ($query) => $query->where('is_active', true))
            ->first();

        // Compare in constant time as well, so the lookup leaks nothing about near matches.
        return $table !== null && hash_equals((string) $table->qr_token, $token) ? $table : null;
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
