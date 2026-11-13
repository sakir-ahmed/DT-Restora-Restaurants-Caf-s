<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\FlushesPublicCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    use BelongsToBranch, HasFactory;
    use FlushesPublicCache;

    protected $fillable = [
        'branch_id', 'name', 'name_bn', 'sort_order', 'is_active', 'is_preorder_only',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_preorder_only' => 'boolean',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<MenuItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * Categories that can be ordered any time. The Saudi Arabian categories are pre-order only:
     * they live in the menu book and stay out of the POS, the website menu and the table menu.
     */
    public function scopeRegular(Builder $builder): Builder
    {
        return $builder->where('is_preorder_only', false);
    }

    public function scopeActive(Builder $builder): Builder
    {
        return $builder->where('is_active', true);
    }

    public function scopeOrdered(Builder $builder): Builder
    {
        return $builder->orderBy('sort_order')->orderBy('name');
    }

    public function displayName(string $locale): string
    {
        return $locale === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }
}
