<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'invoice_no', 'order_type', 'channel',
        'restaurant_table_id', 'waiter_id', 'cashier_id', 'customer_id', 'shift_id',
        'status', 'subtotal', 'discount_type', 'discount_value', 'discount_amount',
        'service_charge', 'vat_amount', 'delivery_fee', 'rounding', 'total',
        'food_cost', 'client_id', 'note', 'paid_at', 'paid_via_payment_id',
        'delivery_zone_id', 'delivery_address', 'scheduled_for', 'tracking_token',
        'coupon_id', 'points_redeemed',
    ];

    protected $casts = [
        'order_type' => OrderType::class,
        'channel' => OrderChannel::class,
        'status' => OrderStatus::class,
        'subtotal' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'rounding' => 'decimal:2',
        'total' => 'decimal:2',
        'food_cost' => 'decimal:4',
        'paid_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'points_redeemed' => 'integer',
    ];

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<VoidRequest, $this> */
    public function voidRequest(): HasOne
    {
        return $this->hasOne(VoidRequest::class)->latestOfMany();
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<RestaurantTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    /** @return BelongsTo<User, $this> */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<DeliveryZone, $this> */
    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    public function isVoid(): bool
    {
        return $this->status === OrderStatus::Void;
    }
}
