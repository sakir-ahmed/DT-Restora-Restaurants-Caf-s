<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Payment;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseShiftAction
{
    public function execute(Shift $shift, float $countedCash): Shift
    {
        return DB::transaction(function () use ($shift, $countedCash): Shift {
            $shift = Shift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $shift->isOpen()) {
                throw ValidationException::withMessages([
                    'shift' => __('orders.shift_already_closed'),
                ]);
            }

            $cashSales = Payment::query()
                ->where('method', 'cash')
                ->where('status', Payment::STATUS_COMPLETED)
                ->whereHas('order', fn ($query) => $query->where('shift_id', $shift->id))
                ->sum('amount');

            $expected = round((float) $shift->opening_cash + (float) $cashSales, 2);

            $shift->update([
                'counted_cash' => $countedCash,
                'expected_cash' => $expected,
                'difference' => round($countedCash - $expected, 2),
                'closed_at' => now(),
            ]);

            activity()
                ->performedOn($shift)
                ->causedBy($shift->user)
                ->log('shift.closed');

            return $shift;
        });
    }
}
