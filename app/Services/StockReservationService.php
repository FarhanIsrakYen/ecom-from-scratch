<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

class StockReservationService
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function reserve(
        int $productId,
        ?int $variantId,
        int $quantity,
        ?string $referenceType = 'checkout',
        ?int $referenceId = null,
        ?int $createdBy = null,
    ): Inventory {
        return DB::transaction(function () use ($productId, $variantId, $quantity, $referenceType, $referenceId, $createdBy): Inventory {
            $inventory = $this->inventoryService->lockedInventory($productId, $variantId);

            if ($inventory->available_stock < $quantity) {
                throw new InsufficientStockException;
            }

            $inventory->available_stock -= $quantity;
            $inventory->reserved_stock += $quantity;
            $inventory->save();

            $this->inventoryService->recordMovement(
                $inventory,
                InventoryMovementType::Reserved,
                $quantity,
                'Checkout reservation',
                $referenceType,
                $referenceId,
                $createdBy,
            );
            $this->inventoryService->syncLegacyVariantStock($inventory);
            $this->inventoryService->dispatchLowStockIfNeeded($inventory->refresh());

            return $inventory;
        });
    }

    public function release(
        int $productId,
        ?int $variantId,
        int $quantity,
        ?string $referenceType = 'payment',
        ?int $referenceId = null,
        ?int $createdBy = null,
    ): Inventory {
        return DB::transaction(function () use ($productId, $variantId, $quantity, $referenceType, $referenceId, $createdBy): Inventory {
            $inventory = $this->inventoryService->lockedInventory($productId, $variantId);

            if ($inventory->reserved_stock < $quantity) {
                throw new InsufficientStockException('Reserved stock is lower than the requested release quantity.');
            }

            $inventory->reserved_stock -= $quantity;
            $inventory->available_stock += $quantity;
            $inventory->save();

            $this->inventoryService->recordMovement(
                $inventory,
                InventoryMovementType::Released,
                $quantity,
                'Payment failed',
                $referenceType,
                $referenceId,
                $createdBy,
            );
            $this->inventoryService->syncLegacyVariantStock($inventory);

            return $inventory->refresh();
        });
    }

    public function confirmSold(
        int $productId,
        ?int $variantId,
        int $quantity,
        ?string $referenceType = 'payment',
        ?int $referenceId = null,
        ?int $createdBy = null,
    ): Inventory {
        return DB::transaction(function () use ($productId, $variantId, $quantity, $referenceType, $referenceId, $createdBy): Inventory {
            $inventory = $this->inventoryService->lockedInventory($productId, $variantId);

            if ($inventory->reserved_stock < $quantity) {
                throw new InsufficientStockException('Reserved stock is lower than the requested sold quantity.');
            }

            $inventory->reserved_stock -= $quantity;
            $inventory->sold_stock += $quantity;
            $inventory->save();

            $this->inventoryService->recordMovement(
                $inventory,
                InventoryMovementType::Sold,
                $quantity,
                'Payment succeeded',
                $referenceType,
                $referenceId,
                $createdBy,
            );
            $this->inventoryService->dispatchLowStockIfNeeded($inventory->refresh());

            return $inventory;
        });
    }
}
