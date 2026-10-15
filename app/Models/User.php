<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'username', 'mobile', 'google_id', 'password', 'pin', 'branch_id', 'locale', 'is_active'])]
#[Hidden(['password', 'pin', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable;

    /** Staff always authenticate through the staff guard. */
    protected $guard_name = 'staff';

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
        'pin' => 'hashed',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Branches this user may see. Users without a branch assignment
     * (owner, super-admin, accountant) see all active branches.
     *
     * @return Collection<int, Branch>
     */
    public function accessibleBranches()
    {
        if ($this->branch_id !== null) {
            return Branch::query()->where('id', $this->branch_id)->get();
        }

        return Branch::query()->orderBy('name')->get();
    }

    public function canAccessBranch(int $branchId): bool
    {
        return $this->branch_id === null || $this->branch_id === $branchId;
    }

    public function seesAllBranches(): bool
    {
        return $this->branch_id === null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'username', 'mobile', 'branch_id', 'is_active'])
            ->logOnlyDirty();
    }
}
