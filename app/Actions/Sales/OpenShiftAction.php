<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Branch;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenShiftAction
{
    public function execute(Branch $branch, User $cashier, float $openingCash, string $terminalCode = 'T01'): Shift
    {
        return DB::transaction(function () use ($branch, $cashier, $openingCash, $terminalCode): Shift {
            $open = Shift::query()
                ->where('branch_id', $branch->id)
                ->where('user_id', $cashier->id)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                throw ValidationException::withMessages([
                    'shift' => __('orders.shift_already_open'),
                ]);
            }

            return Shift::query()->create([
                'branch_id' => $branch->id,
                'user_id' => $cashier->id,
                'terminal_code' => $terminalCode,
                'opening_cash' => $openingCash,
                'opened_at' => now(),
            ]);
        });
    }
}
