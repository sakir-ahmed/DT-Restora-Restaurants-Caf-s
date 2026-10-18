<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['demo_session_id', 'type', 'name', 'path', 'meta', 'occurred_at'];

    protected $casts = [
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<DemoSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(DemoSession::class, 'demo_session_id');
    }
}
