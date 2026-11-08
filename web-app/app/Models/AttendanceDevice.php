<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\AttendanceDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceDevice extends Model
{
    /** @use HasFactory<AttendanceDeviceFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'name', 'serial', 'ip_address', 'token_hash', 'is_active', 'last_seen_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<AttendancePunch, $this> */
    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }
}
