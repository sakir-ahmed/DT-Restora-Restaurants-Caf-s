<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class ApproveStockTransferAction
{
    public function execute(StockTransfer $transfer, ?User $user = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user): StockTransfer {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== StockTransfer::STATUS_REQUESTED) {
                throw new LogicException('Only a requested transfer can be approved.');
            }

            $transfer->update([
                'status' => StockTransfer::STATUS_APPROVED,
                'approved_by' => $user?->id,
            ]);

            activity()
                ->performedOn($transfer)
                ->causedBy($user)
                ->log('stock.transfer_approved');

            return $transfer;
        });
    }
}
