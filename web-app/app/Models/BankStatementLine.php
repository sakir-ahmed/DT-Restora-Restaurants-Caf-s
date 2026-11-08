<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = [
        'account_code', 'occurred_on', 'amount', 'description',
        'journal_id', 'matched_at', 'user_id',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'amount' => 'decimal:2',
        'matched_at' => 'datetime',
    ];

    /** @return BelongsTo<Journal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function isMatched(): bool
    {
        return $this->journal_id !== null;
    }
}
