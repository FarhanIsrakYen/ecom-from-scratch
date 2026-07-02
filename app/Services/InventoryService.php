<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Events\LowStockDetected;
use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function increaseStock(
        int $productId,
        ?int $variantId,
        int $quantity,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $createdBy = null,
    ): Inventory {
        return $this->mutateLocked(
            $productId,
            $variantId,
            function (Inventory $inventory) use ($quantity): void {
                $inventory->available_stock += $quantity;
            },
            InventoryMovementType::StockIn,
            $quantity,
            $reason,
            $referenceType,
            $referenceId,
            $createdBy,
        );
    }

    public function decreaseStock(
        int $productId,
        ?int $variantId,
        int $quantity,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $createdBy = null,
    ): Inventory {
        return $this->mutateLocked(
            $productId,
            $variantId,
            function (Inventory $inventory) use ($quantity): void {
                if ($inventory->available_stock < $quantity) {
                    throw new InsufficientStockException;
                }

                $inventory->available_stock -= $quantity;
            },
            InventoryMovementType::StockOut,
            $quantity,
            $reason,
            $referenceType,
            $referenceId,
            $createdBy,
        );
    }

    public function setAvailableStock(
        int $productId,
        ?int $variantId,
        int $availableStock,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $createdBy = null,
    ): Inventory {
        return $this->mutateLocked(
            $productId,
            $variantId,
            function (Inventory $inventory) use ($availableStock): void {
                $inventory->available_stock = $availableStock;
            },
            InventoryMovementType::Adjusted,
            $availableStock,
            $reason,
            $referenceType,
            $referenceId,
            $createdBy,
        );
    }

    public function updateLowStockThreshold(int $productId, ?int $variantId, int $threshold): Inventory
    {
        return DB::transaction(function () use ($productId, $variantId, $threshold): Inventory {
            $inventory = $this->lockedInventory($productId, $variantId);
            $inventory->update(['low_stock_threshold' => $threshold]);

            $this->dispatchLowStockIfNeeded($inventory->refresh());

            return $inventory;
        });
    }

    public function lowStockQuery()
    {
        return Inventory::query()
            ->where('low_stock_threshold', '>', 0)
            ->whereColumn('available_stock', '<', 'low_stock_threshold');
    }

    public function lockedInventory(int $productId, ?int $variantId): Inventory
    {
        $this->assertVariantBelongsToProduct($productId, $variantId);

        $query = Inventory::query()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId);

        /** @var Inventory|null $inventory */
        $inventory = $query->lockForUpdate()->first();

        if ($inventory instanceof Inventory) {
            return $inventory;
        }

        Inventory::query()->create([
            'product_id' => $productId,
            'product_variant_id' => $variantId,
        ]);

        /** @var Inventory $inventory */
        $inventory = Inventory::query()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->lockForUpdate()
            ->firstOrFail();

        return $inventory;
    }

    public function recordMovement(
        Inventory $inventory,
        InventoryMovementType $type,
        int $quantity,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        return InventoryMovement::query()->create([
            'product_id' => $inventory->product_id,
            'variant_id' => $inventory->product_variant_id,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $createdBy,
        ]);
    }

    public function dispatchLowStockIfNeeded(Inventory $inventory): void
    {
        if ($inventory->isLowStock()) {
            LowStockDetected::dispatch($inventory);
        }
    }

    private function mutateLocked(
        int $productId,
        ?int $variantId,
        callable $mutation,
        InventoryMovementType $movementType,
        int $movementQuantity,
        ?string $reason,
        ?string $referenceType,
        ?int $referenceId,
        ?int $createdBy,
    ): Inventory {
        return DB::transaction(function () use (
            $productId,
            $variantId,
            $mutation,
            $movementType,
            $movementQuantity,
            $reason,
            $referenceType,
            $referenceId,
            $createdBy,
        ): Inventory {
            $inventory = $this->lockedInventory($productId, $variantId);

            $mutation($inventory);
            $inventory->save();

            $this->recordMovement($inventory, $movementType, $movementQuantity, $reason, $referenceType, $referenceId, $createdBy);
            $this->syncLegacyVariantStock($inventory);
            $this->dispatchLowStockIfNeeded($inventory->refresh());

            return $inventory;
        });
    }

    public function syncLegacyVariantStock(Inventory $inventory): void
    {
        if ($inventory->product_variant_id === null) {
            return;
        }

        ProductVariant::query()
            ->whereKey($inventory->product_variant_id)
            ->update(['stock' => $inventory->available_stock]);
    }

    private function assertVariantBelongsToProduct(int $productId, ?int $variantId): void
    {
        if ($variantId === null) {
            return;
        }

        $exists = ProductVariant::query()
            ->whereKey($variantId)
            ->where('product_id', $productId)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException('The selected variant does not belong to the selected product.');
        }
    }
}
