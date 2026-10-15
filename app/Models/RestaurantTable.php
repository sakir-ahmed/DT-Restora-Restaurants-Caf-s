<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantTable extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['branch_id', 'name', 'area', 'seats', 'status', 'is_active'];

    protected $casts = [
        'seats' => 'integer',
        'is_active' => 'boolean',
    ];
}
