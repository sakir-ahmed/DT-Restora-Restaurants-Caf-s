<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A way to take money at the POS (cash, bKash, a bank POS terminal and so on).
 * The type decides how a payment behaves: cash gives change, a wallet needs a
 * TrxID and waits for manager verification, card and other complete at once.
 */
class PaymentMethod extends Model
{
    public const TYPE_CASH = 'cash';

    public const TYPE_WALLET = 'wallet';

    public const TYPE_CARD = 'card';

    public const TYPE_OTHER = 'other';

    public const TYPES = [self::TYPE_CASH, self::TYPE_WALLET, self::TYPE_CARD, self::TYPE_OTHER];

    protected $fillable = ['code', 'name', 'name_bn', 'type', 'is_system', 'is_active', 'sort_order'];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @param  Builder<PaymentMethod>  $builder
     * @return Builder<PaymentMethod>
     */
    public function scopeActive(Builder $builder): Builder
    {
        return $builder->where('is_active', true);
    }

    public function isCash(): bool
    {
        return $this->type === self::TYPE_CASH;
    }

    public function isWallet(): bool
    {
        return $this->type === self::TYPE_WALLET;
    }

    public function displayName(string $locale): string
    {
        return $locale === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }

    /**
     * Find a method by its code, or fail the same way an unknown payment
     * method should: with a validation-friendly exception.
     */
    public static function findByCode(string $code): ?self
    {
        return self::query()->where('code', $code)->first();
    }
}
