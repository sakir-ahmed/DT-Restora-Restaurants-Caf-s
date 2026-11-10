<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * A promotion the owner writes by hand and sends to a group of customers.
 */
class SmsPromotionService
{
    public const AUDIENCES = ['all', 'recent', 'inactive'];

    /** A customer counts as recent when they ordered within this many days. */
    public const WINDOW_DAYS = 30;

    /**
     * @return Builder<Customer>
     */
    public function audience(string $audience): Builder
    {
        $cutoff = now()->subDays(self::WINDOW_DAYS);

        return Customer::query()
            ->where('is_active', true)
            ->whereNotNull('mobile')
            ->when($audience === 'recent', fn (Builder $query) => $query->whereHas('orders', fn ($orders) => $orders->where('created_at', '>=', $cutoff)))
            ->when($audience === 'inactive', fn (Builder $query) => $query->whereDoesntHave('orders', fn ($orders) => $orders->where('created_at', '>=', $cutoff)));
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return collect(self::AUDIENCES)->mapWithKeys(fn (string $audience): array => [$audience => $this->audience($audience)->count()])->all();
    }

    /**
     * Queue the message to each customer once. Returns how many were queued.
     */
    public function send(string $message, string $audience, ?User $by = null): int
    {
        $seen = [];
        $queued = 0;

        $this->audience($audience)->orderBy('id')->each(function (Customer $customer) use ($message, $by, &$seen, &$queued): void {
            $mobile = normalize_bd_mobile((string) $customer->mobile);

            if ($mobile === '' || isset($seen[$mobile])) {
                return;
            }

            $seen[$mobile] = true;
            SendSmsJob::deliver($mobile, $message, 'promotion', $by?->id);
            $queued++;
        });

        return $queued;
    }
}
