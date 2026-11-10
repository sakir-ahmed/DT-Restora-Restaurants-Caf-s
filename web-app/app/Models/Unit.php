<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'name_bn', 'symbol', 'type', 'base_unit_id', 'factor', 'is_system',
    ];

    protected $casts = [
        'factor' => 'decimal:6',
        'is_system' => 'boolean',
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

    /**
     * How many rows use this unit, per table. A unit that appears on an
     * ingredient, purchase, wastage or transfer cannot be deleted, and its
     * size cannot change, because that would change the meaning of old records.
     *
     * @return array<string, int>
     */
    public function usage(): array
    {
        return [
            'ingredients' => DB::table('ingredients')->where('unit_id', $this->id)->count(),
            'purchases' => DB::table('purchase_items')->where('unit_id', $this->id)->count(),
            'wastages' => DB::table('wastages')->where('unit_id', $this->id)->count(),
            'transfers' => DB::table('stock_transfer_items')->where('unit_id', $this->id)->count(),
        ];
    }

    public function isInUse(): bool
    {
        return array_sum($this->usage()) > 0;
    }

    public function isCompatibleWith(Unit $other): bool
    {
        return $this->id === $other->id
            || $this->base_unit_id === $other->id
            || $other->base_unit_id === $this->id
            || ($this->base_unit_id !== null && $this->base_unit_id === $other->base_unit_id);
    }
}
