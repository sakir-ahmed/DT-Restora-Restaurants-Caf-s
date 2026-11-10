<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per SMS the system tried to send, whether it went out, failed or
 * was skipped because its feature is switched off.
 */
class SmsLog extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_DEMO = 'demo';

    protected $fillable = ['feature', 'mobile', 'message', 'provider', 'status', 'response', 'user_id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
