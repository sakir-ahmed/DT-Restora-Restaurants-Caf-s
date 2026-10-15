<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_EQUITY = 'equity';

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const TYPE_COGS = 'cogs';

    protected $fillable = ['code', 'name', 'type', 'subtype', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
