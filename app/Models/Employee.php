<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'user_id', 'name', 'name_bn', 'designation',
        'mobile', 'monthly_salary', 'joined_at', 'is_active',
    ];

    protected $casts = [
        'monthly_salary' => 'decimal:2',
        'joined_at' => 'date',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayName(string $locale): string
    {
        return $locale === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }
}
