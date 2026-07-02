<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\CartException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Services\CartService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): JsonResponse
    {
        return $this->success(
            new CartResource($this->carts->getCart($request->user())),
            'Cart retrieved.',
        );
    }

    public function store(AddCartItemRequest $request): JsonResponse
    {
        try {
            $cart = $this->carts->addItem(
                $request->user(),
                $request->integer('product_id'),
                $request->integer('product_variant_id') ?: null,
                $request->integer('quantity'),
            );
        } catch (CartException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(new CartResource($cart), 'Cart item added.', 201);
    }

    public function update(UpdateCartItemRequest $request, CartItem $item): JsonResponse
    {
        try {
            $cart = $this->carts->updateItem($request->user(), $item, $request->integer('quantity'));
        } catch (CartException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(new CartResource($cart), 'Cart item updated.');
    }

    public function destroy(Request $request, CartItem $item): JsonResponse
    {
        try {
            $cart = $this->carts->removeItem($request->user(), $item);
        } catch (CartException $exception) {
            return $this->error($exception->getMessage(), 404);
        }

        return $this->success(new CartResource($cart), 'Cart item removed.');
    }

    public function clear(Request $request): JsonResponse
    {
        return $this->success(
            new CartResource($this->carts->clear($request->user())),
            'Cart cleared.',
        );
    }
}
