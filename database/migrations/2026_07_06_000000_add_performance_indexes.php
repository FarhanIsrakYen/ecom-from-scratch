<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->index('base_price', 'products_base_price_index');
            $table->index(['status', 'base_price', 'id'], 'products_status_price_id_index');
            $table->index(['status', 'category_id', 'id'], 'products_status_category_id_index');
            $table->index(['status', 'brand_id', 'id'], 'products_status_brand_id_index');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['status', 'payment_status', 'created_at'], 'orders_status_payment_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_status_payment_created_index');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_status_brand_id_index');
            $table->dropIndex('products_status_category_id_index');
            $table->dropIndex('products_status_price_id_index');
            $table->dropIndex('products_base_price_index');
        });
    }
};
