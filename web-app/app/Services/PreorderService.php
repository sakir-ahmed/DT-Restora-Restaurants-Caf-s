<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\Branch;
use App\Models\Preorder;
use App\Services\Sms\SmsFeatures;
use App\Services\Sms\SmsTemplates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * Takes a pre-order from the menu book: prices it from the current menu, keeps it, and texts the
 * guest a copy when they gave a mobile number and the SMS feature is switched on.
 */
class PreorderService
{
    public function __construct(private readonly ReservationService $pricing) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array{customer_name?: ?string, customer_mobile?: ?string, party_size?: ?int, expected_at?: ?Carbon, restaurant_table_id?: ?int, note?: ?string}  $details
     */
    public function place(Branch $branch, array $lines, array $details, string $source, ?int $userId = null): Preorder
    {
        $priced = $this->pricing->priceLines($branch, $lines);

        $preorder = Preorder::query()->create([
            'branch_id' => $branch->id,
            'source' => $source,
            'customer_name' => $details['customer_name'] ?? null,
            'customer_mobile' => $details['customer_mobile'] ?? null,
            'party_size' => $details['party_size'] ?? null,
            'expected_at' => $details['expected_at'] ?? null,
            'restaurant_table_id' => $details['restaurant_table_id'] ?? null,
            'note' => $details['note'] ?? null,
            'items' => $priced['lines'],
            'total' => $priced['total'],
            'created_by' => $userId,
        ]);

        $this->sendSms($preorder, $branch);

        return $preorder;
    }

    /**
     * The guest copy by text. Does nothing without a mobile number; the SMS feature switch is
     * checked when the message is sent, so a switched-off feature leaves a "skipped" log line.
     */
    public function sendSms(Preorder $preorder, Branch $branch): void
    {
        $mobile = normalize_bd_mobile((string) $preorder->customer_mobile);

        if ($mobile === '') {
            return;
        }

        $when = $preorder->expected_at === null
            ? ''
            : __('preorders.sms_when', ['when' => $preorder->expected_at->setTimezone(config('app.timezone'))->translatedFormat('D j M, g:i A')]);

        SendSmsJob::deliver($mobile, trim((string) preg_replace('/\s{2,}/', ' ', SmsTemplates::render('preorder_confirmed', [
            'order' => $preorder->order_no,
            'branch' => $branch->name,
            'items' => $this->summary($preorder),
            'total' => Number::format((float) $preorder->total, 2),
            'when' => $when,
        ]))), 'preorder_confirmed');

        if (SmsFeatures::enabled('preorder_confirmed')) {
            $preorder->forceFill(['sms_sent_at' => now()])->save();
        }
    }

    /**
     * "2 x Burger, 1 x Cola" kept short enough that the whole text stays a few SMS parts.
     */
    private function summary(Preorder $preorder): string
    {
        $locale = app()->getLocale();

        $parts = collect($preorder->items)->map(function (array $line) use ($locale): string {
            $name = $locale === 'bn' && filled($line['name_bn'] ?? null) ? $line['name_bn'] : $line['name'];

            return $line['quantity'].' x '.$name;
        });

        $text = $parts->implode(', ');

        return mb_strlen($text) > 150 ? mb_substr($text, 0, 147).'...' : $text;
    }
}
