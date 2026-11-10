<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\IngredientStock;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wastage;
use App\Services\Accounting\AccountingService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LogWastageAction
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Log wastage with a mandatory reason code. Cost is always
     * calculated server-side from the branch's weighted-average cost.
     */
    public function execute(
        Branch $branch,
        Ingredient $ingredient,
        Unit $unit,
        float $qty,
        string $reasonCode,
        ?string $note,
        ?User $user = null,
    ): Wastage {
        if (! in_array($reasonCode, Wastage::REASONS, true)) {
            throw new InvalidArgumentException('Unknown wastage reason code.');
        }

        if ($qty <= 0) {
            throw new InvalidArgumentException('Wastage quantity must be positive.');
        }

        if (! $unit->isCompatibleWith($ingredient->unit)) {
            throw new InvalidArgumentException('Unit is not compatible with the ingredient.');
        }

        return DB::transaction(function () use ($branch, $ingredient, $unit, $qty, $reasonCode, $note, $user): Wastage {
            $stock = IngredientStock::query()
                ->where('branch_id', $branch->id)
                ->where('ingredient_id', $ingredient->id)
                ->lockForUpdate()
                ->first();

            $baseQty = $unit->toBase($qty);

            $cost = round($baseQty * (float) ($stock->avg_cost ?? 0), 2);

            $wastage = Wastage::query()->create([
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'unit_id' => $unit->id,
                'qty' => $qty,
                'reason_code' => $reasonCode,
                'note' => $note,
                'cost' => $cost,
                'user_id' => $user?->id,
            ]);

            $this->stock->adjust(
                branchId: $branch->id,
                ingredientId: $ingredient->id,
                baseQtyDelta: -$baseQty,
                movementType: 'wastage',
                reference: $wastage,
                unitCost: (float) ($stock->avg_cost ?? 0),
                userId: $user?->id,
            );

            app(AccountingService::class)->postWastage($wastage->fresh(), $user);

            activity()
                ->performedOn($wastage)
                ->causedBy($user)
                ->log('wastage.logged');

            return $wastage;
        });
    }
}
