<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryAdjustmentRequest;
use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use App\Services\InventoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InventoryController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'product_variant_id' => ['sometimes', 'integer', 'exists:product_variants,id'],
            'low_stock' => ['sometimes', 'boolean'],
        ]);

        $items = Inventory::query()
            ->when($filters['product_id'] ?? null, fn ($query, mixed $productId) => $query->where('product_id', $productId))
            ->when($filters['product_variant_id'] ?? null, fn ($query, mixed $variantId) => $query->where('product_variant_id', $variantId))
            ->when((bool) ($filters['low_stock'] ?? false), fn ($query) => $query
                ->where('low_stock_threshold', '>', 0)
                ->whereColumn('available_stock', '<', 'low_stock_threshold'))
            ->latest()
            ->get();

        return $this->success(InventoryResource::collection($items), 'Inventory retrieved.');
    }

    public function adjust(InventoryAdjustmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $inventory = match ($data['type']) {
                'stock_in' => $this->inventory->increaseStock(
                    $data['product_id'],
                    $data['product_variant_id'] ?? null,
                    $data['quantity'],
                    $data['reason'] ?? 'Admin stock increase',
                    $data['reference_type'] ?? 'admin_adjustment',
                    $data['reference_id'] ?? null,
                    $request->user()?->id,
                ),
                'stock_out' => $this->inventory->decreaseStock(
                    $data['product_id'],
                    $data['product_variant_id'] ?? null,
                    $data['quantity'],
                    $data['reason'] ?? 'Admin stock decrease',
                    $data['reference_type'] ?? 'admin_adjustment',
                    $data['reference_id'] ?? null,
                    $request->user()?->id,
                ),
                default => $this->inventory->setAvailableStock(
                    $data['product_id'],
                    $data['product_variant_id'] ?? null,
                    $data['quantity'],
                    $data['reason'] ?? 'Admin stock adjustment',
                    $data['reference_type'] ?? 'admin_adjustment',
                    $data['reference_id'] ?? null,
                    $request->user()?->id,
                ),
            };
        } catch (InsufficientStockException|InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        if (array_key_exists('low_stock_threshold', $data)) {
            $inventory = $this->inventory->updateLowStockThreshold(
                $inventory->product_id,
                $inventory->product_variant_id,
                $data['low_stock_threshold'],
            );
        }

        return $this->success(new InventoryResource($inventory), 'Inventory adjusted.');
    }
}
