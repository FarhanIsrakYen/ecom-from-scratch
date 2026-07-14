<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_dashboard_analytics(): void
    {
        $admin = $this->userWithRole(RoleEnum::Admin);
        $alice = $this->userWithRole(RoleEnum::Customer, ['name' => 'Alice Buyer']);
        $bob = $this->userWithRole(RoleEnum::Customer, ['name' => 'Bob Buyer']);
        $product = Product::factory()->create(['name' => 'Running Shoe', 'sku' => 'SHOE-BASE']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'SHOE-RED-42',
        ]);
        $secondProduct = Product::factory()->create(['name' => 'Cotton Shirt']);
        $coupon = Coupon::query()->create([
            'code' => 'SAVE10',
            'type' => 'fixed',
            'value' => 10,
            'status' => 'active',
        ]);

        $paidOrder = $this->createOrder($alice, [
            'order_number' => 'ORD-001',
            'status' => 'paid',
            'payment_status' => 'paid',
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'discount' => 10,
            'total' => 90,
        ]);
        $paidOrder->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->createOrderItem($paidOrder, $product, $variant, 3, 30);
        CouponUsage::query()->create([
            'coupon_id' => $coupon->id,
            'user_id' => $alice->id,
            'order_id' => $paidOrder->id,
            'discount_amount' => 10,
        ]);

        $pendingOrder = $this->createOrder($bob, [
            'order_number' => 'ORD-002',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 120,
        ]);
        $pendingOrder->forceFill(['created_at' => now()])->save();

        Inventory::factory()->forVariant($variant)->create([
            'available_stock' => 2,
            'reserved_stock' => 1,
            'low_stock_threshold' => 5,
        ]);
        Inventory::factory()->create([
            'product_id' => $secondProduct->id,
            'available_stock' => 20,
            'low_stock_threshold' => 5,
        ]);

        Sanctum::actingAs($admin);

        $this
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_sales', 90)
            ->assertJsonPath('data.summary.total_orders', 2)
            ->assertJsonPath('data.summary.total_customers', 2)
            ->assertJsonPath('data.summary.total_products', 2)
            ->assertJsonPath('data.summary.pending_orders', 1)
            ->assertJsonPath('data.summary.low_stock_products', 1)
            ->assertJsonPath('data.top_selling_products.0.product_id', $product->id)
            ->assertJsonPath('data.top_selling_products.0.quantity_sold', 3)
            ->assertJsonPath('data.top_customers.0.customer_id', $alice->id)
            ->assertJsonPath('data.recent_orders.0.order_number', 'ORD-002')
            ->assertJsonPath('data.revenue_by_category.0.category_id', $product->category_id)
            ->assertJsonPath('data.coupon_usage.0.code', 'SAVE10')
            ->assertJsonPath('data.inventory_alerts.0.sku', 'SHOE-RED-42')
            ->assertJsonStructure([
                'data' => [
                    'sales_chart' => [
                        'daily',
                        'weekly',
                        'monthly',
                    ],
                ],
            ]);
    }

    public function test_dashboard_requires_admin_role(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleEnum::Customer));

        $this
            ->getJson('/api/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    private function createOrder(User $user, array $attributes): Order
    {
        return Order::query()->create(array_merge([
            'user_id' => $user->id,
            'order_number' => fake()->unique()->bothify('ORD-####'),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 0,
            'discount' => 0,
            'delivery_charge' => 0,
            'tax' => 0,
            'total' => 0,
        ], $attributes));
    }

    private function createOrderItem(Order $order, Product $product, ProductVariant $variant, int $quantity, float $unitPrice): void
    {
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'variant_attributes' => ['size' => '42', 'color' => 'red'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
        ]);
    }

    private function userWithRole(RoleEnum $role, array $attributes = []): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role->value],
            ['label' => $role->label()],
        );

        $user = User::factory()->create($attributes);
        $user->roles()->attach($roleModel);

        return $user;
    }
}
