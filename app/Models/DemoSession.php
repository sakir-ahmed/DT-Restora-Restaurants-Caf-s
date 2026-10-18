<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemoSession extends Model
{
    protected $fillable = [
        'token', 'mobile', 'locale', 'ip', 'user_agent', 'device', 'referrer', 'utm_source', 'utm_campaign',
        'stage', 'stage_rank', 'events_count', 'page_views', 'consented', 'started_at', 'last_seen_at',
    ];

    protected $casts = [
        'consented' => 'boolean',
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /** @return HasMany<DemoEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(DemoEvent::class)->orderBy('occurred_at')->orderBy('id');
    }
}
