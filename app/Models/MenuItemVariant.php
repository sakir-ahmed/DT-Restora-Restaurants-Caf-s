<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\FlushesPublicCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemVariant extends Model
{
    use FlushesPublicCache;
    use HasFactory;

    protected $fillable = [
        'menu_item_id', 'name', 'name_bn', 'price', 'is_default', 'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<MenuItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function displayName(string $locale): string
    {
        return $locale === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }
}
