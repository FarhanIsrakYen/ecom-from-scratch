<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\DeliveryZone;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PopularSearch;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SearchHistory;
use App\Models\Shipment;
use App\Models\TaxSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoryCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_major_domain_factories_create_valid_records(): void
    {
        $role = Role::factory()->role(RoleEnum::Customer)->create();
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $payment = Payment::factory()->create(['order_id' => $order->id]);
        $document = Document::factory()->create();

        $records = [
            Cart::factory()->create(['user_id' => $user->id]),
            CartItem::factory()->forProduct($product, $variant)->create(),
            Coupon::factory()->create(),
            CouponUsage::factory()->forUserOrder($user, $order)->create(),
            DeliveryZone::factory()->create(),
            DocumentChunk::factory()->create(['document_id' => $document->id]),
            Inventory::factory()->forVariant($variant)->create(),
            OrderAddress::factory()->create(['order_id' => $order->id]),
            OrderItem::factory()->create(['order_id' => $order->id]),
            PaymentAttempt::factory()->create(['payment_id' => $payment->id, 'order_id' => $order->id]),
            PopularSearch::factory()->create(),
            ProductImage::factory()->create(['product_id' => $product->id]),
            SearchHistory::factory()->create(['user_id' => $user->id]),
            Shipment::factory()->create(['order_id' => $order->id]),
            TaxSetting::factory()->create(),
        ];

        foreach ($records as $record) {
            $this->assertTrue($record->exists, $record::class.' factory did not persist a model.');
        }
    }
}
