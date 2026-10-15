<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalYear extends Model
{
    protected $fillable = ['name', 'starts_at', 'ends_at', 'is_locked', 'locked_at'];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function contains(\DateTimeInterface $date): bool
    {
        return $this->starts_at->startOfDay()->lte($date) && $this->ends_at->endOfDay()->gte($date);
    }
}
