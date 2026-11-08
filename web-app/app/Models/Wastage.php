<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wastage extends Model
{
    use BelongsToBranch, HasFactory;

    public const REASONS = [
        'spoiled', 'dropped', 'complimentary', 'over_production',
        'kitchen_mistake', 'other',
    ];

    protected $fillable = [
        'branch_id', 'ingredient_id', 'unit_id', 'qty', 'reason_code',
        'note', 'cost', 'user_id',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'cost' => 'decimal:2',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Ingredient, $this> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
