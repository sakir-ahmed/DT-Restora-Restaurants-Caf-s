<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Food a guest chose from the menu book ahead of the visit. Unlike a reservation it holds no
 * table and no time slot: a table, a time, a name and a mobile number are all optional.
 */
class Preorder extends Model
{
    use BelongsToBranch, HasFactory;

    public const PENDING = 'pending';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::PENDING, self::COMPLETED, self::CANCELLED];

    public const SOURCE_STAFF = 'staff';

    public const SOURCE_PUBLIC = 'public';

    protected $fillable = [
        'branch_id', 'order_no', 'token', 'source', 'customer_name', 'customer_mobile', 'party_size',
        'expected_at', 'restaurant_table_id', 'note', 'items', 'total', 'status', 'sms_sent_at', 'created_by',
    ];

    protected $attributes = [
        'status' => self::PENDING,
        'source' => self::SOURCE_STAFF,
        'total' => 0,
    ];

    protected $casts = [
        'party_size' => 'integer',
        'expected_at' => 'datetime',
        'items' => 'array',
        'total' => 'decimal:2',
        'sms_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $preorder): void {
            if (blank($preorder->order_no)) {
                $preorder->order_no = self::newOrderNumber();
            }

            if (blank($preorder->token)) {
                $preorder->token = Str::random(32);
            }
        });
    }

    /**
     * A short number a guest can read out over the phone, like PRE-1112-K7QD.
     */
    public static function newOrderNumber(): string
    {
        do {
            $suffix = strtr(Str::upper(Str::random(4)), ['0' => 'Q', 'O' => 'W', '1' => 'K', 'I' => 'M', 'L' => 'N']);
            $number = 'PRE-'.now()->format('md').'-'.$suffix;
        } while (self::query()->withoutBranchScope()->where('order_no', $number)->exists());

        return $number;
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<RestaurantTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id')->withoutGlobalScopes();
    }

    public function itemCount(): int
    {
        return (int) collect($this->items)->sum('quantity');
    }
}
