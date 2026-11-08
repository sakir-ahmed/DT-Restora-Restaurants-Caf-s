<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LetterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Letter extends Model
{
    /** @use HasFactory<LetterFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'author_id',
        'title',
        'recipient',
        'subject',
        'body',
        'letter_date',
    ];

    protected $casts = [
        'letter_date' => 'date',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
