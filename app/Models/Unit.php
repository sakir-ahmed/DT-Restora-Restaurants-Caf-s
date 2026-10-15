<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'name_bn', 'symbol', 'type', 'base_unit_id', 'factor',
    ];

    protected $casts = [
        'factor' => 'decimal:6',
    ];

    /** @return BelongsTo<Unit, $this> */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    /**
     * Convert a quantity from this unit into the base unit.
     */
    public function toBase(float|string $qty): float
    {
        return round((float) $qty * (float) $this->factor, 6);
    }

    /**
     * Convert a base-unit quantity into this unit.
     */
    public function fromBase(float|string $baseQty): float
    {
        $factor = (float) $this->factor;

        return $factor === 0.0 ? 0.0 : round((float) $baseQty / $factor, 6);
    }

    public function isCompatibleWith(Unit $other): bool
    {
        return $this->id === $other->id
            || $this->base_unit_id === $other->id
            || $other->base_unit_id === $this->id
            || ($this->base_unit_id !== null && $this->base_unit_id === $other->base_unit_id);
    }
}
