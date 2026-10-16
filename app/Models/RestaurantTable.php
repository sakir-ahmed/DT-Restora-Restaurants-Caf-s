<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\RestaurantTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantTable extends Model
{
    /** @use HasFactory<RestaurantTableFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = ['branch_id', 'name', 'area', 'seats', 'status', 'is_active'];

    protected $casts = [
        'seats' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
